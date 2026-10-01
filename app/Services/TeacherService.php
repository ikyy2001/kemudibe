<?php

namespace App\Services;

use App\Repositories\TeacherRepository;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TeacherService
{
    private TeacherRepository $teacherRepository;

    public function __construct(TeacherRepository $teacherRepository)
    {
        $this->teacherRepository = $teacherRepository;
    }

    /**
     * Get paginated teachers
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->teacherRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all teachers without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->teacherRepository->getAll($fields);
    }

    /**
     * Find teacher by ID
     */
    public function findTeacher(int $id, array $fields = ['*']): User
    {
        return $this->teacherRepository->findWithSubjects($id, $fields);
    }

    /**
     * Search teachers by query
     */
    public function searchTeachers(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->teacherRepository->searchByNameAndEmail($query, $fields, $perPage);
    }

    /**
     * Find multiple teachers by IDs
     */
    public function findMultipleTeachers(array $ids, array $fields = ['*']): Collection
    {
        return $this->teacherRepository->findManyByIds($ids, $fields);
    }

    /**
     * Get teachers by gender
     */
    public function findTeachersByGender(string $gender, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->teacherRepository->findByGender($gender, $fields, $perPage);
    }

    /**
     * Get teachers without assigned subjects
     */
    public function getUnassignedTeachers(array $fields = ['*']): Collection
    {
        return $this->teacherRepository->getUnassignedTeachers($fields);
    }

    /**
     * Create a new teacher
     */
    public function createTeacher(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Hash password if provided
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Handle photo upload
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            return $this->teacherRepository->create($data);
        });
    }

    /**
     * Update teacher by ID
     */
    public function updateTeacher(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $teacher = $this->teacherRepository->findWithSubjects($id, ['*']);
            $oldPhoto = $teacher->getRawOriginal('photo'); // Get raw photo path

            // Hash password if provided
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Handle photo upload
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            $updatedTeacher = $this->teacherRepository->update($id, $data);

            // Delete old photo if new one was uploaded
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile && $oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            return $updatedTeacher;
        });
    }

    /**
     * Delete teacher by ID
     */
    public function deleteTeacher(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $teacher = $this->teacherRepository->findWithSubjects($id, ['photo']);
            $photoPath = $teacher->getRawOriginal('photo'); // Get raw photo path

            if ($photoPath) {
                $this->deletePhoto($photoPath);
            }

            return $this->teacherRepository->delete($id);
        });
    }

    /**
     * Search teachers with pagination for modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->teacherRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Search teachers for modal (with count only, no relationships)
     */
    public function searchForModal(string $query = '', array $fields = ['*'], int $limit = 6): Collection
    {
        return $this->teacherRepository->searchForModal($query, $fields, $limit);
    }


    /**
     * Upload photo and return storage path
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        return $photo->store('teachers', 'public');
    }

    /**
     * Delete photo from storage
     */
    private function deletePhoto(string $photoPath): void
    {
        if (Storage::disk('public')->exists($photoPath)) {
            Storage::disk('public')->delete($photoPath);
        }
    }
}
