<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Check if examAttempts is loaded and get them
        $attempts = $this->relationLoaded('examAttempts') ? $this->examAttempts : collect();

        // Use withCount results when available, fallback to relationship counting
        $attemptsCount = $this->whenCounted('examAttempts') ?? $attempts->count();
        $completedCount = $this->completed_attempts_count ?? $attempts->where('is_completed', true)->count();
        $inProgressCount = $this->in_progress_attempts_count ?? $attempts->where('is_completed', false)->count();

        // Get student-specific attempt data if available
        $studentAttempt = $this->student_attempt ?? null;
        $studentStatus = 'not_started';
        $hasPassedExam = false;
        $attemptId = null;
        $completedAt = null;

        if ($studentAttempt) {
            if ($studentAttempt->is_completed) {
                $studentStatus = 'completed';
                $hasPassedExam = $studentAttempt->has_passed;
                $completedAt = $studentAttempt->completed_at?->toISOString();
            } else {
                $studentStatus = 'in_progress';
            }
            $attemptId = $studentAttempt->id;
        }

        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'topic_id' => $this->topic_id,
            'name' => $this->name,
            'about' => $this->about,
            'duration_minutes' => $this->duration_minutes ?? 60,
            'passing_grade' => $this->passing_grade ?? ($this->relationLoaded('subject') ? $this->subject?->passing_grade : null) ?? 75,
            'category' => $this->category ?? 'daily_quiz',
            'token' => $this->token,
            'has_token' => !empty($this->token),
            'token_expires_at' => $this->token_expires_at?->toISOString(),
            'is_token_expired' => $this->token_expires_at ? now()->gt($this->token_expires_at) : false,
            'supervisor_pin' => app(\App\Services\StudentExamService::class)->getSupervisorPin($this->id)['pin'] ?? null,
            'supervisor_pin_expires_in' => app(\App\Services\StudentExamService::class)->getSupervisorPin($this->id)['seconds_remaining'] ?? 60,
            'total_points' => $this->total_points,
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'topic' => new TopicResource($this->whenLoaded('topic')),
            'exam_questions' => ExamQuestionResource::collection($this->whenLoaded('examQuestions')),
            'attempts' => ExamAttemptResource::collection($attempts),
            'exam_questions_count' => $this->whenCounted('examQuestions'),
            'attempts_count' => $attemptsCount,
            'completed_count' => $completedCount,
            'in_progress_count' => $inProgressCount,
            'student_status' => $studentStatus,
            'has_passed' => $hasPassedExam,
            'attempt_id' => $attemptId,
            'completed_at' => $completedAt,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}