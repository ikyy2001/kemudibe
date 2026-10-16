<?php

namespace App\Services\Ai;

use App\Support\Ai\Prompts;
use Illuminate\Support\Facades\Cache;

class AiResponseCache
{
    /**
     * Normalize user message for exact-match caching.
     */
    public function normalize(string $text): string
    {
        // 1. Unicode normalization (if intl extension is present)
        if (function_exists('normalizer_normalize')) {
            $normalized = normalizer_normalize($text, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        // 2. Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // 3. Collapse multiple whitespace characters into a single space
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        // 4. Strip trailing punctuation and edge whitespace
        $text = trim($text);
        $text = (string) preg_replace('/[[:punct:]]+$/u', '', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Compute SHA-256 hash of normalized question.
     */
    public function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    /**
     * Generate structured cache key with prompt version, scope, variant, and hash.
     */
    public function getCacheKey(string $scope, string $variant, string $normalized): string
    {
        $version = Prompts::PROMPT_VERSION;
        $hash = $this->hash($normalized);

        return "ai:faq:{$version}:{$scope}:{$variant}:{$hash}";
    }

    /**
     * Retrieve cached response if available.
     * Returns null if history is not empty (context-dependent) or cache miss.
     *
     * @param  array<int, mixed>  $history
     * @return array{reply: string, usage?: array<string, mixed>}|null
     */
    public function get(string $scope, string $variant, string $message, array $history = []): ?array
    {
        // Only standalone opening queries without history are eligible for caching
        if (!empty($history)) {
            return null;
        }

        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return null;
        }

        $key = $this->getCacheKey($scope, $variant, $normalized);

        return Cache::get($key);
    }

    /**
     * Store successful AI response into cache.
     *
     * @param  array<int, mixed>  $history
     * @param  array{reply: string, usage?: array<string, mixed>}  $response
     */
    public function put(string $scope, string $variant, string $message, array $history, array $response): void
    {
        if (!empty($history)) {
            return;
        }

        $reply = trim((string) ($response['reply'] ?? ''));
        if ($reply === '') {
            return;
        }

        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return;
        }

        $ttl = (int) config('ai.cache_ttl', 86400);
        $key = $this->getCacheKey($scope, $variant, $normalized);

        Cache::put($key, $response, $ttl);
    }
}
