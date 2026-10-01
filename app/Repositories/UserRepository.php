<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * Get paginated users
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->with('roles')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all users without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return User::select($fields)
            ->with('roles')
            ->latest()
            ->get();
    }

    /**
     * Search users by name and email
     */
    public function searchByNameAndEmail(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->with('roles')
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get users by gender
     */
    public function findByGender(string $gender, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->with('roles')
            ->where('gender', $gender)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get users by role
     */
    public function findByRole(string $role, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return User::select($fields)
            ->role($role)
            ->with('roles')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search users with pagination for frontend modal
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = User::select($fields)
            ->with('roles')
            ->latest();

        if (!empty($query)) {
            $queryBuilder->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        
        $users = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $users,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }
}