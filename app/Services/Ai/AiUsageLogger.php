<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiUsageLogger
{
    public function __construct(
        protected AiResponseCache $responseCache
    ) {
    }

    /**
     * Safely log an AI interaction to the database.
     * Raw IP and raw question text are never stored; only one-way cryptographic hashes are recorded.
     */
    public function log(
        ?int $userId,
        string $tier,
        string $scope,
        string $rawIp,
        string $rawMessage,
        bool $cacheHit,
        string $status, // 'ok', 'error', 'rate_limited'
        ?string $errorCode = null,
        ?string $model = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $totalTokens = null,
        int $latencyMs = 0
    ): void {
        $salt = (string) config('app.key', 'kemudi-salt');
        $ipHash = hash_hmac('sha256', $rawIp !== '' ? $rawIp : '127.0.0.1', $salt);

        $normalized = $this->responseCache->normalize($rawMessage);
        $questionHash = $this->responseCache->hash($normalized);

        $payload = [
            'user_id' => $userId,
            'tier' => $tier,
            'scope' => $scope,
            'cache_hit' => $cacheHit,
            'status' => $status,
            'error_code' => $errorCode,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'latency_ms' => $latencyMs,
            'ip_hash' => $ipHash,
            'question_hash' => $questionHash,
            'created_at' => now(),
        ];

        if (app()->runningUnitTests() || app()->runningInConsole()) {
            AiUsageLog::create($payload);
        } else {
            dispatch(function () use ($payload) {
                try {
                    AiUsageLog::create($payload);
                } catch (Throwable $e) {
                    Log::error('Failed to persist AI usage log: ' . $e->getMessage());
                }
            })->afterResponse();
        }
    }
}
