<?php

namespace App\Repositories;

use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TopicRepository
{
    /**
     * Get paginated topics with subjects count only
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Topic::select($fields)
            ->latest()
            ->withCount('subjects')
            ->paginate($perPage);
    }

    /**
     * Get all topics without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return Topic::select($fields)
            ->latest()
            ->withCount('subjects')
            ->get();
    }

    /**
     * Find topic by ID with subjects and teachers
     */
    public function findWithSubjects(int $id, array $fields = ['*']): Topic
    {
        return Topic::select($fields)
            ->with([
                'subjects:id,name,photo,teacher_id,topic_id',
                'subjects.teacher:id,name,email,photo'
            ])
            ->findOrFail($id);
    }

    /**
     * Create a new topic
     */
    public function create(array $data): Topic
    {
        return Topic::create($data);
    }

    /**
     * Update topic by ID
     */
    public function update(int $id, array $data): Topic
    {
        $topic = Topic::findOrFail($id);
        $topic->update($data);
        return $topic->fresh();
    }

    /**
     * Delete topic by ID
     */
    public function delete(int $id): bool
    {
        $topic = Topic::findOrFail($id);
        return $topic->delete();
    }

    /**
     * Search topics by name and description
     */
    public function searchByNameAndDescription(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return Topic::select($fields)
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('about', 'LIKE', "%{$query}%")
            ->latest()
            ->withCount('subjects')
            ->paginate($perPage);
    }

    /**
     * Search topics with pagination for frontend modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = Topic::select($fields)
            ->withCount('subjects')
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('about', 'LIKE', "%{$query}%");
        }

        $total = $queryBuilder->count();
        
        $topics = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $topics,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Search topics for frontend modal (with count only, no relationships)
     */
    public function searchForModal(string $query, array $fields = ['*'], int $limit = 6): Collection
    {
        $queryBuilder = Topic::select($fields)
            ->withCount('subjects')
            ->latest()
            ->limit($limit);

        if (!empty($query)) {
            $queryBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('about', 'LIKE', "%{$query}%");
        }

        return $queryBuilder->get();
    }

    /**
     * Find multiple topics by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return Topic::select($fields)
            ->whereIn('id', $ids)
            ->with('subjects')
            ->withCount('subjects')
            ->get();
    }
}