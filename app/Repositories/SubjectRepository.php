<?php

namespace App\Repositories;

use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SubjectRepository
{
    /**
     * Get paginated subjects with counts only
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Subject::select($fields)
            ->latest()
            ->withCount(['subjectExams', 'classSubjects'])
            ->paginate($perPage);
    }

    /**
     * Get all subjects without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return Subject::select($fields)
            ->latest()
            ->withCount(['subjectExams', 'classSubjects'])
            ->get();
    }

    /**
     * Find subject by ID with full relationships
     */
    public function findWithRelations(int $id, array $fields = ['*']): Subject
    {
        return Subject::select($fields)
            ->with([
                'topic:id,name,photo,about',
                'teacher:id,name,email,photo,gender',
                'subjectExams:id,subject_id,name,about,started_at,ended_at',
                'classSubjects.classRoom:id,name,photo,grade'
            ])
            ->withCount(['subjectExams', 'classSubjects'])
            ->findOrFail($id);
    }

    /**
     * Find subjects by teacher ID
     */
    public function findByTeacherId(int $teacherId, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Subject::select($fields)
            ->where('teacher_id', $teacherId)
            ->with(['topic:id,name,photo'])
            ->withCount(['subjectExams', 'classSubjects'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find subjects by topic ID
     */
    public function findByTopicId(int $topicId, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Subject::select($fields)
            ->where('topic_id', $topicId)
            ->with(['teacher:id,name,email,photo'])
            ->withCount(['subjectExams', 'classSubjects'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new subject
     */
    public function create(array $data): Subject
    {
        return Subject::create($data);
    }

    /**
     * Update subject by ID
     */
    public function update(int $id, array $data): Subject
    {
        $subject = Subject::findOrFail($id);
        $subject->update($data);
        return $subject->fresh(['topic', 'teacher']);
    }

    /**
     * Delete subject by ID
     */
    public function delete(int $id): bool
    {
        $subject = Subject::findOrFail($id);
        return $subject->delete();
    }

    /**
     * Search subjects by multiple criteria
     */
    public function searchByMultipleCriteria(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Subject::select($fields)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('tagline', 'LIKE', "%{$query}%")
                  ->orWhere('about', 'LIKE', "%{$query}%");
            })
            ->withCount(['subjectExams', 'classSubjects'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search subjects with pagination for frontend modal (with counts only, no relationships)
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = Subject::select($fields)
            ->with('teacher:id,name,email,photo')
            ->withCount(['subjectExams', 'classSubjects'])
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('tagline', 'LIKE', "%{$query}%")
                  ->orWhere('about', 'LIKE', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        
        $subjects = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $subjects,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Find multiple subjects by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return Subject::select($fields)
            ->whereIn('id', $ids)
            ->with(['topic:id,name,photo', 'teacher:id,name,email,photo'])
            ->withCount(['subjectExams', 'classSubjects'])
            ->get();
    }

    /**
     * Get subjects available for classroom assignment
     */
    public function findAvailableForClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return Subject::select($fields)
            ->whereDoesntHave('classSubjects', function ($query) use ($classRoomId) {
                $query->where('class_room_id', $classRoomId);
            })
            ->with(['topic:id,name,photo', 'teacher:id,name,email,photo'])
            ->latest()
            ->get();
    }

    /**
     * Get subjects assigned to a specific teacher
     */
    public function getByTeacher(int $teacherId, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Subject::select($fields)
            ->where('teacher_id', $teacherId)
            ->with(['topic:id,name,photo', 'classSubjects.classRoom:id,name,grade,photo'])
            ->withCount(['subjectExams', 'classSubjects'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search subjects assigned to a specific teacher with pagination
     */
    public function searchTeacherSubjects(int $teacherId, string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = Subject::select($fields)
            ->where('teacher_id', $teacherId)
            ->with(['topic:id,name,photo', 'classSubjects.classRoom:id,name,grade,photo'])
            ->withCount(['subjectExams', 'classSubjects']);

        if (!empty($query)) {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('tagline', 'like', "%{$query}%")
                  ->orWhereHas('topic', function ($topicQuery) use ($query) {
                      $topicQuery->where('name', 'like', "%{$query}%");
                  });
            });
        }

        $total = $queryBuilder->count();
        $subjects = $queryBuilder->latest()
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $subjects,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Find subject by ID (simple find)
     */
    public function find(int $id): Subject
    {
        return Subject::findOrFail($id);
    }

}