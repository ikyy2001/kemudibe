<?php

namespace App\DTOs\Ai;

readonly class AiResponse
{
    public function __construct(
        public string $content,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public ?int $totalTokens,
        public string $model,
        public int $latencyMs,
    ) {
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'model' => $this->model,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
