<?php

namespace App\Repositories;

use App\Models\SubjectExam;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class SubjectExamRepository
{
    /**
     * Get paginated exams
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return SubjectExam::select($fields)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all exams without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Find exam by ID with full relationships
     */
    public function findWithRelations(int $id, array $fields = ['*']): SubjectExam
    {
        return SubjectExam::select($fields)
            ->with([
                'subject:id,name,photo,tagline,about,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'examQuestions:id,subject_exam_id,name,timer,type,points',
                'examAttempts:id,student_id,subject_exam_id,is_completed,total_questions,answered_questions,completed_at'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->findOrFail($id);
    }

    /**
     * Find exam by ID with questions and options
     */
    public function findWithQuestions(int $id): SubjectExam
    {
        return SubjectExam::with([
                'subject:id,name,photo,tagline,about,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'examQuestions:id,subject_exam_id,name,timer,type,points',
                'examQuestions.questionOptions:id,exam_question_id,name,is_correct',
                'examAttempts' => function($query) {
                    $query->with('student:id,name,email,photo')->orderBy('is_completed', 'desc')->orderBy('created_at', 'desc');
                }
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->findOrFail($id);
    }

    /**
     * Create a new exam
     */
    public function create(array $data): SubjectExam
    {
        return SubjectExam::create($data);
    }

    /**
     * Update exam by ID
     */
    public function update(int $id, array $data): SubjectExam
    {
        $exam = SubjectExam::findOrFail($id);
        $exam->update($data);
        return $exam->fresh();
    }

    /**
     * Delete exam by ID
     */
    public function delete(int $id): bool
    {
        $exam = SubjectExam::findOrFail($id);
        return $exam->delete();
    }

    /**
     * Find exams by subject ID
     */
    public function findBySubject(int $subjectId, array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->where('subject_id', $subjectId)
            ->with([
                'subject:id,name,photo,tagline,about,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount([
                'examQuestions',
                'examAttempts',
                'examAttempts as completed_attempts_count' => function($query) {
                    $query->where('is_completed', true);
                },
                'examAttempts as in_progress_attempts_count' => function($query) {
                    $query->where('is_completed', false);
                }
            ])
            ->latest()
            ->get();
    }

    /**
     * Find exams by teacher ID (through subject relationship)
     */
    public function findByTeacher(int $teacherId, array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->whereHas('subject', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Find exams by topic ID (through subject relationship)
     */
    public function findByTopic(int $topicId, array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->whereHas('subject', function($query) use ($topicId) {
                $query->where('topic_id', $topicId);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Get active exams (currently running)
     */
    public function getActiveExams(array $fields = ['*']): Collection
    {
        $now = Carbon::now();
        return SubjectExam::select($fields)
            ->where('started_at', '<=', $now)
            ->where('ended_at', '>=', $now)
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,email,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Get upcoming exams
     */
    public function getUpcomingExams(array $fields = ['*']): Collection
    {
        $now = Carbon::now();
        return SubjectExam::select($fields)
            ->where('started_at', '>', $now)
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,email,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Get completed exams
     */
    public function getCompletedExams(array $fields = ['*']): Collection
    {
        $now = Carbon::now();
        return SubjectExam::select($fields)
            ->where('ended_at', '<', $now)
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,email,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest('ended_at')
            ->get();
    }

    /**
     * Find exams by date range
     */
    public function findByDateRange(string $startDate, string $endDate, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return SubjectExam::select($fields)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->orWhereBetween('ended_at', [$startDate, $endDate])
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,email,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search exams by name or subject name
     */
    public function searchByNameOrSubject(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return SubjectExam::select($fields)
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('about', 'LIKE', "%{$query}%")
            ->orWhereHas('subject', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('tagline', 'LIKE', "%{$query}%");
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search exams with pagination for frontend modal
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = SubjectExam::select($fields)
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('about', 'LIKE', "%{$query}%")
                         ->orWhereHas('subject', function($q) use ($query) {
                             $q->where('name', 'LIKE', "%{$query}%")
                               ->orWhere('tagline', 'LIKE', "%{$query}%");
                         });
        }

        $total = $queryBuilder->count();
        
        $exams = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $exams,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Find multiple exams by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->whereIn('id', $ids)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->get();
    }

    /**
     * Get exam statistics
     */
    public function getExamStatistics(int $examId): array
    {
        $exam = SubjectExam::withCount([
            'examQuestions',
            'examAttempts',
            'examAttempts as completed_attempts_count' => function($query) {
                $query->where('is_completed', true);
            }
        ])->findOrFail($examId);

        $totalQuestions = $exam->exam_questions_count;
        $totalAttempts = $exam->exam_attempts_count;
        $completedAttempts = $exam->completed_attempts_count;
        $completionRate = $totalAttempts > 0 ? round(($completedAttempts / $totalAttempts) * 100, 2) : 0;

        // Get average score from completed attempts
        $averageScore = $exam->examAttempts()
            ->where('is_completed', true)
            ->avg('answered_questions');

        return [
            'total_questions' => $totalQuestions,
            'total_attempts' => $totalAttempts,
            'completed_attempts' => $completedAttempts,
            'in_progress_attempts' => $totalAttempts - $completedAttempts,
            'completion_rate' => $completionRate,
            'average_answered_questions' => $averageScore ? round($averageScore, 2) : 0,
            'exam_status' => $this->getExamStatus($exam)
        ];
    }

    /**
     * Get exam status based on dates
     */
    private function getExamStatus(SubjectExam $exam): string
    {
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
     * Get exams available for student (in assigned classrooms)
     */
    public function getExamsForStudent(int $studentId, array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->whereHas('subject.classSubjects.classRoom.classStudents', function($query) use ($studentId) {
                $query->where('student_id', $studentId);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,email,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->get();
    }

    /**
     * Check if student has access to exam
     */
    public function hasStudentAccess(int $examId, int $studentId): bool
    {
        return SubjectExam::where('id', $examId)
            ->whereHas('subject.classSubjects.classRoom.classStudents', function($query) use ($studentId) {
                $query->where('student_id', $studentId);
            })
            ->exists();
    }

    /**
     * Get teacher's exam statistics
     */
    public function getTeacherStatistics(int $teacherId): array
    {
        $totalExams = SubjectExam::whereHas('subject', function($query) use ($teacherId) {
            $query->where('teacher_id', $teacherId);
        })->count();

        $activeExams = SubjectExam::whereHas('subject', function($query) use ($teacherId) {
            $query->where('teacher_id', $teacherId);
        })
        ->where('started_at', '<=', Carbon::now())
        ->where('ended_at', '>=', Carbon::now())
        ->count();

        $upcomingExams = SubjectExam::whereHas('subject', function($query) use ($teacherId) {
            $query->where('teacher_id', $teacherId);
        })
        ->where('started_at', '>', Carbon::now())
        ->count();

        $completedExams = SubjectExam::whereHas('subject', function($query) use ($teacherId) {
            $query->where('teacher_id', $teacherId);
        })
        ->where('ended_at', '<', Carbon::now())
        ->count();

        return [
            'total_exams' => $totalExams,
            'active_exams' => $activeExams,
            'upcoming_exams' => $upcomingExams,
            'completed_exams' => $completedExams
        ];
    }

    /**
     * Get exams that need grading (have ungraded questions)
     */
    public function getExamsNeedingGrading(int $teacherId, array $fields = ['*']): Collection
    {
        return SubjectExam::select($fields)
            ->whereHas('subject', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->whereHas('examQuestions.questionAnswers', function($query) {
                $query->whereNull('points_earned')
                      ->orWhere('points_earned', 0);
            })
            ->where('ended_at', '<', Carbon::now())
            ->with([
                'subject:id,name,photo,tagline',
                'examQuestions' => function($query) {
                    $query->whereHas('questionAnswers', function($q) {
                        $q->whereNull('points_earned')
                          ->orWhere('points_earned', 0);
                    });
                }
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest('ended_at')
            ->get();
    }

    /**
     * Get exams for a specific teacher with pagination
     */
    public function getByTeacher(int $teacherId, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return SubjectExam::select($fields)
            ->whereHas('subject', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.topic:id,name,photo'
            ])
            ->withCount(['examQuestions', 'examAttempts'])
            ->latest()
            ->paginate($perPage);
    }
}