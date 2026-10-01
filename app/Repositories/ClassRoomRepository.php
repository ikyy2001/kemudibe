<?php

namespace App\Repositories;

use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassRoomRepository
{
    /**
     * Get paginated classrooms with counts
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassRoom::select($fields)
            ->latest()
            ->withCount(['classStudents', 'classSubjects'])
            ->paginate($perPage);
    }

    /**
     * Get all classrooms without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return ClassRoom::select($fields)
            ->latest()
            ->withCount(['classStudents', 'classSubjects'])
            ->get();
    }

    /**
     * Find classroom by ID with full relationships
     */
    public function findWithRelations(int $id, array $fields = ['*']): ClassRoom
    {
        return ClassRoom::select($fields)
            ->with([
                'classStudents.student:id,name,email,photo,gender',
                'classSubjects.subject' => function ($query) {
                    $query->select(['id', 'name', 'photo', 'tagline', 'content', 'teacher_id'])
                          ->with('teacher:id,name,email,photo')
                          ->withCount('subjectExams');
                }
            ])
            ->withCount(['classStudents', 'classSubjects'])
            ->findOrFail($id);
    }

    /**
     * Find classrooms by grade level
     */
    public function findByGrade(int $grade, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassRoom::select($fields)
            ->where('grade', $grade)
            ->withCount(['classStudents', 'classSubjects'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get students enrolled in classroom
     */
    public function getEnrolledStudents(int $classRoomId, array $fields = ['*']): Collection
    {
        return ClassRoom::select(['id'])
            ->with([
                'classStudents' => function ($query) use ($fields) {
                    $query->select(['id', 'student_id', 'class_room_id', 'has_passed', 'rapport'])
                          ->with(['student' => function ($studentQuery) use ($fields) {
                              $studentQuery->select($fields);
                          }]);
                }
            ])
            ->findOrFail($classRoomId)
            ->classStudents;
    }

    /**
     * Get subjects assigned to classroom
     */
    public function getAssignedSubjects(int $classRoomId, array $fields = ['*']): Collection
    {
        return ClassRoom::select(['id'])
            ->with([
                'classSubjects.subject' => function ($query) use ($fields) {
                    $query->select($fields)
                          ->with(['teacher:id,name,email,photo', 'topic:id,name,photo'])
                          ->withCount('subjectExams');
                }
            ])
            ->findOrFail($classRoomId)
            ->classSubjects;
    }

    /**
     * Create a new classroom
     */
    public function create(array $data): ClassRoom
    {
        return ClassRoom::create($data);
    }

    /**
     * Update classroom by ID
     */
    public function update(int $id, array $data): ClassRoom
    {
        $classRoom = ClassRoom::findOrFail($id);
        $classRoom->update($data);
        return $classRoom->fresh();
    }

    /**
     * Delete classroom by ID
     */
    public function delete(int $id): bool
    {
        $classRoom = ClassRoom::findOrFail($id);
        return $classRoom->delete();
    }

    /**
     * Search classrooms by name and grade
     */
    public function searchByNameAndGrade(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassRoom::select($fields)
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('grade', 'LIKE', "%{$query}%")
            ->withCount(['classStudents', 'classSubjects'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find multiple classrooms by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return ClassRoom::select($fields)
            ->whereIn('id', $ids)
            ->withCount(['classStudents', 'classSubjects'])
            ->get();
    }

    /**
     * Enroll student in classroom
     */
    public function enrollStudent(int $classRoomId, int $studentId, array $additionalData = []): ClassStudent
    {
        $classRoom = ClassRoom::findOrFail($classRoomId);
        return $classRoom->classStudents()->create(array_merge([
            'student_id' => $studentId,
            'class_room_id' => $classRoomId,
        ], $additionalData));
    }

    /**
     * Remove student from classroom
     */
    public function unenrollStudent(int $classRoomId, int $studentId): bool
    {
        $classRoom = ClassRoom::findOrFail($classRoomId);
        return $classRoom->classStudents()
            ->where('student_id', $studentId)
            ->delete() > 0;
    }

    /**
     * Assign subject to classroom
     */
    public function assignSubject(int $classRoomId, int $subjectId): ClassSubject
    {
        $classRoom = ClassRoom::findOrFail($classRoomId);
        return $classRoom->classSubjects()->create([
            'class_room_id' => $classRoomId,
            'subject_id' => $subjectId,
        ]);
    }

    /**
     * Remove subject from classroom
     */
    public function unassignSubject(int $classRoomId, int $subjectId): bool
    {
        $classRoom = ClassRoom::findOrFail($classRoomId);
        return $classRoom->classSubjects()
            ->where('subject_id', $subjectId)
            ->delete() > 0;
    }

    /**
     * Search classrooms with pagination for frontend modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = ClassRoom::select($fields)
            ->withCount(['classStudents', 'classSubjects'])
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('grade', 'LIKE', "%{$query}%");
        }

        $total = $queryBuilder->count();
        
        $classRooms = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $classRooms,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Search classrooms for frontend modal (with count only, no relationships)
     */
    public function searchForModal(string $query, array $fields = ['*'], int $limit = 6): Collection
    {
        $queryBuilder = ClassRoom::select($fields)
            ->withCount(['classStudents', 'classSubjects'])
            ->latest()
            ->limit($limit);

        if (!empty($query)) {
            $queryBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('grade', 'LIKE', "%{$query}%");
        }

        return $queryBuilder->get();
    }

    /**
     * Get students available for enrollment
     */
    public function findAvailableStudents(int $classRoomId): Collection
    {
        return \App\Models\User::whereHas('roles', function ($query) {
                $query->where('name', 'student');
            })
            ->whereDoesntHave('classStudents', function ($query) use ($classRoomId) {
                $query->where('class_room_id', $classRoomId);
            })
            ->select(['id', 'name', 'email', 'photo'])
            ->get();
    }
}