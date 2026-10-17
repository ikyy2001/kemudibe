<?php

namespace App\Providers;

use App\Support\Ai\AiTierResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\InstitutionContext::class);
        $this->app->singleton(\App\Services\Ai\NaraRouterClient::class);
        $this->app->singleton(\App\Services\Ai\AiChatService::class);
        $this->app->singleton(\App\Services\Ai\AiDailyQuota::class);
        $this->app->singleton(\App\Services\Ai\AiResponseCache::class);
        $this->app->singleton(\App\Services\Ai\AiUsageLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Models\SubjectExam::observe(\App\Observers\SubjectExamObserver::class);

        $this->configureAiRateLimiters();
    }

    /**
     * Configure named rate limiters for AI chat and quota queries.
     */
    protected function configureAiRateLimiters(): void
    {
        RateLimiter::for('ai-chat', function (Request $request) {
            $user = $request->user();
            $tier = AiTierResolver::resolve($user);
            $limits = config('ai.limits', []);
            $perMinute = (int) ($limits[$tier]['per_minute'] ?? 5);

            if ($user) {
                $key = "ai:user:{$user->id}";
            } else {
                $rawIp = (string) ($request->ip() ?: '127.0.0.1');
                $rawDeviceId = (string) $request->header('X-Guest-Device-Id', '');
                $sanitizedDeviceId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $rawDeviceId);
                $key = (!empty($sanitizedDeviceId) && strlen($sanitizedDeviceId) >= 8 && strlen($sanitizedDeviceId) <= 64)
                    ? "ai:guest:{$rawIp}:{$sanitizedDeviceId}"
                    : "ai:ip:{$rawIp}";
            }

            return Limit::perMinute($perMinute)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? 60);

                    return response()->json([
                        'success' => false,
                        'message' => "Terlalu cepat. Silakan tunggu {$retryAfter} detik sebelum bertanya lagi.",
                        'code' => 'rate_limit_minute',
                        'retry_after' => $retryAfter,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('ai-quota', function (Request $request) {
            $user = $request->user();
            if ($user) {
                $key = "ai_quota:user:{$user->id}";
            } else {
                $rawIp = (string) ($request->ip() ?: '127.0.0.1');
                $rawDeviceId = (string) $request->header('X-Guest-Device-Id', '');
                $sanitizedDeviceId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $rawDeviceId);
                $key = (!empty($sanitizedDeviceId) && strlen($sanitizedDeviceId) >= 8 && strlen($sanitizedDeviceId) <= 64)
                    ? "ai_quota:guest:{$rawIp}:{$sanitizedDeviceId}"
                    : "ai_quota:ip:{$rawIp}";
            }

            return Limit::perMinute(60)->by($key);
        });
    }
}
