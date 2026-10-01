<?php

namespace App\Repositories;

use App\Models\ClassStudent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassStudentRepository
{
    /**
     * Get paginated enrollments
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassStudent::select($fields)
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all enrollments without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->latest()
            ->get();
    }

    /**
     * Find enrollment by ID with full relationships
     */
    public function findWithRelations(int $id, array $fields = ['*']): ClassStudent
    {
        return ClassStudent::select($fields)
            ->with([
                'student:id,name,email,photo,gender',
                'classRoom:id,name,photo,grade'
            ])
            ->findOrFail($id);
    }

    /**
     * Create a new enrollment
     */
    public function create(array $data): ClassStudent
    {
        return ClassStudent::create($data);
    }

    /**
     * Update enrollment by ID
     */
    public function update(int $id, array $data): ClassStudent
    {
        $enrollment = ClassStudent::findOrFail($id);
        $enrollment->update($data);
        return $enrollment->fresh();
    }

    /**
     * Delete enrollment by ID
     */
    public function delete(int $id): bool
    {
        $enrollment = ClassStudent::findOrFail($id);
        return $enrollment->delete();
    }

    /**
     * Find enrollments by student ID
     */
    public function findByStudent(int $studentId, array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->where('student_id', $studentId)
            ->with(['classRoom:id,name,photo,grade'])
            ->latest()
            ->get();
    }

    /**
     * Find enrollments by classroom ID
     */
    public function findByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->where('class_room_id', $classRoomId)
            ->with(['student:id,name,email,photo,gender'])
            ->latest()
            ->get();
    }

    /**
     * Check if student is already enrolled in classroom
     */
    public function isStudentEnrolled(int $studentId, int $classRoomId): bool
    {
        return ClassStudent::where('student_id', $studentId)
            ->where('class_room_id', $classRoomId)
            ->exists();
    }

    /**
     * Find specific enrollment by student and classroom
     */
    public function findByStudentAndClassRoom(int $studentId, int $classRoomId): ?ClassStudent
    {
        return ClassStudent::where('student_id', $studentId)
            ->where('class_room_id', $classRoomId)
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->first();
    }

    /**
     * Get students who passed in specific classroom
     */
    public function findPassedStudentsByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->where('class_room_id', $classRoomId)
            ->where('has_passed', true)
            ->with(['student:id,name,email,photo,gender'])
            ->latest()
            ->get();
    }

    /**
     * Get students who failed in specific classroom
     */
    public function findFailedStudentsByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->where('class_room_id', $classRoomId)
            ->where('has_passed', false)
            ->with(['student:id,name,email,photo,gender'])
            ->latest()
            ->get();
    }

    /**
     * Search enrollments by student name or classroom name
     */
    public function searchByStudentOrClassRoom(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassStudent::select($fields)
            ->whereHas('student', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->orWhereHas('classRoom', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            })
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search enrollments with pagination for frontend modal
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = ClassStudent::select($fields)
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->latest();

        if (!empty($query)) {
            $queryBuilder->whereHas('student', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->orWhereHas('classRoom', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        
        $enrollments = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $enrollments,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Find multiple enrollments by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->whereIn('id', $ids)
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->get();
    }

    /**
     * Get enrollments by grade level
     */
    public function findByGrade(int $grade, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassStudent::select($fields)
            ->whereHas('classRoom', function($q) use ($grade) {
                $q->where('grade', $grade);
            })
            ->with(['student:id,name,email,photo,gender', 'classRoom:id,name,photo,grade'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get enrollment statistics for classroom
     */
    public function getClassRoomStatistics(int $classRoomId): array
    {
        $total = ClassStudent::where('class_room_id', $classRoomId)->count();
        $passed = ClassStudent::where('class_room_id', $classRoomId)->where('has_passed', true)->count();
        $failed = ClassStudent::where('class_room_id', $classRoomId)->where('has_passed', false)->count();
        $pending = $total - $passed - $failed;

        return [
            'total_students' => $total,
            'passed_students' => $passed,
            'failed_students' => $failed,
            'pending_students' => $pending,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get available students for enrollment in specific classroom
     */
    public function getAvailableStudentsForClassRoom(int $classRoomId): Collection
    {
        return User::role('student')
            ->whereDoesntHave('classStudents', function($q) use ($classRoomId) {
                $q->where('class_room_id', $classRoomId);
            })
            ->select(['id', 'name', 'email', 'photo', 'gender'])
            ->latest()
            ->get();
    }

    /**
     * Bulk enroll students in classroom
     */
    public function bulkEnroll(int $classRoomId, array $studentIds, array $additionalData = []): array
    {
        $enrollments = [];
        foreach ($studentIds as $studentId) {
            // Check if already enrolled
            if (!$this->isStudentEnrolled($studentId, $classRoomId)) {
                $enrollments[] = $this->create(array_merge([
                    'student_id' => $studentId,
                    'class_room_id' => $classRoomId,
                ], $additionalData));
            }
        }
        return $enrollments;
    }

    // /**
    //  * Bulk unenroll students from classroom - UNUSED
    //  */
    // public function bulkUnenroll(int $classRoomId, array $studentIds): int
    // {
    //     return ClassStudent::where('class_room_id', $classRoomId)
    //         ->whereIn('student_id', $studentIds)
    //         ->delete();
    // }

    /**
     * Get student's enrolled classrooms with detailed information
     */
    public function getStudentClassRooms(int $studentId, array $fields = ['*']): Collection
    {
        return ClassStudent::select($fields)
            ->where('student_id', $studentId)
            ->with([
                'classRoom:id,name,photo,grade',
                'classRoom.classSubjects.subject:id,name,photo,tagline',
                'classRoom.classSubjects.subject.teacher:id,name,email,photo'
            ])
            ->latest()
            ->get()
            ->each(function($classStudent) {
                // Load counts for the classRoom relationship
                $classStudent->classRoom->loadCount(['classStudents', 'classSubjects']);
            });
    }
}