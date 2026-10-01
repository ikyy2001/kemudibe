<?php

namespace App\Services;

use App\Repositories\SubjectRepository;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SubjectService
{
    private SubjectRepository $subjectRepository;

    public function __construct(SubjectRepository $subjectRepository)
    {
        $this->subjectRepository = $subjectRepository;
    }

    /**
     * Get paginated subjects
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all subjects without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->subjectRepository->getAll($fields);
    }

    /**
     * Find subject by ID
     */
    public function findSubject(int $id, array $fields = ['*']): Subject
    {
        return $this->subjectRepository->findWithRelations($id, $fields);
    }

    /**
     * Find subjects by teacher ID
     */
    public function findSubjectsByTeacher(int $teacherId, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectRepository->findByTeacherId($teacherId, $fields, $perPage);
    }

    /**
     * Find subjects by topic ID
     */
    public function findSubjectsByTopic(int $topicId, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectRepository->findByTopicId($topicId, $fields, $perPage);
    }

    /**
     * Search subjects by query
     */
    public function searchSubjects(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectRepository->searchByMultipleCriteria($query, $fields, $perPage);
    }

    /**
     * Find multiple subjects by IDs
     */
    public function findMultipleSubjects(array $ids, array $fields = ['*']): Collection
    {
        return $this->subjectRepository->findManyByIds($ids, $fields);
    }

    /**
     * Get subjects available for classroom assignment
     */
    public function getAvailableForClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->subjectRepository->findAvailableForClassRoom($classRoomId, $fields);
    }

    /**
     * Create a new subject
     */
    public function createSubject(array $data): Subject
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            if (isset($data['content']) && $data['content'] instanceof UploadedFile) {
                $data['content'] = $this->uploadContent($data['content']);
            }

            return $this->subjectRepository->create($data);
        });
    }

    /**
     * Update subject by ID
     */
    public function updateSubject(int $id, array $data): Subject
    {
        return DB::transaction(function () use ($id, $data) {
            $subject = $this->subjectRepository->findWithRelations($id, ['*']);
            $oldPhoto = $subject->getRawOriginal('photo'); // Get raw photo path
            $oldContent = $subject->getRawOriginal('content'); // Get raw content path

            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            if (isset($data['content']) && $data['content'] instanceof UploadedFile) {
                $data['content'] = $this->uploadContent($data['content']);
            }

            $updatedSubject = $this->subjectRepository->update($id, $data);

            // Delete old photo if new one was uploaded
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile && $oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            // Delete old content if new one was uploaded
            if (isset($data['content']) && $data['content'] instanceof UploadedFile && $oldContent) {
                $this->deleteContent($oldContent);
            }

            return $updatedSubject;
        });
    }

    /**
     * Delete subject by ID
     */
    public function deleteSubject(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $subject = $this->subjectRepository->findWithRelations($id, ['id', 'name', 'photo', 'content']);

            // Check if subject is assigned to any classrooms
            if ($subject->classSubjects()->exists()) {
                throw new \Exception('Cannot delete subject that is assigned to classrooms. Please unassign from all classrooms first.');
            }

            $photoPath = $subject->getRawOriginal('photo'); // Get raw photo path
            $contentPath = $subject->getRawOriginal('content'); // Get raw content path

            if ($photoPath) {
                $this->deletePhoto($photoPath);
            }

            if ($contentPath) {
                $this->deleteContent($contentPath);
            }

            return $this->subjectRepository->delete($id);
        });
    }


    /**
     * Assign subject to teacher
     */
    public function assignToTeacher(int $subjectId, int $teacherId): Subject
    {
        return $this->subjectRepository->update($subjectId, ['teacher_id' => $teacherId]);
    }

    /**
     * Reassign subject to different teacher
     */
    public function reassignTeacher(int $subjectId, int $newTeacherId): Subject
    {
        return $this->subjectRepository->update($subjectId, ['teacher_id' => $newTeacherId]);
    }

    /**
     * Remove teacher assignment from subject
     */
    public function unassignTeacher(int $subjectId): Subject
    {
        return $this->subjectRepository->update($subjectId, ['teacher_id' => null]);
    }

    /**
     * Search subjects with pagination for modal (with counts only, no relationships)
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->subjectRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Change subject topic category
     */
    public function changeTopicCategory(int $subjectId, int $topicId): Subject
    {
        return $this->subjectRepository->update($subjectId, ['topic_id' => $topicId]);
    }

    /**
     * Get subjects assigned to a specific teacher
     */
    public function getTeacherSubjects(int $teacherId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->subjectRepository->getByTeacher($teacherId, ['*'], $perPage);
    }

    /**
     * Search subjects assigned to a specific teacher
     */
    public function searchTeacherSubjects(int $teacherId, string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->subjectRepository->searchTeacherSubjects($teacherId, $query, $fields, $page, $perPage);
    }

    /**
     * Upload photo and return storage path
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        return $photo->store('subjects', 'public');
    }

    /**
     * Upload content PDF and return storage path
     */
    private function uploadContent(UploadedFile $content): string
    {
        return $content->store('subjects/content', 'public');
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

    /**
     * Delete content from storage
     */
    private function deleteContent(string $contentPath): void
    {
        if (Storage::disk('public')->exists($contentPath)) {
            Storage::disk('public')->delete($contentPath);
        }
    }
}
