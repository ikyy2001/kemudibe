<?php

namespace App\Services;

use App\Repositories\TopicRepository;
use App\Models\Topic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TopicService
{
    private TopicRepository $topicRepository;

    public function __construct(TopicRepository $topicRepository)
    {
        $this->topicRepository = $topicRepository;
    }

    /**
     * Get paginated topics
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->topicRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all topics without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->topicRepository->getAll($fields);
    }

    /**
     * Find topic by ID
     */
    public function findTopic(int $id, array $fields = ['*']): Topic
    {
        return $this->topicRepository->findWithSubjects($id, $fields);
    }

    /**
     * Search topics by query
     */
    public function searchTopics(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->topicRepository->searchByNameAndDescription($query, $fields, $perPage);
    }


    /**
     * Create a new topic
     */
    public function createTopic(array $data): Topic
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            return $this->topicRepository->create($data);
        });
    }

    /**
     * Update topic by ID
     */
    public function updateTopic(int $id, array $data): Topic
    {
        return DB::transaction(function () use ($id, $data) {
            $topic = $this->topicRepository->findWithSubjects($id, ['*']);
            $oldPhoto = $topic->getRawOriginal('photo'); // Get raw photo path

            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            $updatedTopic = $this->topicRepository->update($id, $data);

            // Delete old photo if new one was uploaded
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile && $oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            return $updatedTopic;
        });
    }

    /**
     * Delete topic by ID
     */
    public function deleteTopic(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $topic = $this->topicRepository->findWithSubjects($id, ['id', 'photo']);

            // Check if topic has subjects
            $subjectsCount = $topic->subjects()->count();
            if ($subjectsCount > 0) {
                throw new \InvalidArgumentException("Cannot delete topic. This topic has {$subjectsCount} subjects assigned to it.");
            }

            $photoPath = $topic->getRawOriginal('photo'); // Get raw photo path

            if ($photoPath) {
                $this->deletePhoto($photoPath);
            }

            return $this->topicRepository->delete($id);
        });
    }

    /**
     * Search topics with pagination for modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->topicRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Search topics for modal (with count only, no relationships)
     */
    public function searchForModal(string $query = '', array $fields = ['*'], int $limit = 6): Collection
    {
        return $this->topicRepository->searchForModal($query, $fields, $limit);
    }


    /**
     * Upload photo and return storage path
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        return $photo->store('topics', 'public');
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
