<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TeacherRepository
{
    /**
     * Get paginated teachers with subjects count only
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('teacher')
            ->with('roles')
            ->latest()
            ->withCount('subjects')
            ->paginate($perPage);
    }

    /**
     * Get all teachers without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('teacher')
            ->with('roles')
            ->latest()
            ->withCount('subjects')
            ->get();
    }

    /**
     * Find teacher by ID with subjects
     */
    public function findWithSubjects(int $id, array $fields = ['*']): User
    {
        return User::select($fields)
            ->role('teacher')
            ->with([
                'roles',
                'subjects:id,name,photo,tagline,teacher_id,topic_id',
                'subjects.topic:id,name,photo'
            ])
            ->findOrFail($id);
    }

    /**
     * Create a new teacher
     */
    public function create(array $data): User
    {
        $teacher = User::create($data);
        $teacher->assignRole('teacher');
        return $teacher;
    }

    /**
     * Update teacher by ID
     */
    public function update(int $id, array $data): User
    {
        $teacher = User::role('teacher')->findOrFail($id);
        $teacher->update($data);
        return $teacher->fresh();
    }

    /**
     * Delete teacher by ID
     */
    public function delete(int $id): bool
    {
        $teacher = User::role('teacher')->findOrFail($id);
        return $teacher->delete();
    }

    /**
     * Search teachers by name and email
     */
    public function searchByNameAndEmail(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('teacher')
            ->with('roles')
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->latest()
            ->withCount('subjects')
            ->paginate($perPage);
    }

    /**
     * Search teachers with pagination for frontend modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = User::select($fields)
            ->role('teacher')
            ->withCount('subjects')
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        
        $teachers = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $teachers,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Search teachers for frontend modal (with count only, no relationships)
     */
    public function searchForModal(string $query, array $fields = ['*'], int $limit = 6): Collection
    {
        $queryBuilder = User::select($fields)
            ->role('teacher')
            ->withCount('subjects')
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
     * Find multiple teachers by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('teacher')
            ->whereIn('id', $ids)
            ->with('subjects')
            ->withCount('subjects')
            ->get();
    }

    /**
     * Get teachers by gender
     */
    public function findByGender(string $gender, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role('teacher')
            ->where('gender', $gender)
            ->latest()
            ->withCount('subjects')
            ->paginate($perPage);
    }

    /**
     * Get teachers without assigned subjects
     */
    public function getUnassignedTeachers(array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->role('teacher')
            ->doesntHave('subjects')
            ->latest()
            ->get();
    }
}