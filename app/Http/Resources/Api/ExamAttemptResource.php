<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
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
            'student_id' => $this->student_id,
            'subject_exam_id' => $this->subject_exam_id,
            'is_completed' => $this->is_completed,
            'total_questions' => $this->total_questions,
            'answered_questions' => $this->answered_questions,
            'total_points' => $this->total_points,
            'points_earned' => $this->points_earned,
            'has_passed' => $this->has_passed,
            'score_percentage' => $this->score_percentage,
            'completion_percentage' => $this->total_questions > 0 
                ? round(($this->answered_questions / $this->total_questions) * 100, 2) 
                : 0,
            'current_exam_device_token' => $this->current_exam_device_token,
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'violation_count' => $this->violation_count ?? 0,
            'max_violations' => $this->max_violations ?? 3,
            'violation_score' => (float)($this->violation_score ?? 0.0),
            'max_violation_score' => (float)($this->max_violation_score ?? 3.0),
            'is_frozen' => (bool)$this->is_frozen,
            'frozen_at' => $this->frozen_at?->toISOString(),
            'freeze_count' => (int)($this->freeze_count ?? 0),
            'offline_gaps_count' => (int)($this->offline_gaps_count ?? 0),
            'total_offline_seconds' => (int)($this->total_offline_seconds ?? 0),
            'forced_reason' => $this->forced_reason,
            'violations' => $this->relationLoaded('violations')
                ? $this->violations->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'violation_type' => $v->violation_type,
                        'weight' => (float)($v->weight ?? 1.0),
                        'duration_seconds' => (int)($v->duration_seconds ?? 0),
                        'is_offline_gap' => (bool)($v->is_offline_gap ?? false),
                        'details' => $v->details,
                        'occurred_at' => $v->occurred_at?->toISOString() ?? (string)$v->occurred_at,
                    ];
                })
                : [],
            'completed_at' => $this->completed_at?->toISOString(),
            'student' => new UserResource($this->whenLoaded('student')),
            'subject_exam' => new SubjectExamResource($this->whenLoaded('subjectExam')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}