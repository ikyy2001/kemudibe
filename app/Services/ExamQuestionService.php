<?php

namespace App\Services;

use App\Repositories\ExamQuestionRepository;
use App\Models\ExamQuestion;
use App\Models\SubjectExam;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ExamQuestionService
{
    private ExamQuestionRepository $examQuestionRepository;

    public function __construct(ExamQuestionRepository $examQuestionRepository)
    {
        $this->examQuestionRepository = $examQuestionRepository;
    }

    /**
     * Get paginated exam questions
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->examQuestionRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get all exam questions without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->examQuestionRepository->getAll($filters);
    }

    /**
     * Find exam question by ID
     */
    public function findExamQuestion(int $id): ExamQuestion
    {
        return $this->examQuestionRepository->findWithRelations($id);
    }

    /**
     * Create a new exam question
     */
    public function createExamQuestion(array $data): ExamQuestion
    {
        return DB::transaction(function () use ($data) {
            // Verify subject exam exists
            $subjectExam = SubjectExam::with('examQuestions')->findOrFail($data['subject_exam_id']);

            // Validate point limit
            $this->validatePointLimit($subjectExam, $data['points']);

            // Extract options if provided
            $options = $data['options'] ?? [];
            unset($data['options']);

            // Normalize legacy multiple_choice
            if ($data['type'] === 'multiple_choice') {
                $data['type'] = ExamQuestion::TYPE_SINGLE_CHOICE;
            }

            // Validate question type requirements
            if (in_array($data['type'], [ExamQuestion::TYPE_SINGLE_CHOICE, ExamQuestion::TYPE_MULTIPLE_ANSWER, ExamQuestion::TYPE_TRUE_FALSE_TABLE])) {
                if (empty($options)) {
                    throw new \Exception('Question must have options or statements');
                }
            }

            if ($data['type'] === ExamQuestion::TYPE_SINGLE_CHOICE && !collect($options)->contains('is_correct', true)) {
                throw new \Exception('Multiple choice questions must have at least one correct option');
            }

            if ($data['type'] === ExamQuestion::TYPE_MULTIPLE_ANSWER && !collect($options)->contains('is_correct', true)) {
                throw new \Exception('Multiple answer questions must have at least one correct option');
            }

            if ($data['type'] === ExamQuestion::TYPE_TRUE_FALSE_TABLE && count($options) < 2) {
                throw new \Exception('True/False table questions must have at least two statements');
            }

            if ($data['type'] === ExamQuestion::TYPE_ESSAY && !empty($options)) {
                throw new \Exception('Essay questions cannot have options');
            }

            // Create the question
            $question = $this->examQuestionRepository->create($data);

            // Create options for non-essay questions
            if ($data['type'] !== ExamQuestion::TYPE_ESSAY && !empty($options)) {
                foreach ($options as $index => $option) {
                    $question->questionOptions()->create([
                        'name' => $option['name'],
                        'is_correct' => (bool)($option['is_correct'] ?? false),
                        'sort_order' => (int)($option['sort_order'] ?? $index),
                    ]);
                }
            }

            return $question->load(['subjectExam.subject', 'questionOptions']);
        });
    }

    /**
     * Update exam question by ID
     */
    public function updateExamQuestion(int $id, array $data): ExamQuestion
    {
        return DB::transaction(function () use ($id, $data) {
            $question = $this->examQuestionRepository->findWithRelations($id);

            // Validate point limit if points are being updated
            if (isset($data['points'])) {
                $subjectExam = SubjectExam::with('examQuestions')->findOrFail($question->subject_exam_id);
                $this->validatePointLimit($subjectExam, $data['points'], $id);
            }

            // Extract options if provided
            $options = $data['options'] ?? null;
            unset($data['options']);

            if (isset($data['type']) && $data['type'] === 'multiple_choice') {
                $data['type'] = ExamQuestion::TYPE_SINGLE_CHOICE;
            }

            $targetType = $data['type'] ?? $question->type;

            // Validate question type requirements if type is being changed
            if (isset($data['type'])) {
                if (in_array($targetType, [ExamQuestion::TYPE_SINGLE_CHOICE, ExamQuestion::TYPE_MULTIPLE_ANSWER, ExamQuestion::TYPE_TRUE_FALSE_TABLE]) 
                    && $options === null && $question->questionOptions->isEmpty()) {
                    throw new \Exception('Questions with options must have options');
                }

                if ($targetType === ExamQuestion::TYPE_ESSAY && !$question->questionOptions->isEmpty()) {
                    // Delete existing options when converting to essay
                    $question->questionOptions()->delete();
                }
            }

            // Update the question
            $question = $this->examQuestionRepository->update($id, $data);

            // Update options if provided
            if ($options !== null && $targetType !== ExamQuestion::TYPE_ESSAY) {
                // Delete existing options
                $question->questionOptions()->delete();

                // Validate correct options for choices
                if (in_array($targetType, [ExamQuestion::TYPE_SINGLE_CHOICE, ExamQuestion::TYPE_MULTIPLE_ANSWER]) 
                    && !collect($options)->contains('is_correct', true)) {
                    throw new \Exception('Multiple choice questions must have at least one correct option');
                }

                // Create new options
                foreach ($options as $index => $option) {
                    $question->questionOptions()->create([
                        'name' => $option['name'],
                        'is_correct' => (bool)($option['is_correct'] ?? false),
                        'sort_order' => (int)($option['sort_order'] ?? $index),
                    ]);
                }
            }

            return $question->load(['subjectExam.subject', 'questionOptions']);
        });
    }

    /**
     * Delete exam question by ID
     */
    public function deleteExamQuestion(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $question = $this->examQuestionRepository->findWithRelations($id);

            // Check if question has any answers
            if ($question->questionAnswers->isNotEmpty()) {
                throw new \Exception('Cannot delete question that has student answers. Consider archiving instead.');
            }

            return $this->examQuestionRepository->delete($id);
        });
    }

    /**
     * Get questions by subject exam
     */
    public function getQuestionsBySubjectExam(int $subjectExamId): Collection
    {
        return $this->examQuestionRepository->getBySubjectExam($subjectExamId);
    }

    /**
     * Search exam questions
     */
    public function searchQuestions(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->examQuestionRepository->search($query, $fields, $perPage);
    }

    /**
     * Get questions by type
     */
    public function getQuestionsByType(string $type, array $filters = []): Collection
    {
        return $this->examQuestionRepository->getByType($type, $filters);
    }

    /**
     * Duplicate question
     */
    public function duplicateQuestion(int $id, int $newSubjectExamId): ExamQuestion
    {
        return DB::transaction(function () use ($id, $newSubjectExamId) {
            // Verify target exam exists and get original question
            $targetExam = SubjectExam::with('examQuestions')->findOrFail($newSubjectExamId);
            $originalQuestion = $this->examQuestionRepository->findWithRelations($id);

            // Validate point limit for duplicate
            $this->validatePointLimit($targetExam, $originalQuestion->points);

            return $this->examQuestionRepository->duplicate($id, $newSubjectExamId);
        });
    }

    /**
     * Bulk create questions
     */
    public function bulkCreateQuestions(array $questionsData): array
    {
        return DB::transaction(function () use ($questionsData) {
            $created = [];
            $errors = [];

            // Group questions by exam to validate total points
            $examGroups = collect($questionsData)->groupBy('subject_exam_id');

            foreach ($examGroups as $examId => $examQuestions) {
                try {
                    // Load exam with existing questions
                    $exam = SubjectExam::with('examQuestions')->findOrFail($examId);
                    $currentPoints = $exam->examQuestions->sum('points');
                    $newQuestionsPoints = collect($examQuestions)->sum('points');

                    // Validate total doesn't exceed 100
                    if ($currentPoints + $newQuestionsPoints > 100) {
                        $availablePoints = 100 - $currentPoints;
                        throw new \Exception("Bulk operation would exceed 100-point limit for exam {$examId}. Current: {$currentPoints}, Trying to add: {$newQuestionsPoints}, Available: {$availablePoints}");
                    }
                } catch (\Exception $e) {
                    // Mark all questions for this exam as failed
                    foreach ($examQuestions as $index => $questionData) {
                        $errors[] = "Question " . ($index + 1) . " (Exam {$examId}): " . $e->getMessage();
                    }
                    continue; // Skip this exam's questions
                }
            }

            // If validation passed, create questions individually
            foreach ($questionsData as $index => $questionData) {
                try {
                    $question = $this->createExamQuestion($questionData);
                    $created[] = $question;
                } catch (\Exception $e) {
                    $errors[] = "Question " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            return [
                'created' => $created,
                'errors' => $errors,
                'created_count' => count($created),
                'error_count' => count($errors)
            ];
        });
    }



    /**
     * Get exam statistics
     */
    public function getExamStatistics(int $subjectExamId): array
    {
        $questions = $this->getQuestionsBySubjectExam($subjectExamId);
        $totalQuestions = $questions->count();
        $singleChoice = $questions->whereIn('type', [ExamQuestion::TYPE_SINGLE_CHOICE, 'multiple_choice'])->count();
        $multipleAnswer = $questions->where('type', ExamQuestion::TYPE_MULTIPLE_ANSWER)->count();
        $trueFalseTable = $questions->where('type', ExamQuestion::TYPE_TRUE_FALSE_TABLE)->count();
        $essay = $questions->where('type', ExamQuestion::TYPE_ESSAY)->count();
        $totalPoints = $questions->sum('points');

        return [
            'total_questions' => $totalQuestions,
            'single_choice_count' => $singleChoice,
            'multiple_answer_count' => $multipleAnswer,
            'true_false_table_count' => $trueFalseTable,
            'multiple_choice_count' => $singleChoice,
            'essay_count' => $essay,
            'total_points' => $totalPoints,
            'average_points' => $totalQuestions > 0 ? round($totalPoints / $totalQuestions, 2) : 0,
            'questions_by_type' => [
                'single_choice' => $singleChoice,
                'multiple_answer' => $multipleAnswer,
                'true_false_table' => $trueFalseTable,
                'multiple_choice' => $singleChoice,
                'essay' => $essay
            ]
        ];
    }

    /**
     * Validate question data
     */
    public function validateQuestionData(array $data): bool
    {
        // Verify subject exam exists
        if (!SubjectExam::where('id', $data['subject_exam_id'])->exists()) {
            throw new \Exception('Subject exam not found');
        }

        $type = ($data['type'] ?? '') === 'multiple_choice' ? ExamQuestion::TYPE_SINGLE_CHOICE : ($data['type'] ?? '');

        // Validate question type specific requirements
        if (in_array($type, [ExamQuestion::TYPE_SINGLE_CHOICE, ExamQuestion::TYPE_MULTIPLE_ANSWER, ExamQuestion::TYPE_TRUE_FALSE_TABLE])) {
            if (!isset($data['options']) || empty($data['options'])) {
                throw new \Exception('Question must have options or statements');
            }

            if (in_array($type, [ExamQuestion::TYPE_SINGLE_CHOICE, ExamQuestion::TYPE_MULTIPLE_ANSWER]) 
                && !collect($data['options'])->contains('is_correct', true)) {
                throw new \Exception('Multiple choice questions must have at least one correct option');
            }

            if ($type === ExamQuestion::TYPE_TRUE_FALSE_TABLE && count($data['options']) < 2) {
                throw new \Exception('True/False table questions must have at least two statements');
            }
        } elseif ($type === ExamQuestion::TYPE_ESSAY) {
            if (isset($data['options']) && !empty($data['options'])) {
                throw new \Exception('Essay questions cannot have options');
            }
        }

        return true;
    }

    /**
     * Count questions by exam
     */
    public function countQuestionsByExam(int $subjectExamId): int
    {
        return $this->examQuestionRepository->countByExam($subjectExamId);
    }

    /**
     * Get questions needing grading
     */
    public function getQuestionsForGrading(int $subjectExamId): Collection
    {
        return $this->examQuestionRepository->getQuestionsForGrading($subjectExamId);
    }

    /**
     * Validate that adding points won't exceed the 100-point exam limit
     */
    private function validatePointLimit(SubjectExam $exam, int $newPoints, ?int $excludeQuestionId = null): void
    {
        $currentPoints = $exam->examQuestions->sum('points');

        // Exclude current question points for updates
        if ($excludeQuestionId) {
            $currentQuestion = $exam->examQuestions->where('id', $excludeQuestionId)->first();
            if ($currentQuestion) {
                $currentPoints -= $currentQuestion->points;
            }
        }

        $newTotal = $currentPoints + $newPoints;

        if ($newTotal > 100) {
            $availablePoints = 100 - $currentPoints;
            throw new \Exception("Adding {$newPoints} points would exceed the 100-point exam limit. Only {$availablePoints} points available.");
        }
    }
}
