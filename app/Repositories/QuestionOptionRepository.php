<?php

namespace App\Repositories;

use App\Models\QuestionOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QuestionOptionRepository
{
    /**
     * Get paginated question options with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = QuestionOption::with(['examQuestion.subjectExam'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get all question options without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        $query = QuestionOption::with(['examQuestion.subjectExam'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Find question option by ID with relationships
     */
    public function findWithRelations(int $id): QuestionOption
    {
        return QuestionOption::with(['examQuestion.subjectExam.subject'])
            ->findOrFail($id);
    }

    /**
     * Create a new question option
     */
    public function create(array $data): QuestionOption
    {
        return QuestionOption::create($data);
    }

    /**
     * Update question option by ID
     */
    public function update(int $id, array $data): QuestionOption
    {
        $option = QuestionOption::findOrFail($id);
        $option->update($data);
        return $option->fresh(['examQuestion']);
    }

    /**
     * Delete question option by ID
     */
    public function delete(int $id): bool
    {
        $option = QuestionOption::findOrFail($id);
        return $option->delete();
    }

    /**
     * Get options by exam question ID
     */
    public function getByExamQuestion(int $examQuestionId): Collection
    {
        return QuestionOption::where('exam_question_id', $examQuestionId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get correct options for a question
     */
    public function getCorrectOptions(int $examQuestionId): Collection
    {
        return QuestionOption::where('exam_question_id', $examQuestionId)
            ->where('is_correct', true)
            ->get();
    }

    /**
     * Count options by question
     */
    public function countByQuestion(int $examQuestionId): int
    {
        return QuestionOption::where('exam_question_id', $examQuestionId)->count();
    }

    /**
     * Count correct options by question
     */
    public function countCorrectByQuestion(int $examQuestionId): int
    {
        return QuestionOption::where('exam_question_id', $examQuestionId)
            ->where('is_correct', true)
            ->count();
    }

    /**
     * Update correct option for a question (ensure only one correct)
     */
    public function updateCorrectOption(int $examQuestionId, int $correctOptionId): void
    {
        // Set all options to incorrect first
        QuestionOption::where('exam_question_id', $examQuestionId)
            ->update(['is_correct' => false]);

        // Set the selected option to correct
        QuestionOption::where('id', $correctOptionId)
            ->where('exam_question_id', $examQuestionId)
            ->update(['is_correct' => true]);
    }

    /**
     * Bulk create options for a question
     */
    public function bulkCreateForQuestion(int $examQuestionId, array $optionsData): Collection
    {
        $options = collect();

        foreach ($optionsData as $optionData) {
            $optionData['exam_question_id'] = $examQuestionId;
            $options->push($this->create($optionData));
        }

        return $options;
    }

    /**
     * Delete all options for a question
     */
    public function deleteByQuestion(int $examQuestionId): bool
    {
        return QuestionOption::where('exam_question_id', $examQuestionId)->delete();
    }

    /**
     * Search question options
     */
    public function search(string $query, int $perPage = 10): LengthAwarePaginator
    {
        return QuestionOption::with(['examQuestion.subjectExam'])
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhereHas('examQuestion', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['exam_question_id'])) {
            $query->where('exam_question_id', $filters['exam_question_id']);
        }

        if (isset($filters['is_correct'])) {
            $query->where('is_correct', $filters['is_correct']);
        }

        if (isset($filters['subject_exam_id'])) {
            $query->whereHas('examQuestion', function($q) use ($filters) {
                $q->where('subject_exam_id', $filters['subject_exam_id']);
            });
        }

        if (isset($filters['subject_id'])) {
            $query->whereHas('examQuestion.subjectExam', function($q) use ($filters) {
                $q->where('subject_id', $filters['subject_id']);
            });
        }

        if (isset($filters['teacher_id'])) {
            $query->whereHas('examQuestion.subjectExam.subject', function($q) use ($filters) {
                $q->where('teacher_id', $filters['teacher_id']);
            });
        }
    }

    /**
     * Validate option constraints
     */
    public function validateOptionConstraints(int $examQuestionId, array $options): array
    {
        $errors = [];
        $correctCount = collect($options)->where('is_correct', true)->count();

        if ($correctCount === 0) {
            $errors[] = 'At least one option must be marked as correct';
        }

        if ($correctCount > 1) {
            $errors[] = 'Only one option can be marked as correct for multiple choice questions';
        }

        if (count($options) < 2) {
            $errors[] = 'Multiple choice questions must have at least 2 options';
        }

        if (count($options) > 10) {
            $errors[] = 'Multiple choice questions cannot have more than 10 options';
        }

        return $errors;
    }

    /**
     * Get statistics for question options
     */
    public function getStatistics(int $examQuestionId): array
    {
        $options = $this->getByExamQuestion($examQuestionId);
        $totalOptions = $options->count();
        $correctOptions = $options->where('is_correct', true)->count();

        return [
            'total_options' => $totalOptions,
            'correct_options' => $correctOptions,
            'incorrect_options' => $totalOptions - $correctOptions,
            'has_correct_answer' => $correctOptions > 0,
            'is_valid' => $correctOptions === 1 && $totalOptions >= 2
        ];
    }
}