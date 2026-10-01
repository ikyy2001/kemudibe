<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'photo' => $this->photo,
            'gender' => $this->gender,
            'class_students_count' => $this->whenCounted('classStudents'),
            'question_answers_count' => $this->whenCounted('questionAnswers'),
            'exam_attempts_count' => $this->whenCounted('examAttempts'),
            'completed_exams_count' => $this->whenCounted('completedExamsCount'),
            'class_students' => ClassStudentResource::collection($this->whenLoaded('classStudents')),
            'classrooms_summary' => $this->when($this->relationLoaded('classStudents') && $this->classStudents->first()?->classRoom?->class_students_count !== null, function () {
                return $this->classStudents->map(function ($enrollment) {
                    $classroom = $enrollment->classRoom;
                    return [
                        'classroom_id' => $classroom->id,
                        'classroom_name' => $classroom->name,
                        'classroom_grade' => $classroom->grade,
                        'total_students' => $classroom->class_students_count,
                        'total_subjects' => $classroom->class_subjects_count,
                    ];
                });
            }),
            'exam_statistics' => $this->when(isset($this->total_exams_available), function () {
                return [
                    'total_exams_available' => $this->total_exams_available,
                    'total_exams_completed' => $this->total_exams_completed,
                ];
            }),
            'exam_attempts' => ExamAttemptResource::collection($this->whenLoaded('examAttempts')),
            'question_answers' => QuestionAnswerResource::collection($this->whenLoaded('questionAnswers')),
            'roles' => $this->when($this->relationLoaded('roles'), function () {
                return $this->roles->pluck('name');
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
