<?php

namespace App\Services\Ai;

use App\Exceptions\AiQuotaExceededException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AiDailyQuota
{
    /**
     * Get remaining daily quota for tier and identifier.
     * Returns null if tier is unlimited (e.g. admin).
     */
    public function remaining(string $tier, string|int $identifier): ?int
    {
        $limit = $this->getDailyLimit($tier);
        if ($limit === null) {
            return null;
        }

        $key = $this->getCacheKey($tier, $identifier);
        $used = (int) Cache::get($key, 0);

        return max(0, $limit - $used);
    }

    /**
     * Check if daily quota is available. Throws AiQuotaExceededException if exhausted.
     *
     * @throws AiQuotaExceededException
     */
    public function check(string $tier, string|int $identifier): void
    {
        $limit = $this->getDailyLimit($tier);
        if ($limit === null) {
            return;
        }

        $key = $this->getCacheKey($tier, $identifier);
        $used = (int) Cache::get($key, 0);

        if ($used >= $limit) {
            throw new AiQuotaExceededException(
                tier: $tier,
                identifier: $identifier,
                limit: $limit,
                used: $used,
                retryAfter: $this->getSecondsUntilReset()
            );
        }
    }

    /**
     * Atomically consume 1 query from the daily quota.
     * Safe from race conditions using Cache::add + Cache::increment.
     */
    public function consume(string $tier, string|int $identifier): int
    {
        $limit = $this->getDailyLimit($tier);
        if ($limit === null) {
            return 0;
        }

        $key = $this->getCacheKey($tier, $identifier);
        $ttl = $this->getSecondsUntilReset();

        // Atomically ensure key exists with TTL before incrementing
        Cache::add($key, 0, $ttl);

        return (int) Cache::increment($key);
    }

    /**
     * Get configured daily limit for a tier. Null means unlimited.
     */
    public function getDailyLimit(string $tier): ?int
    {
        $limits = config("ai.limits.{$tier}", []);
        $limit = $limits['per_day'] ?? null;

        return $limit !== null ? (int) $limit : null;
    }

    /**
     * Get configured per-minute limit for a tier.
     */
    public function getPerMinuteLimit(string $tier): int
    {
        $limits = config("ai.limits.{$tier}", []);

        return (int) ($limits['per_minute'] ?? 5);
    }

    /**
     * Current Carbon datetime in Asia/Jakarta timezone.
     */
    public function getNow(): Carbon
    {
        return Carbon::now('Asia/Jakarta');
    }

    /**
     * Midnight reset Carbon datetime for current day in Asia/Jakarta.
     */
    public function getResetsAt(): Carbon
    {
        return $this->getNow()->copy()->endOfDay();
    }

    /**
     * Calculate seconds remaining until midnight in Asia/Jakarta with buffer.
     */
    public function getSecondsUntilReset(): int
    {
        $now = $this->getNow();
        $resetsAt = $this->getResetsAt();

        return max(1, $now->diffInSeconds($resetsAt) + 60);
    }

    /**
     * Generate cache key for quota tracking.
     */
    public function getCacheKey(string $tier, string|int $identifier): string
    {
        $date = $this->getNow()->format('Y-m-d');

        return "ai:quota:{$tier}:{$identifier}:{$date}";
    }
}
