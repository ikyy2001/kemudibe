<?php

namespace App\Services;

use App\Repositories\ClassSubjectRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\ClassRoomRepository;
use App\Models\ClassSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ClassSubjectService
{
    private ClassSubjectRepository $classSubjectRepository;
    private SubjectRepository $subjectRepository;
    private ClassRoomRepository $classRoomRepository;

    public function __construct(
        ClassSubjectRepository $classSubjectRepository,
        SubjectRepository $subjectRepository,
        ClassRoomRepository $classRoomRepository
    ) {
        $this->classSubjectRepository = $classSubjectRepository;
        $this->subjectRepository = $subjectRepository;
        $this->classRoomRepository = $classRoomRepository;
    }

    /**
     * Get paginated subject assignments
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classSubjectRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all subject assignments without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->getAll($fields);
    }

    /**
     * Find assignment by ID
     */
    public function findAssignment(int $id, array $fields = ['*']): ClassSubject
    {
        return $this->classSubjectRepository->findWithRelations($id, $fields);
    }

    /**
     * Find assignments by classroom ID
     */
    public function findAssignmentsByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->findByClassRoom($classRoomId, $fields);
    }

    /**
     * Find assignments by subject ID
     */
    public function findAssignmentsBySubject(int $subjectId, array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->findBySubject($subjectId, $fields);
    }

    /**
     * Find assignments by teacher ID
     */
    public function findAssignmentsByTeacher(int $teacherId, array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->findByTeacher($teacherId, $fields);
    }

    /**
     * Find assignments by topic ID
     */
    public function findAssignmentsByTopic(int $topicId, array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->findByTopic($topicId, $fields);
    }

    /**
     * Find assignments by grade level
     */
    public function findAssignmentsByGrade(int $grade, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classSubjectRepository->findByGrade($grade, $fields, $perPage);
    }

    /**
     * Search assignments by query
     */
    public function searchAssignments(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classSubjectRepository->searchBySubjectOrClassRoom($query, $fields, $perPage);
    }

    /**
     * Assign subject to classroom
     */
    public function assignSubjectToClassRoom(array $data): ClassSubject
    {
        return DB::transaction(function () use ($data) {
            // Validate subject exists
            $subject = $this->subjectRepository->find($data['subject_id']);

            // Validate classroom exists
            $classroom = $this->classRoomRepository->findWithRelations($data['class_room_id'], ['id', 'name']);

            // Check if already assigned
            if ($this->classSubjectRepository->isSubjectAssigned($data['class_room_id'], $data['subject_id'])) {
                throw new \Exception('Subject is already assigned to this classroom');
            }

            return $this->classSubjectRepository->create($data);
        });
    }

    /**
     * Update assignment
     */
    public function updateAssignment(int $id, array $data): ClassSubject
    {
        return DB::transaction(function () use ($id, $data) {
            // Validate new subject if being updated
            if (isset($data['subject_id'])) {
                $this->subjectRepository->find($data['subject_id']);
            }

            // Validate new classroom if being updated
            if (isset($data['class_room_id'])) {
                $this->classRoomRepository->findWithRelations($data['class_room_id'], ['id', 'name']);
            }

            // Check for duplicate assignment if updating subject or classroom
            if (isset($data['subject_id']) || isset($data['class_room_id'])) {
                $currentAssignment = $this->classSubjectRepository->findWithRelations($id);
                $subjectId = $data['subject_id'] ?? $currentAssignment->subject_id;
                $classRoomId = $data['class_room_id'] ?? $currentAssignment->class_room_id;

                $existingAssignment = $this->classSubjectRepository->findByClassRoomAndSubject($classRoomId, $subjectId);
                if ($existingAssignment && $existingAssignment->id !== $id) {
                    throw new \Exception('Subject is already assigned to this classroom');
                }
            }

            return $this->classSubjectRepository->update($id, $data);
        });
    }

    /**
     * Unassign subject from classroom (delete assignment)
     */
    public function unassignSubject(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->classSubjectRepository->delete($id);
        });
    }

    /**
     * Unassign subject by classroom and subject IDs
     */
    public function unassignSubjectFromClassRoom(int $classRoomId, int $subjectId): bool
    {
        return DB::transaction(function () use ($classRoomId, $subjectId) {
            $assignment = $this->classSubjectRepository->findByClassRoomAndSubject($classRoomId, $subjectId);
            if (!$assignment) {
                throw new ModelNotFoundException('Assignment not found');
            }
            return $this->classSubjectRepository->delete($assignment->id);
        });
    }

    /**
     * Get available subjects for classroom
     */
    public function getAvailableSubjectsForClassRoom(int $classRoomId): Collection
    {
        // Validate classroom exists
        $this->classRoomRepository->findWithRelations($classRoomId, ['id', 'name']);

        return $this->classSubjectRepository->getAvailableSubjectsForClassRoom($classRoomId);
    }

    /**
     * Get available classrooms for subject
     */
    public function getAvailableClassRoomsForSubject(int $subjectId): Collection
    {
        // Validate subject exists
        $this->subjectRepository->find($subjectId);

        return $this->classSubjectRepository->getAvailableClassRoomsForSubject($subjectId);
    }

    /**
     * Bulk assign subjects to classroom
     */
    public function bulkAssignSubjectsToClassRoom(int $classRoomId, array $subjectIds): array
    {
        return DB::transaction(function () use ($classRoomId, $subjectIds) {
            // Validate classroom exists
            $this->classRoomRepository->findWithRelations($classRoomId, ['id', 'name']);

            // Validate all subjects exist
            $subjects = $this->subjectRepository->findManyByIds($subjectIds, ['id', 'name']);
            if ($subjects->count() !== count($subjectIds)) {
                throw new \Exception('One or more subjects not found');
            }

            return $this->classSubjectRepository->bulkAssignToClassRoom($classRoomId, $subjectIds);
        });
    }

    /**
     * Bulk assign classrooms to subject
     */
    public function bulkAssignClassRoomsToSubject(int $subjectId, array $classRoomIds): array
    {
        return DB::transaction(function () use ($subjectId, $classRoomIds) {
            // Validate subject exists
            $this->subjectRepository->find($subjectId);

            // Validate all classrooms exist
            $classrooms = $this->classRoomRepository->getAll(['*']);
            $validClassrooms = $classrooms->whereIn('id', $classRoomIds);
            if ($validClassrooms->count() !== count($classRoomIds)) {
                throw new \Exception('One or more classrooms not found');
            }

            return $this->classSubjectRepository->bulkAssignToSubject($subjectId, $classRoomIds);
        });
    }


    /**
     * Delete multiple assignments
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->classSubjectRepository->delete($id);
            }
            return true;
        });
    }

    /**
     * Get classroom assignment statistics
     */
    public function getClassRoomStatistics(int $classRoomId): array
    {
        // Validate classroom exists
        $this->classRoomRepository->findWithRelations($classRoomId, ['id', 'name']);

        return $this->classSubjectRepository->getClassRoomStatistics($classRoomId);
    }

    /**
     * Get subject assignment statistics
     */
    public function getSubjectStatistics(int $subjectId): array
    {
        // Validate subject exists
        $this->subjectRepository->find($subjectId);

        return $this->classSubjectRepository->getSubjectStatistics($subjectId);
    }

    /**
     * Check if subject is assigned to classroom
     */
    public function isSubjectAssigned(int $classRoomId, int $subjectId): bool
    {
        return $this->classSubjectRepository->isSubjectAssigned($classRoomId, $subjectId);
    }

    /**
     * Find specific assignment by classroom and subject
     */
    public function findAssignmentByClassRoomAndSubject(int $classRoomId, int $subjectId): ?ClassSubject
    {
        return $this->classSubjectRepository->findByClassRoomAndSubject($classRoomId, $subjectId);
    }

    /**
     * Search assignments with pagination for modal
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->classSubjectRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Find multiple assignments by IDs
     */
    public function findMultipleAssignments(array $ids, array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->findManyByIds($ids, $fields);
    }

    /**
     * Get assignments for teacher's subjects
     */
    public function getTeacherAssignments(int $teacherId, array $fields = ['*']): Collection
    {
        return $this->classSubjectRepository->findByTeacher($teacherId, $fields);
    }

    /**
     * Get all subjects available for assignment (with teacher and topic info)
     */
    public function getAllAvailableSubjects(): Collection
    {
        return $this->subjectRepository->getAll(['id', 'name', 'photo', 'content', 'tagline', 'teacher_id', 'topic_id']);
    }

    /**
     * Get all classrooms available for assignment
     */
    public function getAllAvailableClassRooms(): Collection
    {
        return $this->classRoomRepository->getAll(['id', 'name', 'photo', 'grade']);
    }

    /**
     * Get classrooms assigned to teacher via their subjects
     */
    public function getTeacherClassRooms(int $teacherId): Collection
    {
        return $this->classSubjectRepository->getTeacherClassRooms($teacherId);
    }
}
