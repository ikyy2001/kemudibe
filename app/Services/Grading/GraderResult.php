<?php

namespace App\Services\Grading;

class GraderResult
{
    public function __construct(
        public float $pointsEarned,
        public bool $hasPassed,
        public string $resultStatus, // 'correct', 'partial', 'wrong', 'pending'
        public ?string $answerText = null,
        public ?array $answerData = null
    ) {}

    public function toArray(): array
    {
        return [
            'points_earned' => $this->pointsEarned,
            'has_passed' => $this->hasPassed,
            'result_status' => $this->resultStatus,
            'answer_text' => $this->answerText,
            'answer_data' => $this->answerData,
        ];
    }
}
