<?php

namespace Tests\Unit\Services\Ai;

use App\DTOs\Ai\AiResponse;
use App\Exceptions\AiServiceException;
use App\Services\Ai\NaraRouterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NaraRouterClientTest extends TestCase
{
    protected NaraRouterClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.base_url', 'https://router.bynara.id/v1');
        Config::set('ai.api_key', 'test-api-key');
        Config::set('ai.model', 'openai/gpt-4o-mini');
        Config::set('ai.max_tokens', 300);
        Config::set('ai.temperature', 0.3);
        Config::set('ai.timeout', 30);

        $this->client = app(NaraRouterClient::class);
    }

    public function test_singleton_binding(): void
    {
        $instance1 = app(NaraRouterClient::class);
        $instance2 = app(NaraRouterClient::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_chat_success_with_content_and_usage(): void
    {
        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'model' => 'openai/gpt-4o-mini',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Halo! Ada yang bisa saya bantu seputar LMS?',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 15,
                    'total_tokens' => 35,
                ],
            ], 200),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Halo AI'],
        ];

        $response = $this->client->chat($messages);

        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertEquals('Halo! Ada yang bisa saya bantu seputar LMS?', $response->content);
        $this->assertEquals(20, $response->promptTokens);
        $this->assertEquals(15, $response->completionTokens);
        $this->assertEquals(35, $response->totalTokens);
        $this->assertEquals('openai/gpt-4o-mini', $response->model);
        $this->assertGreaterThanOrEqual(1, $response->latencyMs);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://router.bynara.id/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request['model'] === 'openai/gpt-4o-mini'
                && $request['messages'] === [['role' => 'user', 'content' => 'Halo AI']]
                && $request['max_tokens'] === 300
                && $request['stream'] === false;
        });
    }

    public function test_chat_success_without_usage_fills_null(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Respons tanpa field usage',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->client->chat([['role' => 'user', 'content' => 'Tes']]);

        $this->assertEquals('Respons tanpa field usage', $response->content);
        $this->assertNull($response->promptTokens);
        $this->assertNull($response->completionTokens);
        $this->assertNull($response->totalTokens);
    }

    public function test_chat_timeout_throws_ai_service_exception(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds'),
        ]);

        try {
            $this->client->chat([['role' => 'user', 'content' => 'Tes timeout']]);
            $this->fail('Expected AiServiceException was not thrown.');
        } catch (AiServiceException $e) {
            $this->assertEquals(AiServiceException::CATEGORY_TIMEOUT, $e->getCategory());
            $this->assertStringNotContainsString('test-api-key', $e->getMessage());
        }
    }

    public function test_chat_upstream_429_throws_upstream_rate_limited(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached',
                    'type' => 'rate_limit_exceeded',
                ],
            ], 429),
        ]);

        try {
            $this->client->chat([['role' => 'user', 'content' => 'Tes 429']]);
            $this->fail('Expected AiServiceException was not thrown.');
        } catch (AiServiceException $e) {
            $this->assertEquals(AiServiceException::CATEGORY_UPSTREAM_RATE_LIMITED, $e->getCategory());
            $this->assertEquals(429, $e->getStatusCode());
            $this->assertStringNotContainsString('test-api-key', $e->getMessage());
        }
    }

    public function test_chat_upstream_500_throws_upstream_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => 'Internal Server Error',
            ], 500),
        ]);

        try {
            $this->client->chat([['role' => 'user', 'content' => 'Tes 500']]);
            $this->fail('Expected AiServiceException was not thrown.');
        } catch (AiServiceException $e) {
            $this->assertEquals(AiServiceException::CATEGORY_UPSTREAM_ERROR, $e->getCategory());
            $this->assertEquals(500, $e->getStatusCode());
            $this->assertStringNotContainsString('test-api-key', $e->getMessage());
        }
    }

    public function test_chat_invalid_response_format_throws_invalid_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'invalid' => 'no choices array',
            ], 200),
        ]);

        try {
            $this->client->chat([['role' => 'user', 'content' => 'Tes invalid format']]);
            $this->fail('Expected AiServiceException was not thrown.');
        } catch (AiServiceException $e) {
            $this->assertEquals(AiServiceException::CATEGORY_INVALID_RESPONSE, $e->getCategory());
            $this->assertEquals(200, $e->getStatusCode());
        }
    }

    public function test_chat_empty_api_key_throws_misconfigured(): void
    {
        Config::set('ai.api_key', '');

        try {
            $this->client->chat([['role' => 'user', 'content' => 'Tes misconfigured api key']]);
            $this->fail('Expected AiServiceException was not thrown.');
        } catch (AiServiceException $e) {
            $this->assertEquals(AiServiceException::CATEGORY_MISCONFIGURED, $e->getCategory());
        }
    }

    public function test_chat_empty_model_throws_misconfigured(): void
    {
        Config::set('ai.model', '');

        try {
            $this->client->chat([['role' => 'user', 'content' => 'Tes misconfigured model']]);
            $this->fail('Expected AiServiceException was not thrown.');
        } catch (AiServiceException $e) {
            $this->assertEquals(AiServiceException::CATEGORY_MISCONFIGURED, $e->getCategory());
        }
    }

    public function test_chat_max_tokens_clamped_to_config_limit(): void
    {
        Config::set('ai.max_tokens', 250);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ], 200),
        ]);

        // Requesting 500 tokens when config max is 250 -> should be clamped to 250
        $this->client->chat([['role' => 'user', 'content' => 'Tes clamp']], [
            'max_tokens' => 500,
        ]);

        Http::assertSent(function (Request $request) {
            return $request['max_tokens'] === 250;
        });

        // Requesting 100 tokens when config max is 250 -> should be 100
        $this->client->chat([['role' => 'user', 'content' => 'Tes lower']], [
            'max_tokens' => 100,
        ]);

        Http::assertSent(function (Request $request) {
            return $request['max_tokens'] === 100;
        });
    }
}
