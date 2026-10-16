<?php

namespace Tests\Unit\Services\Ai;

use App\DTOs\Ai\AiResponse;
use App\Models\User;
use App\Services\Ai\AiChatService;
use App\Services\Ai\NaraRouterClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiChatServiceTest extends TestCase
{
    public function test_process_chat_trims_history_to_sliding_window_limit_and_truncates_length(): void
    {
        Config::set('ai.history_limit', 3); // 3 pairs = max 6 messages
        Config::set('ai.max_tokens', 300);
        Config::set('ai.show_usage', true);

        $mockClient = $this->mock(NaraRouterClient::class);

        // We will pass 8 history items (4 pairs). The service should take ONLY the last 6 items (3 pairs),
        // and truncate item 7's content (> 600 chars) to exactly 600 chars.
        $longContent = str_repeat('A', 800);

        $history = [
            ['role' => 'user', 'content' => 'Old message 1 (should be dropped)'],
            ['role' => 'assistant', 'content' => 'Old reply 1 (should be dropped)'],
            ['role' => 'user', 'content' => 'Message 2'],
            ['role' => 'assistant', 'content' => 'Reply 2'],
            ['role' => 'user', 'content' => 'Message 3'],
            ['role' => 'assistant', 'content' => 'Reply 3'],
            ['role' => 'user', 'content' => $longContent],
            ['role' => 'assistant', 'content' => 'Reply 4'],
        ];

        $mockClient->shouldReceive('chat')
            ->once()
            ->withArgs(function (array $messages, array $options) use ($longContent) {
                // 1 system + 6 trimmed history + 1 new user message = 8 messages sent to client
                if (count($messages) !== 8) {
                    return false;
                }

                // Check system prompt is first
                if ($messages[0]['role'] !== 'system') {
                    return false;
                }

                // Check that oldest 2 messages were dropped (sliding window)
                if ($messages[1]['content'] !== 'Message 2') {
                    return false;
                }

                // Check that the long content was truncated to 600 characters
                if ($messages[4]['content'] !== 'Reply 3') {
                    return false;
                }
                if (mb_strlen($messages[5]['content']) !== 600) {
                    return false;
                }
                if ($messages[6]['content'] !== 'Reply 4') {
                    return false;
                }

                // Check new user message is last
                if ($messages[7]['content'] !== 'Pertanyaan terkini') {
                    return false;
                }

                return $options['max_tokens'] === 300;
            })
            ->andReturn(new AiResponse(
                content: 'Jawaban sukses',
                promptTokens: 100,
                completionTokens: 50,
                totalTokens: 150,
                model: 'openai/gpt-4o-mini',
                latencyMs: 120
            ));

        $service = app(AiChatService::class);
        $result = $service->chatLanding('Pertanyaan terkini', $history);

        $this->assertEquals('Jawaban sukses', $result['reply']);
        $this->assertFalse($result['cached']);
        $this->assertEquals(150, $result['usage']['total_tokens']);
    }

    public function test_chat_dashboard_injects_user_role_context_into_prompt(): void
    {
        Config::set('ai.history_limit', 3);
        Config::set('ai.max_tokens', 300);

        $mockClient = $this->mock(NaraRouterClient::class);

        $user = new User();
        $user->name = 'Budi';
        $user->role = 'teacher';

        $mockClient->shouldReceive('chat')
            ->once()
            ->withArgs(function (array $messages) {
                $systemPrompt = $messages[0]['content'];
                // Should contain Guru / teacher context and user name Budi
                return str_contains($systemPrompt, 'Guru') && str_contains($systemPrompt, 'Budi');
            })
            ->andReturn(new AiResponse('Bantuan untuk guru', 50, 20, 70, 'openai/gpt-4o-mini', 80));

        $service = app(AiChatService::class);
        $result = $service->chatDashboard($user, 'Bagaimana cara buat ujian?');

        $this->assertEquals('Bantuan untuk guru', $result['reply']);
    }
}
