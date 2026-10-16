<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiResponseCache;
use App\Support\Ai\Prompts;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiResponseCacheTest extends TestCase
{
    protected AiResponseCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->cache = app(AiResponseCache::class);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_normalization_rules(): void
    {
        // Lowercase, multi-spaces, trailing punctuation removal
        $input = "  BAGAIMANA   cara membuat   Ujian Baru???!!...   ";
        $expected = "bagaimana cara membuat ujian baru";

        $this->assertEquals($expected, $this->cache->normalize($input));
    }

    public function test_cache_put_and_get_standalone_message(): void
    {
        $scope = 'landing';
        $variant = 'guest';
        $message = 'Apa itu Kemudi?';
        $history = [];
        $response = [
            'reply' => 'Kemudi adalah platform CBT terpadu.',
            'cached' => false,
            'usage' => ['total_tokens' => 30],
        ];

        $this->assertNull($this->cache->get($scope, $variant, $message, $history));

        $this->cache->put($scope, $variant, $message, $history, $response);

        $cached = $this->cache->get($scope, $variant, $message, $history);
        $this->assertNotNull($cached);
        $this->assertEquals('Kemudi adalah platform CBT terpadu.', $cached['reply']);

        // Similar question with different casing and extra punctuation should hit the same cache
        $similarQuestion = "   apa itu kemudi?!??   ";
        $hit = $this->cache->get($scope, $variant, $similarQuestion, $history);
        $this->assertNotNull($hit);
        $this->assertEquals('Kemudi adalah platform CBT terpadu.', $hit['reply']);
    }

    public function test_conversation_with_history_is_not_cached(): void
    {
        $scope = 'landing';
        $variant = 'guest';
        $message = 'Lalu bagaimana dengan nilainya?';
        $history = [
            ['role' => 'user', 'content' => 'Halo'],
            ['role' => 'assistant', 'content' => 'Hai'],
        ];
        $response = ['reply' => 'Nilai otomatis terkoreksi.'];

        $this->cache->put($scope, $variant, $message, $history, $response);

        // Cannot put or get when history is not empty
        $this->assertNull($this->cache->get($scope, $variant, $message, $history));
    }

    public function test_cache_key_contains_prompt_version(): void
    {
        $normalized = 'bagaimana cara login';
        $key = $this->cache->getCacheKey('dashboard', 'student', $normalized);

        $this->assertStringStartsWith('ai:faq:' . Prompts::PROMPT_VERSION . ':dashboard:student:', $key);
    }
}
