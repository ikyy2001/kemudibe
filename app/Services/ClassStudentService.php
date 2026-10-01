<?php

namespace App\Services;

use App\Repositories\ClassStudentRepository;
use App\Repositories\StudentRepository;
use App\Repositories\ClassRoomRepository;
use App\Models\ClassStudent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ClassStudentService
{
    private ClassStudentRepository $classStudentRepository;
    private StudentRepository $studentRepository;
    private ClassRoomRepository $classRoomRepository;

    public function __construct(
        ClassStudentRepository $classStudentRepository,
        StudentRepository $studentRepository,
        ClassRoomRepository $classRoomRepository
    )
    {
        $this->classStudentRepository = $classStudentRepository;
        $this->studentRepository = $studentRepository;
        $this->classRoomRepository = $classRoomRepository;
    }

    /**
     * Get paginated enrollments
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classStudentRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all enrollments without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->classStudentRepository->getAll($fields);
    }

    /**
     * Find enrollment by ID
     */
    public function findEnrollment(int $id, array $fields = ['*']): ClassStudent
    {
        return $this->classStudentRepository->findWithRelations($id, $fields);
    }

    /**
     * Find enrollments by student ID
     */
    public function findEnrollmentsByStudent(int $studentId, array $fields = ['*']): Collection
    {
        return $this->classStudentRepository->findByStudent($studentId, $fields);
    }

    /**
     * Find enrollments by classroom ID
     */
    public function findEnrollmentsByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->classStudentRepository->findByClassRoom($classRoomId, $fields);
    }

    /**
     * Search enrollments by query
     */
    public function searchEnrollments(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classStudentRepository->searchByStudentOrClassRoom($query, $fields, $perPage);
    }

    /**
     * Find enrollments by grade level
     */
    public function findEnrollmentsByGrade(int $grade, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->classStudentRepository->findByGrade($grade, $fields, $perPage);
    }

    /**
     * Get passed students in classroom
     */
    public function getPassedStudents(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->classStudentRepository->findPassedStudentsByClassRoom($classRoomId, $fields);
    }

    /**
     * Get failed students in classroom
     */
    public function getFailedStudents(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->classStudentRepository->findFailedStudentsByClassRoom($classRoomId, $fields);
    }

    /**
     * Enroll student in classroom
     */
    public function enrollStudent(array $data): ClassStudent
    {
        return DB::transaction(function () use ($data) {
            // Validate student exists and has student role
            $student = $this->studentRepository->findStudent($data['student_id']);

            // Validate classroom exists
            $classRoom = $this->classRoomRepository->findWithRelations($data['class_room_id'], ['id', 'name']);

            // Check if already enrolled
            if ($this->classStudentRepository->isStudentEnrolled($data['student_id'], $data['class_room_id'])) {
                throw new \Exception('Student is already enrolled in this classroom');
            }

            // Set default values
            $data['has_passed'] = $data['has_passed'] ?? false;

            return $this->classStudentRepository->create($data);
        });
    }

    /**
     * Update enrollment
     */
    public function updateEnrollment(int $id, array $data): ClassStudent
    {
        return DB::transaction(function () use ($id, $data) {
            // If updating student or classroom, validate they exist
            if (isset($data['student_id'])) {
                $this->studentRepository->findStudent($data['student_id']);
            }

            if (isset($data['class_room_id'])) {
                $this->classRoomRepository->findWithRelations($data['class_room_id'], ['id', 'name']);
            }

            // Check for duplicate enrollment if updating student or classroom
            if (isset($data['student_id']) || isset($data['class_room_id'])) {
                $currentEnrollment = $this->classStudentRepository->findWithRelations($id);
                $studentId = $data['student_id'] ?? $currentEnrollment->student_id;
                $classRoomId = $data['class_room_id'] ?? $currentEnrollment->class_room_id;

                $existingEnrollment = $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);
                if ($existingEnrollment && $existingEnrollment->id !== $id) {
                    throw new \Exception('Student is already enrolled in this classroom');
                }
            }

            return $this->classStudentRepository->update($id, $data);
        });
    }

    /**
     * Unenroll student (delete enrollment)
     */
    public function unenrollStudent(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->classStudentRepository->delete($id);
        });
    }

    /**
     * Unenroll student by student and classroom IDs
     */
    public function unenrollStudentFromClassRoom(int $studentId, int $classRoomId): bool
    {
        return DB::transaction(function () use ($studentId, $classRoomId) {
            $enrollment = $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);
            if (!$enrollment) {
                throw new ModelNotFoundException('Enrollment not found');
            }
            return $this->classStudentRepository->delete($enrollment->id);
        });
    }

    /**
     * Update student status (passed/failed) with rapport
     */
    public function updateStudentStatus(int $id, bool $hasPassed, ?string $rapport = null): ClassStudent
    {
        return DB::transaction(function () use ($id, $hasPassed, $rapport) {
            return $this->classStudentRepository->update($id, [
                'has_passed' => $hasPassed,
                'rapport' => $rapport
            ]);
        });
    }

    /**
     * Update student status by student and classroom IDs
     */
    public function updateStudentStatusByIds(int $studentId, int $classRoomId, bool $hasPassed, ?string $rapport = null): ClassStudent
    {
        return DB::transaction(function () use ($studentId, $classRoomId, $hasPassed, $rapport) {
            $enrollment = $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);
            if (!$enrollment) {
                throw new ModelNotFoundException('Enrollment not found');
            }

            return $this->classStudentRepository->update($enrollment->id, [
                'has_passed' => $hasPassed,
                'rapport' => $rapport
            ]);
        });
    }

    /**
     * Get available students for enrollment in classroom
     */
    public function getAvailableStudentsForClassRoom(int $classRoomId): Collection
    {
        // Validate classroom exists
        $this->classRoomRepository->findWithRelations($classRoomId, ['id', 'name']);

        return $this->classStudentRepository->getAvailableStudentsForClassRoom($classRoomId);
    }

    /**
     * Bulk enroll students in classroom
     */
    public function bulkEnrollStudents(int $classRoomId, array $studentIds, array $additionalData = []): array
    {
        return DB::transaction(function () use ($classRoomId, $studentIds, $additionalData) {
            // Validate classroom exists
            $this->classRoomRepository->findWithRelations($classRoomId, ['id', 'name']);

            // Validate all students exist and have student role
            $students = $this->studentRepository->getAll(['*']);
            $validStudents = $students->whereIn('id', $studentIds);
            if ($validStudents->count() !== count($studentIds)) {
                throw new \Exception('One or more students not found or do not have student role');
            }

            return $this->classStudentRepository->bulkEnroll($classRoomId, $studentIds, $additionalData);
        });
    }

    /**
     * Delete multiple enrollments
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->classStudentRepository->delete($id);
            }
            return true;
        });
    }

    /**
     * Get classroom enrollment statistics
     */
    public function getClassRoomStatistics(int $classRoomId): array
    {
        // Validate classroom exists
        $this->classRoomRepository->findWithRelations($classRoomId, ['id', 'name']);

        return $this->classStudentRepository->getClassRoomStatistics($classRoomId);
    }

    /**
     * Check if student is enrolled in classroom
     */
    public function isStudentEnrolled(int $studentId, int $classRoomId): bool
    {
        return $this->classStudentRepository->isStudentEnrolled($studentId, $classRoomId);
    }

    /**
     * Find specific enrollment by student and classroom
     */
    public function findEnrollmentByStudentAndClassRoom(int $studentId, int $classRoomId): ?ClassStudent
    {
        return $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);
    }

    /**
     * Search enrollments with pagination for modal
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->classStudentRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Find multiple enrollments by IDs
     */
    public function findMultipleEnrollments(array $ids, array $fields = ['*']): Collection
    {
        return $this->classStudentRepository->findManyByIds($ids, $fields);
    }

    /**
     * Upload rapport PDF for student-classroom enrollment
     */
    public function uploadRapport(int $studentId, int $classRoomId, UploadedFile $pdf): ClassStudent
    {
        return DB::transaction(function () use ($studentId, $classRoomId, $pdf) {
            // Find existing enrollment
            $enrollment = $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);

            if (!$enrollment) {
                throw new ModelNotFoundException('Student enrollment not found in this classroom');
            }

            // Delete old rapport file if exists
            if ($enrollment->rapport && Storage::disk('public')->exists($enrollment->rapport)) {
                Storage::disk('public')->delete($enrollment->rapport);
            }

            // Upload new PDF
            $rapportPath = $this->uploadRapportPdf($pdf, $studentId, $classRoomId);

            // Update enrollment with new rapport path and mark as passed
            return $this->classStudentRepository->update($enrollment->id, [
                'rapport' => $rapportPath,
                'has_passed' => true
            ]);
        });
    }

    /**
     * Download rapport PDF for student-classroom enrollment
     */
    public function downloadRapport(int $studentId, int $classRoomId): array
    {
        $enrollment = $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);

        if (!$enrollment) {
            throw new ModelNotFoundException('Student enrollment not found in this classroom');
        }

        if (!$enrollment->rapport) {
            throw new \Exception('No rapport file found for this enrollment');
        }

        if (!Storage::disk('public')->exists($enrollment->rapport)) {
            throw new \Exception('Rapport file not found on storage');
        }

        return [
            'path' => $enrollment->rapport,
            'url' => Storage::disk('public')->url($enrollment->rapport),
            'filename' => basename($enrollment->rapport),
            'size' => Storage::disk('public')->size($enrollment->rapport)
        ];
    }

    /**
     * Delete rapport PDF for student-classroom enrollment
     */
    public function deleteRapport(int $studentId, int $classRoomId): ClassStudent
    {
        return DB::transaction(function () use ($studentId, $classRoomId) {
            $enrollment = $this->classStudentRepository->findByStudentAndClassRoom($studentId, $classRoomId);

            if (!$enrollment) {
                throw new ModelNotFoundException('Student enrollment not found in this classroom');
            }

            if (!$enrollment->rapport) {
                throw new \Exception('No rapport file found for this enrollment');
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($enrollment->rapport)) {
                Storage::disk('public')->delete($enrollment->rapport);
            }

            // Clear rapport field
            return $this->classStudentRepository->update($enrollment->id, ['rapport' => null]);
        });
    }

    /**
     * Get student's enrolled classrooms with detailed information
     */
    public function getStudentClassRooms(int $studentId, array $fields = ['*']): Collection
    {
        // Validate student exists and has student role
        $student = $this->studentRepository->findStudent($studentId);

        return $this->classStudentRepository->getStudentClassRooms($studentId, $fields);
    }

    /**
     * Upload rapport PDF and return storage path
     */
    private function uploadRapportPdf(UploadedFile $pdf, int $studentId, int $classRoomId): string
    {
        // Generate unique filename
        $filename = "student_{$studentId}_classroom_{$classRoomId}_" . time() . '.pdf';

        // Store in rapports directory
        return $pdf->storeAs('rapports', $filename, 'public');
    }
}
