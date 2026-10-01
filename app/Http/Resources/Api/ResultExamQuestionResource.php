<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultExamQuestionResource extends JsonResource
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
            'subject_exam_id' => $this->subject_exam_id,
            'name' => $this->name,
            'timer' => $this->timer,
            'type' => $this->type,
            'points' => $this->points,
            'question_options' => ResultQuestionOptionResource::collection($this->whenLoaded('questionOptions')),
            'question_answers_count' => $this->whenCounted('questionAnswers'),
            'subject_exam' => new SubjectExamResource($this->whenLoaded('subjectExam')),
            'question_answers' => QuestionAnswerResource::collection($this->whenLoaded('questionAnswers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}