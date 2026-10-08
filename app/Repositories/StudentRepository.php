<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StudentRepository
{
    /**
     * Get paginated students with enrollments count only
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('student')
            ->with('roles')
            ->latest()
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->paginate($perPage);
    }

    /**
     * Get all students without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('student')
            ->with('roles')
            ->latest()
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->get();
    }

    /**
     * Find student by ID (basic method for validation)
     */
    public function findStudent(int $id, array $fields = ['*']): User
    {
        return User::select($fields)
            ->role('student')
            ->findOrFail($id);
    }

    /**
     * Find student by ID with enrollments and exam history
     */
    public function findWithEnrollments(int $id, array $fields = ['*']): User
    {
        return User::select($fields)
            ->role('student')
            ->with([
                'roles',
                'classStudents.classRoom:id,name,photo,grade',
                'examAttempts.subjectExam:id,name,subject_id',
                'examAttempts.subjectExam.subject:id,name,photo',
                'questionAnswers' => function($query) {
                    $query->select('id', 'student_id', 'exam_question_id', 'has_passed', 'points_earned')
                          ->with('examQuestion:id,name,points,subject_exam_id');
                }
            ])
            ->findOrFail($id);
    }

    /**
     * Find student by ID with classroom statistics
     */
    public function findWithClassroomStats(int $id, array $fields = ['*']): User
    {
        $student = User::select($fields)
            ->role('student')
            ->with([
                'roles',
                'classStudents.classRoom' => function($query) {
                    $query->select('id', 'name', 'photo', 'grade')
                          ->withCount(['classStudents', 'classSubjects']);
                }
            ])
            ->findOrFail($id);

        // Calculate total exam statistics
        $totalExamsAvailable = User::where('id', $id)
            ->role('student')
            ->withCount([
                'classStudents as total_exams_available' => function($query) {
                    $query->join('class_rooms', 'class_students.class_room_id', '=', 'class_rooms.id')
                          ->join('class_subjects', 'class_rooms.id', '=', 'class_subjects.class_room_id')
                          ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                          ->join('subject_exams', 'subjects.id', '=', 'subject_exams.subject_id');
                }
            ])
            ->first()
            ->total_exams_available;

        $totalExamsCompleted = User::where('id', $id)
            ->role('student')
            ->withCount([
                'examAttempts as total_exams_completed' => function($query) {
                    $query->where('is_completed', true);
                }
            ])
            ->first()
            ->total_exams_completed;

        // Add the statistics to the student object
        $student->total_exams_available = $totalExamsAvailable;
        $student->total_exams_completed = $totalExamsCompleted;

        return $student;
    }

    /**
     * Create a new student
     */
    public function create(array $data): User
    {
        $student = User::create($data);
        $student->assignRole('student');
        return $student;
    }

    /**
     * Update student by ID
     */
    public function update(int $id, array $data): User
    {
        $student = User::role('student')->findOrFail($id);
        $student->update($data);
        return $student->fresh();
    }

    /**
     * Delete student by ID
     */
    public function delete(int $id): bool
    {
        $student = User::role('student')->findOrFail($id);
        return $student->delete();
    }

    /**
     * Search students by name and email
     */
    public function searchByNameAndEmail(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('student')
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->latest()
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->paginate($perPage);
    }

    /**
     * Search students with pagination for frontend modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = User::select($fields)
            ->role('student')
            ->distinct()
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        
        $students = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $students,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Search students for frontend modal (with count only, no relationships)
     */
    public function searchForModal(string $query, array $fields = ['*'], int $limit = 6): Collection
    {
        $queryBuilder = User::select($fields)
            ->role('student')
            ->distinct()
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->latest()
            ->limit($limit);

        if (!empty($query)) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            });
        }

        return $queryBuilder->get();
    }

    /**
     * Find multiple students by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('student')
            ->whereIn('id', $ids)
            ->with(['classStudents.classRoom', 'examAttempts'])
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->get();
    }

    /**
     * Get students by gender
     */
    public function findByGender(string $gender, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('student')
            ->where('gender', $gender)
            ->latest()
            ->withCount(['classStudents', 'questionAnswers', 'examAttempts'])
            ->paginate($perPage);
    }

    /**
     * Get students not enrolled in any classroom
     */
    public function getUnenrolledStudents(array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('student')
            ->doesntHave('classStudents')
            ->latest()
            ->get();
    }

    /**
     * Get students enrolled in specific classroom
     */
    public function findByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('student')
            ->whereHas('classStudents', function($query) use ($classRoomId) {
                $query->where('class_room_id', $classRoomId);
            })
            ->with(['classStudents' => function($query) use ($classRoomId) {
                $query->where('class_room_id', $classRoomId)
                      ->select('id', 'student_id', 'class_room_id', 'has_passed', 'rapport');
            }])
            ->latest()
            ->get();
    }

    /**
     * Get students with their exam performance
     */
    public function findWithExamPerformance(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('student')
            ->with([
                'examAttempts' => function($query) {
                    $query->select('id', 'student_id', 'subject_exam_id', 'is_completed', 'total_questions', 'answered_questions', 'completed_at')
                          ->where('is_completed', true);
                }
            ])
            ->withCount([
                'examAttempts',
                'examAttempts as completed_exams_count' => function($query) {
                    $query->where('is_completed', true);
                }
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get student profile with detailed enrollment and exam statistics
     */
    public function getStudentProfile(int $studentId, array $fields = ['*']): User
    {
        return User::select($fields)
            ->role('student')
            ->with([
                'roles',
                'classStudents.classRoom:id,name,photo,grade',
                'classStudents.classRoom.classSubjects.subject:id,name,photo',
                'examAttempts' => function($query) {
                    $query->select('id', 'student_id', 'subject_exam_id', 'is_completed', 'total_questions', 'answered_questions', 'completed_at')
                          ->with('subjectExam:id,name,subject_id')
                          ->with('subjectExam.subject:id,name,photo')
                          ->latest();
                },
                'questionAnswers' => function($query) {
                    $query->select('id', 'student_id', 'exam_question_id', 'has_passed', 'points_earned')
                          ->with('examQuestion:id,name,points,subject_exam_id');
                }
            ])
            ->withCount([
                'classStudents',
                'examAttempts',
                'examAttempts as completed_exams_count' => function($query) {
                    $query->where('is_completed', true);
                },
                'questionAnswers',
                'questionAnswers as correct_answers_count' => function($query) {
                    $query->where('has_passed', true);
                }
            ])
            ->findOrFail($studentId);
    }

    /**
     * Check if teacher has access to student (through shared subjects/classrooms)
     */
    public function teacherHasAccessToStudent(int $teacherId, int $studentId): bool
    {
        return \App\Models\ClassStudent::where('student_id', $studentId)
            ->whereHas('classRoom.classSubjects.subject', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })->exists();
    }

    /**
     * Get student profile with teacher-scoped data filtering
     */
    public function getStudentProfileForTeacher(int $studentId, int $teacherId, array $fields = ['*']): User
    {
        return User::select($fields)
            ->role('student')
            ->with([
                'roles',
                // Only enrollments in classrooms with teacher's subjects
                'classStudents' => function($query) use ($teacherId) {
                    $query->whereHas('classRoom.classSubjects.subject', function($q) use ($teacherId) {
                        $q->where('teacher_id', $teacherId);
                    })->with([
                        'classRoom:id,name,photo,grade',
                        'classRoom.classSubjects' => function($q) use ($teacherId) {
                            $q->whereHas('subject', function($subq) use ($teacherId) {
                                $subq->where('teacher_id', $teacherId);
                            })->with('subject:id,name,photo,tagline');
                        }
                    ]);
                },
                // Only exam attempts for teacher's subjects
                'examAttempts' => function($query) use ($teacherId) {
                    $query->select('id', 'student_id', 'subject_exam_id', 'is_completed', 'total_questions', 'answered_questions', 'completed_at')
                          ->whereHas('subjectExam.subject', function($q) use ($teacherId) {
                              $q->where('teacher_id', $teacherId);
                          })
                          ->with([
                              'subjectExam:id,name,subject_id',
                              'subjectExam.subject:id,name,photo'
                          ])
                          ->latest();
                },
                // Only question answers for teacher's subjects
                'questionAnswers' => function($query) use ($teacherId) {
                    $query->select('id', 'student_id', 'exam_question_id', 'has_passed', 'points_earned')
                          ->whereHas('examQuestion.subjectExam.subject', function($q) use ($teacherId) {
                              $q->where('teacher_id', $teacherId);
                          })
                          ->with('examQuestion:id,name,points,subject_exam_id');
                }
            ])
            ->withCount([
                // Only count enrollments in teacher's subjects
                'classStudents as teacher_class_students_count' => function($query) use ($teacherId) {
                    $query->whereHas('classRoom.classSubjects.subject', function($q) use ($teacherId) {
                        $q->where('teacher_id', $teacherId);
                    });
                },
                // Only count exam attempts for teacher's subjects
                'examAttempts as teacher_exam_attempts_count' => function($query) use ($teacherId) {
                    $query->whereHas('subjectExam.subject', function($q) use ($teacherId) {
                        $q->where('teacher_id', $teacherId);
                    });
                },
                // Only count completed exams for teacher's subjects
                'examAttempts as teacher_completed_exams_count' => function($query) use ($teacherId) {
                    $query->where('is_completed', true)
                          ->whereHas('subjectExam.subject', function($q) use ($teacherId) {
                              $q->where('teacher_id', $teacherId);
                          });
                },
                // Only count answers for teacher's subjects
                'questionAnswers as teacher_question_answers_count' => function($query) use ($teacherId) {
                    $query->whereHas('examQuestion.subjectExam.subject', function($q) use ($teacherId) {
                        $q->where('teacher_id', $teacherId);
                    });
                },
                // Only count correct answers for teacher's subjects
                'questionAnswers as teacher_correct_answers_count' => function($query) use ($teacherId) {
                    $query->where('has_passed', true)
                          ->whereHas('examQuestion.subjectExam.subject', function($q) use ($teacherId) {
                              $q->where('teacher_id', $teacherId);
                          });
                }
            ])
            ->findOrFail($studentId);
    }
}