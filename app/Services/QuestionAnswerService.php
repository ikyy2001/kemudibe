<?php

namespace App\Services;

use App\Repositories\QuestionAnswerRepository;
use App\Repositories\ExamAttemptRepository;
use App\Repositories\ExamQuestionRepository;
use App\Models\QuestionAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QuestionAnswerService
{
    private QuestionAnswerRepository $questionAnswerRepository;
    private ExamAttemptRepository $examAttemptRepository;
    private ExamQuestionRepository $examQuestionRepository;

    public function __construct(
        QuestionAnswerRepository $questionAnswerRepository,
        ExamAttemptRepository $examAttemptRepository,
        ExamQuestionRepository $examQuestionRepository
    ) {
        $this->questionAnswerRepository = $questionAnswerRepository;
        $this->examAttemptRepository = $examAttemptRepository;
        $this->examQuestionRepository = $examQuestionRepository;
    }

    /**
     * Get paginated question answers
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->questionAnswerRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get all question answers without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->questionAnswerRepository->getAll($filters);
    }

    /**
     * Find question answer by ID
     */
    public function findQuestionAnswer(int $id): QuestionAnswer
    {
        return $this->questionAnswerRepository->findWithRelations($id);
    }

    /**
     * Create a new question answer
     */
    public function createQuestionAnswer(array $data): QuestionAnswer
    {
        return DB::transaction(function () use ($data) {
            $question = $this->examQuestionRepository->findWithRelations($data['exam_question_id']);

            // Check if answer already exists
            $existing = $this->questionAnswerRepository->findByQuestionAndStudent(
                $data['exam_question_id'],
                $data['student_id']
            );

            if ($existing) {
                throw new \Exception('Answer already exists for this question. Use update instead.');
            }

            // Auto-grade if multiple choice
            if ($question->type === 'multiple_choice') {
                $correctOption = $question->questionOptions->where('is_correct', true)->first();
                if ($correctOption && trim($data['answer_text']) === trim($correctOption->name)) {
                    $data['has_passed'] = true;
                    $data['points_earned'] = $question->points;
                } else {
                    $data['has_passed'] = false;
                    $data['points_earned'] = 0;
                }
            } else {
                $data['has_passed'] = false;
                $data['points_earned'] = 0;
            }

            $answer = $this->questionAnswerRepository->create($data);

            // Update exam attempt progress
            $this->updateAttemptProgress($data['student_id'], $question->subject_exam_id);

            return $answer;
        });
    }

    /**
     * Update question answer by ID
     */
    public function updateQuestionAnswer(int $id, array $data): QuestionAnswer
    {
        return DB::transaction(function () use ($id, $data) {
            $answer = $this->questionAnswerRepository->findWithRelations($id);
            $question = $answer->examQuestion;

            // Auto-grade if multiple choice
            if ($question->type === 'multiple_choice') {
                $correctOption = $question->questionOptions->where('is_correct', true)->first();
                if ($correctOption && trim($data['answer_text']) === trim($correctOption->name)) {
                    $data['has_passed'] = true;
                    $data['points_earned'] = $question->points;
                } else {
                    $data['has_passed'] = false;
                    $data['points_earned'] = 0;
                }
            }

            return $this->questionAnswerRepository->update($id, $data);
        });
    }

    /**
     * Delete question answer by ID
     */
    public function deleteQuestionAnswer(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $answer = $this->questionAnswerRepository->findWithRelations($id);
            $studentId = $answer->student_id;
            $examId = $answer->examQuestion->subject_exam_id;

            $result = $this->questionAnswerRepository->delete($id);

            // Update exam attempt progress
            $this->updateAttemptProgress($studentId, $examId);

            return $result;
        });
    }

    /**
     * Grade essay answer
     */
    public function gradeAnswer(int $id, array $data): QuestionAnswer
    {
        return DB::transaction(function () use ($id, $data) {
            $answer = $this->questionAnswerRepository->findWithRelations($id);

            // Verify essay question
            if ($answer->examQuestion->type === 'multiple_choice') {
                throw new \Exception('Multiple choice questions are auto-graded and cannot be manually graded');
            }

            // Verify points don't exceed maximum
            $maxPoints = $answer->examQuestion->points;
            if ($data['points_earned'] > $maxPoints) {
                throw new \Exception("Points earned cannot exceed the maximum points for this question ({$maxPoints})");
            }

            // Auto-calculate has_passed if not provided
            if (!isset($data['has_passed'])) {
                $data['has_passed'] = $data['points_earned'] > 0;
            }

            $updatedAnswer = $this->questionAnswerRepository->update($id, $data);

            // Update exam attempt totals after grading
            $this->updateAttemptProgress($answer->student_id, $answer->examQuestion->subject_exam_id);

            return $updatedAnswer;
        });
    }

    /**
     * Bulk grade multiple answers
     */
    public function bulkGrade(array $answers): array
    {
        return DB::transaction(function () use ($answers) {
            $graded = 0;
            $errors = [];
            $studentsToUpdate = [];

            foreach ($answers as $answerData) {
                try {
                    $answer = $this->questionAnswerRepository->findWithRelations($answerData['id']);

                    // Skip multiple choice questions
                    if ($answer->examQuestion->type === 'multiple_choice') {
                        $errors[] = "Answer ID {$answerData['id']} is multiple choice and cannot be manually graded";
                        continue;
                    }

                    // Verify points don't exceed maximum
                    $maxPoints = $answer->examQuestion->points;
                    if ($answerData['points_earned'] > $maxPoints) {
                        $errors[] = "Answer ID {$answerData['id']}: Points earned ({$answerData['points_earned']}) exceed maximum ({$maxPoints})";
                        continue;
                    }

                    $this->questionAnswerRepository->update($answerData['id'], [
                        'points_earned' => $answerData['points_earned'],
                        'has_passed' => $answerData['has_passed'],
                        'feedback' => $answerData['feedback'] ?? null
                    ]);

                    // Track students/exams that need attempt updates
                    $key = "{$answer->student_id}_{$answer->examQuestion->subject_exam_id}";
                    $studentsToUpdate[$key] = [
                        'student_id' => $answer->student_id,
                        'exam_id' => $answer->examQuestion->subject_exam_id
                    ];

                    $graded++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to grade answer ID {$answerData['id']}: " . $e->getMessage();
                }
            }

            // Update exam attempts for all affected students
            foreach ($studentsToUpdate as $update) {
                $this->updateAttemptProgress($update['student_id'], $update['exam_id']);
            }

            return [
                'graded_count' => $graded,
                'errors' => $errors
            ];
        });
    }

    /**
     * Get answers needing grading
     */
    public function needsGrading(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->questionAnswerRepository->needsGrading($filters, $perPage);
    }

    /**
     * Get student answers for specific exam
     */
    public function getStudentAnswers(int $studentId, int $examId): array
    {
        $answers = $this->questionAnswerRepository->getStudentAnswersForExam($studentId, $examId);

        if ($answers->isEmpty()) {
            throw new \Exception('No answers found for this student and exam');
        }

        // Calculate total score
        $totalPointsEarned = $answers->sum('points_earned');
        $totalPossiblePoints = $answers->sum(function($answer) {
            return $answer->examQuestion->points ?? 0;
        });
        $percentage = $totalPossiblePoints > 0 ?
            round(($totalPointsEarned / $totalPossiblePoints) * 100, 2) : 0;

        return [
            'answers' => $answers,
            'summary' => [
                'total_questions' => $answers->count(),
                'total_points_earned' => $totalPointsEarned,
                'total_possible_points' => $totalPossiblePoints,
                'percentage' => $percentage,
                'needs_grading' => $answers->where('points_earned', 0)->where('examQuestion.type', 'essay')->count()
            ]
        ];
    }

    /**
     * Delete multiple question answers
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->questionAnswerRepository->delete($id);
            }
            return true;
        });
    }

    /**
     * Update exam attempt progress and totals
     */
    private function updateAttemptProgress(int $studentId, int $examId): void
    {
        $attempt = $this->examAttemptRepository->findByStudentAndExam($studentId, $examId);

        if ($attempt) {
            // Get all student answers for this exam
            $answers = $this->questionAnswerRepository->getStudentAnswersForExam($studentId, $examId);

            // Calculate totals
            $answeredCount = $answers->count();
            $totalPointsEarned = $answers->sum('points_earned');
            $totalPossiblePoints = $answers->sum(function($answer) {
                return $answer->examQuestion->points ?? 0;
            });

            // Calculate percentage and pass status
            $scorePercentage = $totalPossiblePoints > 0 ?
                round(($totalPointsEarned / $totalPossiblePoints) * 100, 2) : 0;
            $hasPassed = $scorePercentage >= 60; // Assuming 60% is passing

            $attempt->update([
                'answered_questions' => $answeredCount,
                'points_earned' => $totalPointsEarned,
                'score_percentage' => $scorePercentage,
                'has_passed' => $hasPassed
            ]);
        }
    }
}
