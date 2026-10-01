<?php

namespace App\Services;

use App\Repositories\ClassRoomRepository;
use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassRoomService
{
    private ClassRoomRepository $classRoomRepository;

    public function __construct(ClassRoomRepository $classRoomRepository)
    {
        $this->classRoomRepository = $classRoomRepository;
    }

    /**
     * Get paginated classrooms
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classRoomRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all classrooms without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->classRoomRepository->getAll($fields);
    }

    /**
     * Find classroom by ID
     */
    public function findClassRoom(int $id, array $fields = ['*']): ClassRoom
    {
        return $this->classRoomRepository->findWithRelations($id, $fields);
    }

    /**
     * Find classrooms by grade level
     */
    public function findClassRoomsByGrade(int $grade, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classRoomRepository->findByGrade($grade, $fields, $perPage);
    }

    /**
     * Search classrooms by query
     */
    public function searchClassRooms(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classRoomRepository->searchByNameAndGrade($query, $fields, $perPage);
    }

    /**
     * Find multiple classrooms by IDs
     */
    public function findMultipleClassRooms(array $ids, array $fields = ['*']): Collection
    {
        return $this->classRoomRepository->findManyByIds($ids, $fields);
    }

    /**
     * Get students enrolled in classroom
     */
    public function getEnrolledStudents(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->classRoomRepository->getEnrolledStudents($classRoomId, $fields);
    }

    /**
     * Get subjects assigned to classroom
     */
    public function getAssignedSubjects(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->classRoomRepository->getAssignedSubjects($classRoomId, $fields);
    }

    /**
     * Get students available for enrollment
     */
    public function getAvailableStudents(int $classRoomId): Collection
    {
        return $this->classRoomRepository->findAvailableStudents($classRoomId);
    }

    /**
     * Create a new classroom
     */
    public function createClassRoom(array $data): ClassRoom
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            return $this->classRoomRepository->create($data);
        });
    }

    /**
     * Update classroom by ID
     */
    public function updateClassRoom(int $id, array $data): ClassRoom
    {
        return DB::transaction(function () use ($id, $data) {
            $classRoom = $this->classRoomRepository->findWithRelations($id, ['*']);
            $oldPhoto = $classRoom->getRawOriginal('photo'); // Get raw photo path

            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            $updatedClassRoom = $this->classRoomRepository->update($id, $data);

            // Delete old photo if new one was uploaded
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile && $oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            return $updatedClassRoom;
        });
    }

    /**
     * Delete classroom by ID
     */
    public function deleteClassRoom(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $classRoom = $this->classRoomRepository->findWithRelations($id, ['photo']);
            
            // Check if classroom has any assigned students
            if ($classRoom->classStudents_count > 0 || \App\Models\ClassStudent::where('class_room_id', $id)->exists()) {
                throw new \InvalidArgumentException('Cannot delete classroom that has enrolled students');
            }
            
            // Check if classroom has any assigned subjects
            if ($classRoom->classSubjects_count > 0 || \App\Models\ClassSubject::where('class_room_id', $id)->exists()) {
                throw new \InvalidArgumentException('Cannot delete classroom that has assigned subjects');
            }
            
            $photoPath = $classRoom->getRawOriginal('photo'); // Get raw photo path

            if ($photoPath) {
                $this->deletePhoto($photoPath);
            }

            return $this->classRoomRepository->delete($id);
        });
    }

    /**
     * Delete multiple classrooms
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            // First check all classrooms for relationships
            foreach ($ids as $id) {
                $classRoom = $this->classRoomRepository->findWithRelations($id, ['id', 'name']);
                
                // Check if classroom has any assigned students
                if ($classRoom->classStudents_count > 0 || \App\Models\ClassStudent::where('class_room_id', $id)->exists()) {
                    throw new \InvalidArgumentException("Cannot delete classroom '{$classRoom->name}' that has enrolled students");
                }
                
                // Check if classroom has any assigned subjects
                if ($classRoom->classSubjects_count > 0 || \App\Models\ClassSubject::where('class_room_id', $id)->exists()) {
                    throw new \InvalidArgumentException("Cannot delete classroom '{$classRoom->name}' that has assigned subjects");
                }
            }
            
            // If all validations pass, proceed with deletion
            $classRooms = $this->classRoomRepository->findManyByIds($ids, ['id', 'photo']);
            
            foreach ($classRooms as $classRoom) {
                $photoPath = $classRoom->getRawOriginal('photo'); // Get raw photo path
                if ($photoPath) {
                    $this->deletePhoto($photoPath);
                }
            }

            foreach ($ids as $id) {
                $this->classRoomRepository->delete($id);
            }

            return true;
        });
    }

    /**
     * Enroll student in classroom
     */
    public function enrollStudent(int $classRoomId, int $studentId, array $additionalData = []): ClassStudent
    {
        return DB::transaction(function () use ($classRoomId, $studentId, $additionalData) {
            return $this->classRoomRepository->enrollStudent($classRoomId, $studentId, $additionalData);
        });
    }

    /**
     * Enroll multiple students in classroom
     */
    public function enrollMultipleStudents(int $classRoomId, array $studentIds, array $additionalData = []): array
    {
        return DB::transaction(function () use ($classRoomId, $studentIds, $additionalData) {
            $enrolled = [];
            foreach ($studentIds as $studentId) {
                $enrolled[] = $this->classRoomRepository->enrollStudent($classRoomId, $studentId, $additionalData);
            }
            return $enrolled;
        });
    }

    /**
     * Remove student from classroom
     */
    public function unenrollStudent(int $classRoomId, int $studentId): bool
    {
        return DB::transaction(function () use ($classRoomId, $studentId) {
            return $this->classRoomRepository->unenrollStudent($classRoomId, $studentId);
        });
    }

    /**
     * Assign subject to classroom
     */
    public function assignSubject(int $classRoomId, int $subjectId): ClassSubject
    {
        return DB::transaction(function () use ($classRoomId, $subjectId) {
            return $this->classRoomRepository->assignSubject($classRoomId, $subjectId);
        });
    }

    /**
     * Assign multiple subjects to classroom
     */
    public function assignMultipleSubjects(int $classRoomId, array $subjectIds): array
    {
        return DB::transaction(function () use ($classRoomId, $subjectIds) {
            $assigned = [];
            foreach ($subjectIds as $subjectId) {
                $assigned[] = $this->classRoomRepository->assignSubject($classRoomId, $subjectId);
            }
            return $assigned;
        });
    }

    /**
     * Remove subject from classroom
     */
    public function unassignSubject(int $classRoomId, int $subjectId): bool
    {
        return DB::transaction(function () use ($classRoomId, $subjectId) {
            return $this->classRoomRepository->unassignSubject($classRoomId, $subjectId);
        });
    }

    /**
     * Update student status in classroom
     */
    public function updateStudentStatus(int $classRoomId, int $studentId, bool $hasPassed, ?string $rapport = null): int
    {
        return DB::transaction(function () use ($classRoomId, $studentId, $hasPassed, $rapport) {
            $classRoom = $this->classRoomRepository->findWithRelations($classRoomId, ['id']);
            
            return $classRoom->classStudents()
                ->where('student_id', $studentId)
                ->update([
                    'has_passed' => $hasPassed,
                    'rapport' => $rapport
                ]);
        });
    }

    /**
     * Get classroom statistics
     */
    public function getClassRoomStatistics(int $classRoomId): array
    {
        $classRoom = $this->classRoomRepository->findWithRelations($classRoomId, ['*']);
        
        return [
            'total_students' => $classRoom->class_students_count,
            'total_subjects' => $classRoom->class_subjects_count,
            'passed_students' => $classRoom->classStudents()->where('has_passed', true)->count(),
            'failed_students' => $classRoom->classStudents()->where('has_passed', false)->count(),
            'grade' => $classRoom->grade,
            'name' => $classRoom->name
        ];
    }

    /**
     * Search classrooms with pagination for modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->classRoomRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Search classrooms for modal (with count only, no relationships)
     */
    public function searchForModal(string $query = '', array $fields = ['*'], int $limit = 6): Collection
    {
        return $this->classRoomRepository->searchForModal($query, $fields, $limit);
    }

    /**
     * Upload photo and return storage path
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        return $photo->store('classrooms', 'public');
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