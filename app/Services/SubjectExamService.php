<?php

namespace App\Services;

use App\Repositories\SubjectExamRepository;
use App\Repositories\SubjectRepository;
use App\Models\SubjectExam;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class SubjectExamService
{
    private SubjectExamRepository $subjectExamRepository;
    private SubjectRepository $subjectRepository;

    public function __construct(
        SubjectExamRepository $subjectExamRepository,
        SubjectRepository $subjectRepository
    ) {
        $this->subjectExamRepository = $subjectExamRepository;
        $this->subjectRepository = $subjectRepository;
    }

    /**
     * Get paginated exams
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectExamRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all exams without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->getAll($fields);
    }

    /**
     * Find exam by ID
     */
    public function findExam(int $id, array $fields = ['*']): SubjectExam
    {
        return $this->subjectExamRepository->findWithRelations($id, $fields);
    }

    /**
     * Find exam by ID with questions
     */
    public function findExamWithQuestions(int $id): SubjectExam
    {
        return $this->subjectExamRepository->findWithQuestions($id);
    }

    /**
     * Find exams by subject ID
     */
    public function findExamsBySubject(int $subjectId, array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->findBySubject($subjectId, $fields);
    }

    /**
     * Find exams by teacher ID
     */
    public function findExamsByTeacher(int $teacherId, array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->findByTeacher($teacherId, $fields);
    }

    /**
     * Find exams by topic ID
     */
    public function findExamsByTopic(int $topicId, array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->findByTopic($topicId, $fields);
    }

    /**
     * Get active exams (currently running)
     */
    public function getActiveExams(array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->getActiveExams($fields);
    }

    /**
     * Get upcoming exams
     */
    public function getUpcomingExams(array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->getUpcomingExams($fields);
    }

    /**
     * Get completed exams
     */
    public function getCompletedExams(array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->getCompletedExams($fields);
    }

    /**
     * Find exams by date range
     */
    public function findExamsByDateRange(string $startDate, string $endDate, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        // Validate dates
        $start = Carbon::createFromFormat('Y-m-d', $startDate);
        $end = Carbon::createFromFormat('Y-m-d', $endDate);
        
        if ($start > $end) {
            throw new \Exception('Start date must be before end date');
        }

        return $this->subjectExamRepository->findByDateRange($startDate, $endDate, $fields, $perPage);
    }

    /**
     * Search exams by query
     */
    public function searchExams(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectExamRepository->searchByNameOrSubject($query, $fields, $perPage);
    }

    /**
     * Create a new exam
     */
    public function createExam(array $data): SubjectExam
    {
        return DB::transaction(function () use ($data) {
            // Validate subject exists
            $subject = $this->subjectRepository->find($data['subject_id']);

            // Validate dates
            $startDate = Carbon::parse($data['started_at']);
            $endDate = Carbon::parse($data['ended_at']);
            
            if ($startDate >= $endDate) {
                throw new \Exception('End date must be after start date');
            }

            if ($startDate->startOfDay() < Carbon::now()->startOfDay()) {
                throw new \Exception('Start date cannot be in the past');
            }


            return $this->subjectExamRepository->create($data);
        });
    }

    /**
     * Update exam
     */
    public function updateExam(int $id, array $data): SubjectExam
    {
        return DB::transaction(function () use ($id, $data) {
            $currentExam = $this->subjectExamRepository->findWithRelations($id, ['*']);

            // Validate subject if being updated
            if (isset($data['subject_id'])) {
                $this->subjectRepository->find($data['subject_id']);
            }

            // Validate dates if being updated
            if (isset($data['started_at']) || isset($data['ended_at'])) {
                $startDate = isset($data['started_at']) 
                    ? Carbon::parse($data['started_at']) 
                    : $currentExam->started_at;
                    
                $endDate = isset($data['ended_at']) 
                    ? Carbon::parse($data['ended_at']) 
                    : $currentExam->ended_at;
                
                if ($startDate >= $endDate) {
                    throw new \Exception('End date must be after start date');
                }

                // Only validate future date if exam hasn't started yet
                if ($currentExam->started_at > Carbon::now() && $startDate->startOfDay() < Carbon::now()->startOfDay()) {
                    throw new \Exception('Start date must be in the future for upcoming exams');
                }

            }

            return $this->subjectExamRepository->update($id, $data);
        });
    }

    /**
     * Delete exam
     */
    public function deleteExam(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $exam = $this->subjectExamRepository->findWithRelations($id, ['*']);
            
            // Check if exam has started - prevent deletion of active/completed exams with attempts
            if ($exam->started_at <= Carbon::now() && $exam->exam_attempts_count > 0) {
                throw new \Exception('Cannot delete exam that has started and has student attempts');
            }

            return $this->subjectExamRepository->delete($id);
        });
    }

    /**
     * Get exams available for student
     */
    public function getExamsForStudent(int $studentId, array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->getExamsForStudent($studentId, $fields);
    }

    /**
     * Check if student has access to exam
     */
    public function hasStudentAccess(int $examId, int $studentId): bool
    {
        return $this->subjectExamRepository->hasStudentAccess($examId, $studentId);
    }

    /**
     * Get exam statistics
     */
    public function getExamStatistics(int $examId): array
    {
        return $this->subjectExamRepository->getExamStatistics($examId);
    }

    /**
     * Get teacher's exam statistics
     */
    public function getTeacherStatistics(int $teacherId): array
    {
        return $this->subjectExamRepository->getTeacherStatistics($teacherId);
    }

    /**
     * Get exams that need grading
     */
    public function getExamsNeedingGrading(int $teacherId, array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->getExamsNeedingGrading($teacherId, $fields);
    }

    /**
     * Duplicate exam (copy exam for different dates)
     */
    public function duplicateExam(int $examId, array $newData): SubjectExam
    {
        return DB::transaction(function () use ($examId, $newData) {
            $originalExam = $this->subjectExamRepository->findWithRelations($examId, ['*']);
            
            // Prepare data for new exam
            $examData = [
                'subject_id' => $newData['subject_id'] ?? $originalExam->subject_id,
                'name' => $newData['name'] ?? $originalExam->name . ' (Copy)',
                'about' => $newData['about'] ?? $originalExam->about,
                'started_at' => $newData['started_at'],
                'ended_at' => $newData['ended_at'],
            ];

            // Create new exam using existing validation
            $newExam = $this->createExam($examData);

            // Copy questions if requested
            if (isset($newData['copy_questions']) && $newData['copy_questions']) {
                foreach ($originalExam->examQuestions as $question) {
                    $newQuestion = $question->replicate();
                    $newQuestion->subject_exam_id = $newExam->id;
                    $newQuestion->save();

                    // Copy question options if it's multiple choice
                    if ($question->questionOptions) {
                        foreach ($question->questionOptions as $option) {
                            $newOption = $option->replicate();
                            $newOption->exam_question_id = $newQuestion->id;
                            $newOption->save();
                        }
                    }
                }
            }

            return $newExam;
        });
    }

    /**
     * Get exam status
     */
    public function getExamStatus(int $examId): string
    {
        $exam = $this->subjectExamRepository->findWithRelations($examId, ['started_at', 'ended_at']);
        $now = Carbon::now();
        
        if ($now < $exam->started_at) {
            return 'upcoming';
        } elseif ($now >= $exam->started_at && $now <= $exam->ended_at) {
            return 'active';
        } else {
            return 'completed';
        }
    }

    /**
     * Check if exam can be edited
     */
    public function canEditExam(int $examId): bool
    {
        $exam = $this->subjectExamRepository->findWithRelations($examId, ['started_at', 'ended_at']);
        
        // Can edit if exam hasn't started yet
        return $exam->started_at > Carbon::now();
    }

    /**
     * Check if exam can be deleted
     */
    public function canDeleteExam(int $examId): bool
    {
        $exam = $this->subjectExamRepository->findWithRelations($examId, ['started_at']);
        
        // Can delete if exam hasn't started yet or has no attempts
        return $exam->started_at > Carbon::now() || $exam->exam_attempts_count === 0;
    }

    /**
     * Search exams with pagination for modal
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->subjectExamRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Find multiple exams by IDs
     */
    public function findMultipleExams(array $ids, array $fields = ['*']): Collection
    {
        return $this->subjectExamRepository->findManyByIds($ids, $fields);
    }

    /**
     * Delete multiple exams
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                // Use individual delete to apply business logic
                $this->deleteExam($id);
            }
            return true;
        });
    }

    /**
     * Get exam duration in minutes
     */
    public function getExamDuration(int $examId): int
    {
        $exam = $this->subjectExamRepository->findWithRelations($examId, ['started_at', 'ended_at']);
        return $exam->started_at->diffInMinutes($exam->ended_at);
    }

    /**
     * Get remaining time for active exam in minutes
     */
    public function getRemainingTime(int $examId): ?int
    {
        $exam = $this->subjectExamRepository->findWithRelations($examId, ['started_at', 'ended_at']);
        $now = Carbon::now();
        
        if ($now < $exam->started_at) {
            return null; // Exam hasn't started
        }
        
        if ($now > $exam->ended_at) {
            return 0; // Exam has ended
        }
        
        return $now->diffInMinutes($exam->ended_at);
    }

    /**
     * Get teacher's active exams count
     */
    public function getActiveExamsCountForTeacher(int $teacherId): int
    {
        return $this->subjectExamRepository->findByTeacher($teacherId)
            ->filter(function($exam) {
                $now = Carbon::now();
                return $now >= $exam->started_at && $now <= $exam->ended_at;
            })
            ->count();
    }

    /**
     * Get exams for a specific teacher with pagination
     */
    public function getTeacherExams(int $teacherId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectExamRepository->getByTeacher($teacherId, ['*'], $perPage);
    }
}