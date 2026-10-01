<?php

namespace App\Repositories;

use App\Models\QuestionAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QuestionAnswerRepository
{
    /**
     * Get paginated question answers with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = QuestionAnswer::with(['student', 'examQuestion'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['exam_id'])) {
            $query->whereHas('examQuestion', function($q) use ($filters) {
                $q->where('subject_exam_id', $filters['exam_id']);
            });
        }

        if (isset($filters['question_id'])) {
            $query->where('exam_question_id', $filters['question_id']);
        }

        if (isset($filters['has_passed'])) {
            $query->where('has_passed', $filters['has_passed']);
        }

        if (isset($filters['min_points'])) {
            $query->where('points_earned', '>=', $filters['min_points']);
        }

        if (isset($filters['max_points'])) {
            $query->where('points_earned', '<=', $filters['max_points']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all question answers without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        $query = QuestionAnswer::with(['student', 'examQuestion'])
            ->orderBy('created_at', 'desc');

        // Apply filters (same as paginated)
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['exam_id'])) {
            $query->whereHas('examQuestion', function($q) use ($filters) {
                $q->where('subject_exam_id', $filters['exam_id']);
            });
        }

        return $query->get();
    }

    /**
     * Find question answer by ID with relationships
     */
    public function findWithRelations(int $id): QuestionAnswer
    {
        return QuestionAnswer::with(['student', 'examQuestion.questionOptions'])
            ->findOrFail($id);
    }

    /**
     * Find answer by question and student
     */
    public function findByQuestionAndStudent(int $questionId, int $studentId): ?QuestionAnswer
    {
        return QuestionAnswer::where('exam_question_id', $questionId)
            ->where('student_id', $studentId)
            ->first();
    }

    /**
     * Create a new question answer
     */
    public function create(array $data): QuestionAnswer
    {
        return QuestionAnswer::create($data);
    }

    /**
     * Update question answer by ID
     */
    public function update(int $id, array $data): QuestionAnswer
    {
        $answer = QuestionAnswer::findOrFail($id);
        $answer->update($data);
        return $answer->fresh(['student', 'examQuestion']);
    }

    /**
     * Delete question answer by ID
     */
    public function delete(int $id): bool
    {
        $answer = QuestionAnswer::findOrFail($id);
        return $answer->delete();
    }

    /**
     * Get answers needing grading
     */
    public function needsGrading(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = QuestionAnswer::with(['student', 'examQuestion.subjectExam'])
            ->whereHas('examQuestion', function($q) {
                $q->where('type', 'essay');
            })
            ->where('points_earned', 0)
            ->orderBy('created_at', 'asc');

        // Apply filters
        if (isset($filters['exam_id'])) {
            $query->whereHas('examQuestion', function($q) use ($filters) {
                $q->where('subject_exam_id', $filters['exam_id']);
            });
        }

        if (isset($filters['teacher_id'])) {
            $query->whereHas('examQuestion.subjectExam.subject', function($q) use ($filters) {
                $q->where('teacher_id', $filters['teacher_id']);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get student answers for specific exam
     */
    public function getStudentAnswersForExam(int $studentId, int $examId): Collection
    {
        return QuestionAnswer::whereHas('examQuestion', function($query) use ($examId) {
            $query->where('subject_exam_id', $examId);
        })->where('student_id', $studentId)
          ->with(['examQuestion.questionOptions'])
          ->orderBy('exam_question_id')
          ->get();
    }

    /**
     * Count student answers for specific exam
     */
    public function countStudentAnswersForExam(int $studentId, int $examId): int
    {
        return QuestionAnswer::whereHas('examQuestion', function($query) use ($examId) {
            $query->where('subject_exam_id', $examId);
        })->where('student_id', $studentId)->count();
    }

    /**
     * Find multiple answers by IDs
     */
    public function findManyByIds(array $ids): Collection
    {
        return QuestionAnswer::whereIn('id', $ids)
            ->with(['student', 'examQuestion'])
            ->get();
    }

}