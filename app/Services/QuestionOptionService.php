<?php

namespace App\Services;

use App\Repositories\QuestionOptionRepository;
use App\Models\QuestionOption;
use App\Models\ExamQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QuestionOptionService
{
    private QuestionOptionRepository $questionOptionRepository;

    public function __construct(QuestionOptionRepository $questionOptionRepository)
    {
        $this->questionOptionRepository = $questionOptionRepository;
    }

    /**
     * Get paginated question options
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->questionOptionRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get all question options without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->questionOptionRepository->getAll($filters);
    }

    /**
     * Find question option by ID
     */
    public function findQuestionOption(int $id): QuestionOption
    {
        return $this->questionOptionRepository->findWithRelations($id);
    }

    /**
     * Create a new question option
     */
    public function createQuestionOption(array $data): QuestionOption
    {
        return DB::transaction(function () use ($data) {
            // Verify exam question exists and is multiple choice
            $examQuestion = ExamQuestion::findOrFail($data['exam_question_id']);
            
            if ($examQuestion->type !== 'multiple_choice') {
                throw new \Exception('Options can only be added to multiple choice questions');
            }

            // If this option is marked as correct, ensure no other option is correct
            if ($data['is_correct'] ?? false) {
                $this->ensureOnlyOneCorrectOption($data['exam_question_id'], null);
            }

            // Validate total options count
            $currentCount = $this->questionOptionRepository->countByQuestion($data['exam_question_id']);
            if ($currentCount >= 10) {
                throw new \Exception('Cannot add more than 10 options to a question');
            }

            return $this->questionOptionRepository->create($data);
        });
    }

    /**
     * Update question option by ID
     */
    public function updateQuestionOption(int $id, array $data): QuestionOption
    {
        return DB::transaction(function () use ($id, $data) {
            $option = $this->questionOptionRepository->findWithRelations($id);

            // Verify question is multiple choice
            if ($option->examQuestion->type !== 'multiple_choice') {
                throw new \Exception('Options can only be updated for multiple choice questions');
            }

            // If this option is being marked as correct, ensure no other option is correct
            if (isset($data['is_correct']) && $data['is_correct']) {
                $this->ensureOnlyOneCorrectOption($option->exam_question_id, $id);
            }

            // If this is the only correct option and we're unmarking it, prevent it
            if (isset($data['is_correct']) && !$data['is_correct'] && $option->is_correct) {
                $correctCount = $this->questionOptionRepository->countCorrectByQuestion($option->exam_question_id);
                if ($correctCount <= 1) {
                    throw new \Exception('Cannot unmark the only correct option. Mark another option as correct first.');
                }
            }

            return $this->questionOptionRepository->update($id, $data);
        });
    }

    /**
     * Delete question option by ID
     */
    public function deleteQuestionOption(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $option = $this->questionOptionRepository->findWithRelations($id);

            // Check if this is the last option or only correct option
            $totalOptions = $this->questionOptionRepository->countByQuestion($option->exam_question_id);
            if ($totalOptions <= 2) {
                throw new \Exception('Cannot delete option. Multiple choice questions must have at least 2 options.');
            }

            if ($option->is_correct) {
                $correctCount = $this->questionOptionRepository->countCorrectByQuestion($option->exam_question_id);
                if ($correctCount <= 1) {
                    throw new \Exception('Cannot delete the only correct option. Mark another option as correct first.');
                }
            }

            return $this->questionOptionRepository->delete($id);
        });
    }

    /**
     * Get options by exam question
     */
    public function getOptionsByExamQuestion(int $examQuestionId): Collection
    {
        return $this->questionOptionRepository->getByExamQuestion($examQuestionId);
    }

    /**
     * Get correct options for a question
     */
    public function getCorrectOptions(int $examQuestionId): Collection
    {
        return $this->questionOptionRepository->getCorrectOptions($examQuestionId);
    }

    /**
     * Set correct option for a question
     */
    public function setCorrectOption(int $examQuestionId, int $correctOptionId): QuestionOption
    {
        return DB::transaction(function () use ($examQuestionId, $correctOptionId) {
            // Verify the option belongs to the question
            $option = QuestionOption::where('id', $correctOptionId)
                ->where('exam_question_id', $examQuestionId)
                ->firstOrFail();

            // Verify the question is multiple choice
            $examQuestion = ExamQuestion::findOrFail($examQuestionId);
            if ($examQuestion->type !== 'multiple_choice') {
                throw new \Exception('Correct options can only be set for multiple choice questions');
            }

            // Update correct option
            $this->questionOptionRepository->updateCorrectOption($examQuestionId, $correctOptionId);

            return $option->fresh();
        });
    }

    /**
     * Bulk create options for a question
     */
    public function bulkCreateOptionsForQuestion(int $examQuestionId, array $optionsData): Collection
    {
        return DB::transaction(function () use ($examQuestionId, $optionsData) {
            // Verify exam question exists and is multiple choice
            $examQuestion = ExamQuestion::findOrFail($examQuestionId);
            
            if ($examQuestion->type !== 'multiple_choice') {
                throw new \Exception('Options can only be added to multiple choice questions');
            }

            // Validate options data
            $errors = $this->questionOptionRepository->validateOptionConstraints($examQuestionId, $optionsData);
            if (!empty($errors)) {
                throw new \Exception('Validation failed: ' . implode(', ', $errors));
            }

            // Delete existing options
            $this->questionOptionRepository->deleteByQuestion($examQuestionId);

            // Create new options
            return $this->questionOptionRepository->bulkCreateForQuestion($examQuestionId, $optionsData);
        });
    }

    /**
     * Search question options
     */
    public function searchOptions(string $query, int $perPage = 10): LengthAwarePaginator
    {
        return $this->questionOptionRepository->search($query, $perPage);
    }

    /**
     * Get option statistics
     */
    public function getOptionStatistics(int $examQuestionId): array
    {
        return $this->questionOptionRepository->getStatistics($examQuestionId);
    }

    /**
     * Validate option data
     */
    public function validateOptionData(array $data): bool
    {
        // Verify exam question exists
        if (!ExamQuestion::where('id', $data['exam_question_id'])->exists()) {
            throw new \Exception('Exam question not found');
        }

        // Verify exam question is multiple choice
        $examQuestion = ExamQuestion::find($data['exam_question_id']);
        if ($examQuestion->type !== 'multiple_choice') {
            throw new \Exception('Options can only be added to multiple choice questions');
        }

        return true;
    }

    /**
     * Ensure only one correct option per question
     */
    private function ensureOnlyOneCorrectOption(int $examQuestionId, ?int $excludeOptionId = null): void
    {
        $query = QuestionOption::where('exam_question_id', $examQuestionId)
            ->where('is_correct', true);

        if ($excludeOptionId) {
            $query->where('id', '!=', $excludeOptionId);
        }

        $query->update(['is_correct' => false]);
    }

    /**
     * Bulk update options
     */
    public function bulkUpdateOptions(array $updates): array
    {
        return DB::transaction(function () use ($updates) {
            $updated = 0;
            $errors = [];

            foreach ($updates as $update) {
                try {
                    if (!isset($update['id'])) {
                        $errors[] = 'Option ID is required for updates';
                        continue;
                    }

                    $optionData = $update;
                    unset($optionData['id']);

                    $this->updateQuestionOption($update['id'], $optionData);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "Option ID {$update['id']}: " . $e->getMessage();
                }
            }

            return [
                'updated_count' => $updated,
                'errors' => $errors
            ];
        });
    }

    // /**
    //  * Bulk delete options - UNUSED
    //  */
    // public function bulkDeleteOptions(array $ids): array
    // {
    //     return DB::transaction(function () use ($ids) {
    //         $deleted = 0;
    //         $errors = [];

    //         foreach ($ids as $id) {
    //             try {
    //                 $this->deleteQuestionOption($id);
    //                 $deleted++;
    //             } catch (\Exception $e) {
    //                 $errors[] = "Option ID {$id}: " . $e->getMessage();
    //             }
    //         }

    //         return [
    //             'deleted_count' => $deleted,
    //             'errors' => $errors
    //         ];
    //     });
    // }

    /**
     * Reorder options for a question
     */
    public function reorderOptions(int $examQuestionId, array $orderData): Collection
    {
        return DB::transaction(function () use ($examQuestionId, $orderData) {
            // Verify all options belong to the question
            $options = $this->questionOptionRepository->getByExamQuestion($examQuestionId);
            $optionIds = $options->pluck('id')->toArray();

            foreach ($orderData as $item) {
                if (!in_array($item['id'], $optionIds)) {
                    throw new \Exception("Option ID {$item['id']} does not belong to this question");
                }
            }

            // Update order (if you have an order column in your table)
            // For now, we'll just return the options in the requested order
            $orderedOptions = collect();
            foreach ($orderData as $item) {
                $option = $options->firstWhere('id', $item['id']);
                if ($option) {
                    $orderedOptions->push($option);
                }
            }

            return $orderedOptions;
        });
    }
}