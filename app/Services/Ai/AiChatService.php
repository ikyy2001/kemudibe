<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\Ai\AiTierResolver;
use App\Support\Ai\Prompts;

class AiChatService
{
    public function __construct(
        protected NaraRouterClient $naraRouterClient
    ) {
    }

    /**
     * Handle public chat for Landing Page (scope: landing, tier: guest).
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, cached: bool, usage?: array{total_tokens: ?int}}
     */
    public function chatLanding(string $message, array $history = []): array
    {
        return $this->processChat(
            systemPrompt: Prompts::landing(),
            message: $message,
            history: $history
        );
    }

    /**
     * Handle authenticated chat for Dashboard (scope: dashboard, tier based on role).
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, cached: bool, usage?: array{total_tokens: ?int}}
     */
    public function chatDashboard(?User $user, string $message, array $history = []): array
    {
        $tier = AiTierResolver::resolve($user);
        $systemPrompt = Prompts::dashboard($tier, $user?->name);

        return $this->processChat(
            systemPrompt: $systemPrompt,
            message: $message,
            history: $history
        );
    }

    /**
     * Prepare context trimming (sliding window) and execute chat completion.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, cached: bool, usage?: array{total_tokens: ?int}}
     */
    protected function processChat(string $systemPrompt, string $message, array $history): array
    {
        // 1. Sliding window: take last AI_HISTORY_LIMIT pairs (each pair = 2 messages)
        $historyLimit = (int) config('ai.history_limit', 3);
        $maxHistoryItems = max(1, $historyLimit * 2);
        $slicedHistory = array_slice($history, -$maxHistoryItems);

        // 2. Trim each history item's content to max 600 characters
        $trimmedHistory = [];
        foreach ($slicedHistory as $item) {
            if (isset($item['role'], $item['content'])) {
                $trimmedHistory[] = [
                    'role' => $item['role'],
                    'content' => mb_substr(trim((string) $item['content']), 0, 600),
                ];
            }
        }

        // 3. Assemble messages: [system prompt] + [trimmed history] + [new user message]
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $trimmedHistory,
            [['role' => 'user', 'content' => trim($message)]]
        );

        // 4. Send chat completion request
        $response = $this->naraRouterClient->chat($messages, [
            'max_tokens' => (int) config('ai.max_tokens', 300),
        ]);

        $reply = $response->content;

        // Strict Guardrail: Mencegah bypass/jailbreak, kode pemrograman, dan jawaban kompromi di luar domain
        $isCompromised = preg_match('/<form|<input|<script|<select|<\/form>|```(html|javascript|php|python|sql|bash|sh|css)/i', $reply)
            || preg_match('/(tidak berkaitan|bukan bagian|di luar|bukan ranah|terlepas dari|meskipun)[\s\S]*?(namun|tetapi|tapi|sebagai bantuan|secara umum|secara ilmiah)/iu', $reply)
            || preg_match('/(sebagai bantuan awal|namun,?\s*sebagai bantuan)/iu', $reply)
            || preg_match('/(namun|tetapi|tapi)\s+(saya|kami)\s+(akan|dapat|bisa)\s+(bantu|jelaskan|berikan)/iu', $reply)
            || preg_match('/(namun|tetapi|tapi)\s+berikut\s+(penjelasan|jawaban|rumus|langkah|contoh)/iu', $reply)
            || preg_match('/(namun|tetapi|tapi)\s+secara\s+(singkat|umum|ilmiah)/iu', $reply)
            || str_starts_with($reply, 'Maaf, saya adalah Asisten AI Resmi Kemudi');

        if ($isCompromised) {
            $reply = Prompts::STANDARD_REJECTION;
        }

        // 5. Structure response payload
        $result = [
            'reply' => $reply,
            'cached' => false,
        ];

        if (config('ai.show_usage', true)) {
            $result['usage'] = [
                'total_tokens' => $response->totalTokens,
            ];
        }

        return $result;
    }
}
