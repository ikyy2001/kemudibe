<?php

namespace App\Services;

use App\Repositories\ExamAttemptRepository;
use App\Repositories\SubjectExamRepository;
use App\Repositories\QuestionAnswerRepository;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ExamAttemptService
{
    private ExamAttemptRepository $examAttemptRepository;
    private SubjectExamRepository $subjectExamRepository;
    private QuestionAnswerRepository $questionAnswerRepository;

    public function __construct(
        ExamAttemptRepository $examAttemptRepository,
        SubjectExamRepository $subjectExamRepository,
        QuestionAnswerRepository $questionAnswerRepository
    ) {
        $this->examAttemptRepository = $examAttemptRepository;
        $this->subjectExamRepository = $subjectExamRepository;
        $this->questionAnswerRepository = $questionAnswerRepository;
    }

    /**
     * Get paginated exam attempts
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->examAttemptRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get all exam attempts without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->examAttemptRepository->getAll($filters);
    }

    /**
     * Find exam attempt by ID
     */
    public function findExamAttempt(int $id): ExamAttempt
    {
        return $this->examAttemptRepository->findWithRelations($id);
    }

    /**
     * Create a new exam attempt
     */
    public function createExamAttempt(array $data): ExamAttempt
    {
        return DB::transaction(function () use ($data) {
            // Check if exam exists and is valid
            $exam = $this->subjectExamRepository->findWithQuestions($data['subject_exam_id']);

            // Check if student already has an attempt for this exam
            $existing = $this->examAttemptRepository->findByStudentAndExam(
                $data['student_id'],
                $data['subject_exam_id']
            );

            if ($existing) {
                throw new \Exception('Student already has an attempt for this exam');
            }

            // Add total questions count and copy total points from exam
            $data['total_questions'] = $exam->examQuestions->count();
            $data['total_points'] = $exam->total_points;

            return $this->examAttemptRepository->create($data);
        });
    }

    /**
     * Update exam attempt by ID
     */
    public function updateExamAttempt(int $id, array $data): ExamAttempt
    {
        return DB::transaction(function () use ($id, $data) {
            $attempt = $this->examAttemptRepository->findWithRelations($id);

            // Prevent updating completed attempts
            if ($attempt->is_completed && isset($data['is_completed']) && !$data['is_completed']) {
                throw new \Exception('Cannot reopen a completed exam attempt');
            }

            return $this->examAttemptRepository->update($id, $data);
        });
    }

    /**
     * Delete exam attempt by ID
     */
    public function deleteExamAttempt(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $attempt = $this->examAttemptRepository->findWithRelations($id);

            // Delete associated question answers first
            $answers = $this->questionAnswerRepository->getAll([
                'student_id' => $attempt->student_id,
                'exam_id' => $attempt->subject_exam_id
            ]);
            foreach ($answers as $answer) {
                $this->questionAnswerRepository->delete($answer->id);
            }

            return $this->examAttemptRepository->delete($id);
        });
    }

    /**
     * Get statistics for exam attempts
     */
    public function getStatistics(array $filters = []): array
    {
        $stats = $this->examAttemptRepository->getStatistics($filters);

        if ($stats['total_attempts'] > 0) {
            $stats['average_completion_rate'] = round(
                ($stats['completed_attempts'] / $stats['total_attempts']) * 100, 2
            );
        } else {
            $stats['average_completion_rate'] = 0;
        }

        // Get completion stats by date (last 7 days)
        $stats['completion_by_date'] = $this->examAttemptRepository->getCompletionByDate();

        return $stats;
    }
    /**
     * Get student's exam attempt for specific exam
     */
    public function getStudentAttempt(int $studentId, int $examId): array
    {
        $attempt = $this->examAttemptRepository->findByStudentAndExam($studentId, $examId);

        if (!$attempt) {
            throw new \Exception('No exam attempt found for this student and exam');
        }

        // Get associated answers
        $answers = $this->questionAnswerRepository->getAll([
            'student_id' => $studentId,
            'exam_id' => $examId
        ]);

        return [
            'attempt' => $attempt,
            'answers' => $answers,
            'progress' => [
                'answered_questions' => $answers->count(),
                'total_questions' => $attempt->total_questions,
                'percentage_complete' => $attempt->total_questions > 0 ?
                    round(($answers->count() / $attempt->total_questions) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Complete exam attempt and calculate final scores
     */
    public function completeExamAttempt(int $attemptId, float $passingPercentage = 60.0): ExamAttempt
    {
        return DB::transaction(function () use ($attemptId, $passingPercentage) {
            $attempt = $this->examAttemptRepository->findWithRelations($attemptId);

            if ($attempt->is_completed) {
                throw new \Exception('Exam attempt is already completed');
            }

            // Calculate points earned from question answers
            $answers = $this->questionAnswerRepository->getAll([
                'student_id' => $attempt->student_id,
                'exam_id' => $attempt->subject_exam_id
            ]);
            $pointsEarned = $answers->sum('points_earned');

            // Update answered questions count
            $answeredCount = $answers->count();

            // Calculate if passed
            $scorePercentage = $attempt->total_points > 0 ?
                ($pointsEarned / $attempt->total_points) * 100 : 0;
            $hasPassed = $scorePercentage >= $passingPercentage;

            // Update attempt with final scores
            return $this->examAttemptRepository->update($attemptId, [
                'is_completed' => true,
                'answered_questions' => $answeredCount,
                'points_earned' => $pointsEarned,
                'has_passed' => $hasPassed,
                'completed_at' => now()
            ]);
        });
    }

    /**
     * Delete multiple exam attempts
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->deleteExamAttempt($id);
            }
            return true;
        });
    }
}
