<?php

namespace App\Repositories;

use App\Models\ExamAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ExamAttemptRepository
{
    /**
     * Get paginated exam attempts with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ExamAttempt::with(['student', 'subjectExam.subject'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['exam_id'])) {
            $query->where('subject_exam_id', $filters['exam_id']);
        }

        if (isset($filters['status'])) {
            $isCompleted = $filters['status'] === 'completed';
            $query->where('is_completed', $isCompleted);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('created_at', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all exam attempts without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        $query = ExamAttempt::with(['student', 'subjectExam.subject'])
            ->orderBy('created_at', 'desc');

        // Apply filters (same as paginated)
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['exam_id'])) {
            $query->where('subject_exam_id', $filters['exam_id']);
        }

        return $query->get();
    }

    /**
     * Find exam attempt by ID with relationships
     */
    public function findWithRelations(int $id): ExamAttempt
    {
        return ExamAttempt::with(['student', 'subjectExam.subject'])
            ->findOrFail($id);
    }

    /**
     * Find exam attempt by student and exam
     */
    public function findByStudentAndExam(int $studentId, int $examId): ?ExamAttempt
    {
        return ExamAttempt::where('student_id', $studentId)
            ->where('subject_exam_id', $examId)
            ->with(['student', 'subjectExam.subject'])
            ->first();
    }

    /**
     * Create a new exam attempt
     */
    public function create(array $data): ExamAttempt
    {
        return ExamAttempt::create($data);
    }

    /**
     * Update exam attempt by ID
     */
    public function update(int $id, array $data): ExamAttempt
    {
        $attempt = ExamAttempt::findOrFail($id);
        $attempt->update($data);
        return $attempt->fresh(['student', 'subjectExam.subject']);
    }

    /**
     * Delete exam attempt by ID
     */
    public function delete(int $id): bool
    {
        $attempt = ExamAttempt::findOrFail($id);
        return $attempt->delete();
    }

    /**
     * Get statistics for exam attempts
     */
    public function getStatistics(array $filters = []): array
    {
        $query = ExamAttempt::query();

        // Apply filters
        if (isset($filters['exam_id'])) {
            $query->where('subject_exam_id', $filters['exam_id']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('created_at', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        $stats = [
            'total_attempts' => $query->count(),
            'completed_attempts' => $query->where('is_completed', true)->count(),
            'in_progress_attempts' => $query->where('is_completed', false)->count(),
            'average_answers_per_attempt' => round($query->avg('answered_questions'), 2) ?: 0
        ];

        return $stats;
    }

    /**
     * Get completion stats by date (last 7 days)
     */
    public function getCompletionByDate(): Collection
    {
        return ExamAttempt::selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->where('is_completed', true)
            ->where('completed_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Find multiple exam attempts by IDs
     */
    public function findManyByIds(array $ids): Collection
    {
        return ExamAttempt::whereIn('id', $ids)
            ->with(['student', 'subjectExam.subject'])
            ->get();
    }
}