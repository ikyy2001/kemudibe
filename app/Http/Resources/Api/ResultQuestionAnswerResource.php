<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultQuestionAnswerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_question_id' => $this->exam_question_id,
            'student_id' => $this->student_id,
            'answer_text' => $this->answer_text,
            'has_passed' => $this->has_passed,
            'points_earned' => $this->points_earned,
            'feedback' => $this->feedback,
            'score_percentage' => $this->whenLoaded('examQuestion', function () {
                $maxPoints = $this->examQuestion->points ?? 0;
                return $maxPoints > 0 ? round(($this->points_earned / $maxPoints) * 100, 2) : 0;
            }),
            'exam_question' => new ResultExamQuestionResource($this->whenLoaded('examQuestion')),
            'student' => new UserResource($this->whenLoaded('student')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}