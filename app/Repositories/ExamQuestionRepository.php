<?php

namespace App\Repositories;

use App\Models\ExamQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ExamQuestionRepository
{
    /**
     * Get paginated exam questions with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ExamQuestion::with(['subjectExam.subject', 'questionOptions', 'questionAnswers'])
            ->withCount(['questionOptions', 'questionAnswers'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get all exam questions without pagination
     */
    public function getAll(array $filters = []): Collection
    {
        $query = ExamQuestion::with(['subjectExam.subject', 'questionOptions'])
            ->withCount(['questionOptions', 'questionAnswers'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Find exam question by ID with relationships
     */
    public function findWithRelations(int $id): ExamQuestion
    {
        return ExamQuestion::with(['subjectExam.subject', 'questionOptions', 'questionAnswers'])
            ->withCount(['questionOptions', 'questionAnswers'])
            ->findOrFail($id);
    }

    /**
     * Create a new exam question
     */
    public function create(array $data): ExamQuestion
    {
        return ExamQuestion::create($data);
    }

    /**
     * Update exam question by ID
     */
    public function update(int $id, array $data): ExamQuestion
    {
        $question = ExamQuestion::findOrFail($id);
        $question->update($data);
        return $question->fresh(['subjectExam.subject', 'questionOptions']);
    }

    /**
     * Delete exam question by ID
     */
    public function delete(int $id): bool
    {
        $question = ExamQuestion::findOrFail($id);
        return $question->delete();
    }

    /**
     * Get questions by subject exam ID
     */
    public function getBySubjectExam(int $subjectExamId): Collection
    {
        return ExamQuestion::where('subject_exam_id', $subjectExamId)
            ->with(['questionOptions'])
            ->withCount(['questionOptions', 'questionAnswers'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Search exam questions
     */
    public function search(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ExamQuestion::select($fields)
            ->with(['subjectExam.subject', 'questionOptions'])
            ->withCount(['questionOptions', 'questionAnswers'])
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhereHas('subjectExam.subject', function($subQuery) use ($query) {
                      $subQuery->where('name', 'LIKE', "%{$query}%");
                  });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get questions by type
     */
    public function getByType(string $type, array $filters = []): Collection
    {
        $query = ExamQuestion::where('type', $type)
            ->with(['subjectExam.subject', 'questionOptions'])
            ->withCount(['questionOptions', 'questionAnswers']);

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Duplicate a question
     */
    public function duplicate(int $id, int $newSubjectExamId): ExamQuestion
    {
        $original = $this->findWithRelations($id);
        
        $duplicated = ExamQuestion::create([
            'subject_exam_id' => $newSubjectExamId,
            'name' => $original->name . ' (Copy)',
            'timer' => $original->timer,
            'type' => $original->type,
            'points' => $original->points,
        ]);

        // Duplicate options if multiple choice
        if ($original->type === 'multiple_choice') {
            foreach ($original->questionOptions as $option) {
                $duplicated->questionOptions()->create([
                    'name' => $option->name,
                    'is_correct' => $option->is_correct,
                ]);
            }
        }

        return $duplicated->load(['subjectExam.subject', 'questionOptions']);
    }

    /**
     * Count questions by exam
     */
    public function countByExam(int $subjectExamId): int
    {
        return ExamQuestion::where('subject_exam_id', $subjectExamId)->count();
    }

    /**
     * Get questions with answers for grading
     */
    public function getQuestionsForGrading(int $subjectExamId): Collection
    {
        return ExamQuestion::where('subject_exam_id', $subjectExamId)
            ->with([
                'questionAnswers' => function($query) {
                    $query->where('points_earned', 0)->where(function($q) {
                        $q->whereHas('examQuestion', function($subQ) {
                            $subQ->where('type', 'essay');
                        });
                    });
                },
                'questionAnswers.student'
            ])
            ->whereHas('questionAnswers', function($query) {
                $query->where('points_earned', 0)->where(function($q) {
                    $q->whereHas('examQuestion', function($subQ) {
                        $subQ->where('type', 'essay');
                    });
                });
            })
            ->get();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['subject_exam_id'])) {
            $query->where('subject_exam_id', $filters['subject_exam_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['min_points'])) {
            $query->where('points', '>=', $filters['min_points']);
        }

        if (isset($filters['max_points'])) {
            $query->where('points', '<=', $filters['max_points']);
        }

        if (isset($filters['subject_id'])) {
            $query->whereHas('subjectExam', function($q) use ($filters) {
                $q->where('subject_id', $filters['subject_id']);
            });
        }

        if (isset($filters['teacher_id'])) {
            $query->whereHas('subjectExam.subject', function($q) use ($filters) {
                $q->where('teacher_id', $filters['teacher_id']);
            });
        }
    }
}