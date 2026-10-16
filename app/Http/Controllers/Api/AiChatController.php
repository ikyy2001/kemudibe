<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiQuotaExceededException;
use App\Exceptions\AiServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatRequest;
use App\Models\AiUsageLog;
use App\Services\Ai\AiChatService;
use App\Services\Ai\AiDailyQuota;
use App\Services\Ai\AiResponseCache;
use App\Services\Ai\AiUsageLogger;
use App\Support\Ai\AiTierResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function __construct(
        protected AiChatService $aiChatService,
        protected AiDailyQuota $aiDailyQuota,
        protected AiResponseCache $responseCache,
        protected AiUsageLogger $usageLogger
    ) {
    }

    /**
     * Get quota information for public guest users.
     */
    public function publicQuota(Request $request): JsonResponse
    {
        $tier = AiTierResolver::TIER_GUEST;
        $identifier = sha1($request->ip() ?: '127.0.0.1');

        return response()->json([
            'success' => true,
            'data' => [
                'tier' => $tier,
                'per_minute_limit' => $this->aiDailyQuota->getPerMinuteLimit($tier),
                'daily_limit' => $this->aiDailyQuota->getDailyLimit($tier),
                'daily_remaining' => $this->aiDailyQuota->remaining($tier, $identifier),
                'daily_resets_at' => $this->aiDailyQuota->getResetsAt()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get quota information for authenticated dashboard users.
     */
    public function dashboardQuota(Request $request): JsonResponse
    {
        $user = $request->user();
        $tier = AiTierResolver::resolve($user);
        $identifier = (string) $user->id;

        return response()->json([
            'success' => true,
            'data' => [
                'tier' => $tier,
                'per_minute_limit' => $this->aiDailyQuota->getPerMinuteLimit($tier),
                'daily_limit' => $this->aiDailyQuota->getDailyLimit($tier),
                'daily_remaining' => $this->aiDailyQuota->remaining($tier, $identifier),
                'daily_resets_at' => $this->aiDailyQuota->getResetsAt()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Public chat endpoint for Landing Page (no auth required).
     */
    public function publicChat(ChatRequest $request): JsonResponse
    {
        $startTime = hrtime(true);
        $rawMessage = (string) $request->validated('message');
        $history = (array) $request->validated('history', []);
        $tier = AiTierResolver::TIER_GUEST;
        $rawIp = (string) ($request->ip() ?: '127.0.0.1');
        $identifier = sha1($rawIp);

        $lock = Cache::lock("ai:inflight:{$tier}:{$identifier}", 35);
        if (!$lock->get()) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->usageLogger->log(
                userId: null,
                tier: $tier,
                scope: 'landing',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'rate_limited',
                errorCode: 'request_in_progress',
                latencyMs: $latencyMs
            );

            return response()->json([
                'success' => false,
                'code' => 'request_in_progress',
                'message' => 'Pertanyaan sebelumnya masih diproses.',
            ], 409);
        }

        try {
            // 1. Check FAQ response cache (exact match after normalization)
            $cached = $this->responseCache->get('landing', 'guest', $rawMessage, $history);
            if ($cached !== null) {
                $latencyMs = $this->calculateLatency($startTime);
                $totalTokens = $cached['usage']['total_tokens'] ?? null;
                $this->usageLogger->log(
                    userId: null,
                    tier: $tier,
                    scope: 'landing',
                    rawIp: $rawIp,
                    rawMessage: $rawMessage,
                    cacheHit: true,
                    status: 'ok',
                    model: config('ai.model'),
                    totalTokens: $totalTokens,
                    latencyMs: $latencyMs
                );

                $cachedResponse = array_merge($cached, ['cached' => true]);

                return response()->json([
                    'success' => true,
                    'data' => $cachedResponse,
                ]);
            }

            // 2. Check daily quota (cache miss)
            $this->aiDailyQuota->check($tier, $identifier);

            // 3. Call AI service
            $result = $this->aiChatService->chatLanding($rawMessage, $history);

            // 4. Atomically consume daily quota ONLY after successful AI call
            $this->aiDailyQuota->consume($tier, $identifier);

            // 5. Store in FAQ cache if eligible
            $this->responseCache->put('landing', 'guest', $rawMessage, $history, $result);

            $latencyMs = $this->calculateLatency($startTime);
            $totalTokens = $result['usage']['total_tokens'] ?? null;
            $this->usageLogger->log(
                userId: null,
                tier: $tier,
                scope: 'landing',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'ok',
                model: config('ai.model'),
                totalTokens: $totalTokens,
                latencyMs: $latencyMs
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (AiQuotaExceededException $e) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->usageLogger->log(
                userId: null,
                tier: $tier,
                scope: 'landing',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'rate_limited',
                errorCode: 'daily_quota',
                latencyMs: $latencyMs
            );

            return $e->render($request);
        } catch (AiServiceException $e) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->usageLogger->log(
                userId: null,
                tier: $tier,
                scope: 'landing',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'error',
                errorCode: $e->getCategory(),
                latencyMs: $latencyMs
            );

            return $this->handleAiException($e);
        } finally {
            $lock->release();
        }
    }

    /**
     * Authenticated chat endpoint for logged-in Dashboard users.
     */
    public function dashboardChat(ChatRequest $request): JsonResponse
    {
        $startTime = hrtime(true);
        $user = $request->user();
        $tier = AiTierResolver::resolve($user);
        $rawMessage = (string) $request->validated('message');
        $history = (array) $request->validated('history', []);
        $rawIp = (string) ($request->ip() ?: '127.0.0.1');
        $identifier = (string) $user->id;

        $lock = Cache::lock("ai:inflight:{$tier}:{$identifier}", 35);
        if (!$lock->get()) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->usageLogger->log(
                userId: $user->id,
                tier: $tier,
                scope: 'dashboard',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'rate_limited',
                errorCode: 'request_in_progress',
                latencyMs: $latencyMs
            );

            return response()->json([
                'success' => false,
                'code' => 'request_in_progress',
                'message' => 'Pertanyaan sebelumnya masih diproses.',
            ], 409);
        }

        try {
            // 1. Check FAQ response cache (role-specific variant)
            $cached = $this->responseCache->get('dashboard', $tier, $rawMessage, $history);
            if ($cached !== null) {
                $latencyMs = $this->calculateLatency($startTime);
                $totalTokens = $cached['usage']['total_tokens'] ?? null;
                $this->usageLogger->log(
                    userId: $user->id,
                    tier: $tier,
                    scope: 'dashboard',
                    rawIp: $rawIp,
                    rawMessage: $rawMessage,
                    cacheHit: true,
                    status: 'ok',
                    model: config('ai.model'),
                    totalTokens: $totalTokens,
                    latencyMs: $latencyMs
                );

                $cachedResponse = array_merge($cached, ['cached' => true]);

                return response()->json([
                    'success' => true,
                    'data' => $cachedResponse,
                ]);
            }

            // 2. Check daily quota (cache miss)
            $this->aiDailyQuota->check($tier, $identifier);

            // 3. Call AI service
            $result = $this->aiChatService->chatDashboard(
                user: $user,
                message: $rawMessage,
                history: $history
            );

            // 4. Atomically consume daily quota ONLY after successful AI response
            $this->aiDailyQuota->consume($tier, $identifier);

            // 5. Store in FAQ cache if eligible
            $this->responseCache->put('dashboard', $tier, $rawMessage, $history, $result);

            $latencyMs = $this->calculateLatency($startTime);
            $totalTokens = $result['usage']['total_tokens'] ?? null;
            $this->usageLogger->log(
                userId: $user->id,
                tier: $tier,
                scope: 'dashboard',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'ok',
                model: config('ai.model'),
                totalTokens: $totalTokens,
                latencyMs: $latencyMs
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (AiQuotaExceededException $e) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->usageLogger->log(
                userId: $user->id,
                tier: $tier,
                scope: 'dashboard',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'rate_limited',
                errorCode: 'daily_quota',
                latencyMs: $latencyMs
            );

            return $e->render($request);
        } catch (AiServiceException $e) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->usageLogger->log(
                userId: $user->id,
                tier: $tier,
                scope: 'dashboard',
                rawIp: $rawIp,
                rawMessage: $rawMessage,
                cacheHit: false,
                status: 'error',
                errorCode: $e->getCategory(),
                latencyMs: $latencyMs
            );

            return $this->handleAiException($e);
        } finally {
            $lock->release();
        }
    }

    /**
     * Admin-only usage statistics endpoint (30 days summary).
     */
    public function adminUsage(Request $request): JsonResponse
    {
        $startDate = Carbon::now('Asia/Jakarta')->subDays(30)->startOfDay();

        $dailyLogs = AiUsageLog::where('created_at', '>=', $startDate)
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total_requests,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                ROUND(AVG(latency_ms), 1) as avg_latency_ms,
                SUM(CASE WHEN status = 'rate_limited' THEN 1 ELSE 0 END) as rate_limited_count,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count
            ")
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_requests;
                $hits = (int) $row->cache_hits;

                return [
                    'date' => $row->date,
                    'total_requests' => $total,
                    'cache_hits' => $hits,
                    'cache_hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0.0,
                    'total_tokens' => (int) $row->total_tokens,
                    'avg_latency_ms' => (float) $row->avg_latency_ms,
                    'rate_limited_count' => (int) $row->rate_limited_count,
                    'error_count' => (int) $row->error_count,
                ];
            });

        $totalRequests = AiUsageLog::where('created_at', '>=', $startDate)->count();
        $cacheHits = AiUsageLog::where('created_at', '>=', $startDate)->where('cache_hit', true)->count();
        $totalTokens = (int) AiUsageLog::where('created_at', '>=', $startDate)->sum('total_tokens');
        $avgLatency = round((float) (AiUsageLog::where('created_at', '>=', $startDate)->avg('latency_ms') ?? 0), 1);
        $rateLimited = AiUsageLog::where('created_at', '>=', $startDate)->where('status', 'rate_limited')->count();

        $topQuestions = AiUsageLog::where('created_at', '>=', $startDate)
            ->select('question_hash', DB::raw('count(*) as count'))
            ->groupBy('question_hash')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'days' => 30,
                    'total_requests' => $totalRequests,
                    'cache_hits' => $cacheHits,
                    'cache_hit_rate' => $totalRequests > 0 ? round(($cacheHits / $totalRequests) * 100, 2) : 0.0,
                    'total_tokens' => $totalTokens,
                    'avg_latency_ms' => $avgLatency,
                    'rate_limited_count' => $rateLimited,
                ],
                'daily' => $dailyLogs,
                'top_questions' => $topQuestions,
            ],
        ]);
    }

    /**
     * Map AiServiceException to user-friendly Indonesian error response.
     */
    protected function handleAiException(AiServiceException $e): JsonResponse
    {
        $category = $e->getCategory();

        if ($category === AiServiceException::CATEGORY_MISCONFIGURED) {
            Log::critical('AI Assistant misconfigured: API key or model missing in configuration');

            return response()->json([
                'success' => false,
                'message' => 'Layanan asisten AI sedang tidak dapat digunakan.',
                'code' => 'AI_UNAVAILABLE',
            ], 503);
        }

        if ($category === AiServiceException::CATEGORY_UPSTREAM_RATE_LIMITED) {
            return response()->json([
                'success' => false,
                'message' => 'Asisten AI sedang sibuk karena banyaknya permintaan, coba lagi sebentar lagi.',
                'code' => 'AI_BUSY',
            ], 503, ['Retry-After' => '60']);
        }

        // timeout, upstream_error, invalid_response
        return response()->json([
            'success' => false,
            'message' => 'Asisten AI sedang sibuk, coba lagi sebentar lagi.',
            'code' => 'AI_BUSY',
        ], 503);
    }

    /**
     * Calculate elapsed latency in milliseconds.
     */
    protected function calculateLatency(int $startTimeHr): int
    {
        return (int) max(1, round((hrtime(true) - $startTimeHr) / 1e6));
    }
}
