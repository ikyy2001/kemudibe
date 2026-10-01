<?php

namespace App\Services;

use App\Repositories\StudentRepository;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StudentService
{
    private StudentRepository $studentRepository;

    public function __construct(StudentRepository $studentRepository)
    {
        $this->studentRepository = $studentRepository;
    }

    /**
     * Get paginated students
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->studentRepository->getPaginated($fields, $perPage);
    }

    /**
     * Get all students without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return $this->studentRepository->getAll($fields);
    }

    /**
     * Find student by ID
     */
    public function findStudent(int $id, array $fields = ['*']): User
    {
        return $this->studentRepository->findWithEnrollments($id, $fields);
    }

    /**
     * Find student by ID with classroom statistics
     */
    public function findStudentWithClassroomStats(int $id, array $fields = ['*']): User
    {
        return $this->studentRepository->findWithClassroomStats($id, $fields);
    }

    /**
     * Search students by query
     */
    public function searchStudents(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->studentRepository->searchByNameAndEmail($query, $fields, $perPage);
    }

    /**
     * Find multiple students by IDs
     */
    public function findMultipleStudents(array $ids, array $fields = ['*']): Collection
    {
        return $this->studentRepository->findManyByIds($ids, $fields);
    }

    /**
     * Get students by gender
     */
    public function findStudentsByGender(string $gender, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->studentRepository->findByGender($gender, $fields, $perPage);
    }

    /**
     * Get students not enrolled in any classroom
     */
    public function getUnenrolledStudents(array $fields = ['*']): Collection
    {
        return $this->studentRepository->getUnenrolledStudents($fields);
    }

    /**
     * Get students enrolled in specific classroom
     */
    public function findStudentsByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return $this->studentRepository->findByClassRoom($classRoomId, $fields);
    }

    /**
     * Get students with their exam performance
     */
    public function getStudentsWithExamPerformance(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return $this->studentRepository->findWithExamPerformance($fields, $perPage);
    }

    /**
     * Create a new student
     */
    public function createStudent(array $data): User
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

            return $this->studentRepository->create($data);
        });
    }

    /**
     * Update student by ID
     */
    public function updateStudent(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $student = $this->studentRepository->findWithEnrollments($id, ['*']);
            $oldPhoto = $student->getRawOriginal('photo'); // Get raw photo path

            // Hash password if provided
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Handle photo upload
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $data['photo'] = $this->uploadPhoto($data['photo']);
            }

            $updatedStudent = $this->studentRepository->update($id, $data);

            // Delete old photo if new one was uploaded
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile && $oldPhoto) {
                $this->deletePhoto($oldPhoto);
            }

            return $updatedStudent;
        });
    }

    /**
     * Delete student by ID
     */
    public function deleteStudent(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $student = $this->studentRepository->findWithEnrollments($id, ['photo']);
            $photoPath = $student->getRawOriginal('photo'); // Get raw photo path

            if ($photoPath) {
                $this->deletePhoto($photoPath);
            }

            return $this->studentRepository->delete($id);
        });
    }

    /**
     * Search students with pagination for modal (with count only, no relationships)
     */
    public function searchWithPagination(string $query = '', array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        return $this->studentRepository->searchWithPagination($query, $fields, $page, $perPage);
    }

    /**
     * Search students for modal (with count only, no relationships)
     */
    public function searchForModal(string $query = '', array $fields = ['*'], int $limit = 6): Collection
    {
        return $this->studentRepository->searchForModal($query, $fields, $limit);
    }

    /**
     * Delete multiple students
     */
    public function deleteMultiple(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            $students = $this->studentRepository->findManyByIds($ids, ['id', 'photo']);
            
            foreach ($students as $student) {
                $photoPath = $student->getRawOriginal('photo'); // Get raw photo path
                if ($photoPath) {
                    $this->deletePhoto($photoPath);
                }
            }

            foreach ($ids as $id) {
                $this->studentRepository->delete($id);
            }

            return true;
        });
    }

    /**
     * Get student profile with detailed information
     */
    public function getStudentProfile(int $studentId, array $fields = ['*']): User
    {
        return $this->studentRepository->getStudentProfile($studentId, $fields);
    }

    /**
     * Get student profile for teacher with access validation and scoped data
     */
    public function getStudentProfileForTeacher(int $studentId, int $teacherId, array $fields = ['*']): User
    {
        // First verify teacher has access to this student
        if (!$this->studentRepository->teacherHasAccessToStudent($teacherId, $studentId)) {
            throw new \Symfony\Component\HttpFoundation\Exception\BadRequestException(
                'Access denied. You can only view students in your assigned subjects/classrooms.'
            );
        }

        return $this->studentRepository->getStudentProfileForTeacher($studentId, $teacherId, $fields);
    }

    /**
     * Get student statistics
     */
    public function getStudentStatistics(int $studentId): array
    {
        $student = $this->studentRepository->findWithEnrollments($studentId, ['*']);
        
        // Calculate exam statistics
        $completedExams = $student->examAttempts->where('is_completed', true);
        $totalPoints = $student->questionAnswers->sum('points_earned');
        $possiblePoints = $student->questionAnswers->sum(function($answer) {
            return $answer->examQuestion->points ?? 0;
        });
        
        $averageScore = $possiblePoints > 0 ? round(($totalPoints / $possiblePoints) * 100, 2) : 0;
        $passedExams = $student->questionAnswers->where('has_passed', true)->count();
        $failedExams = $student->questionAnswers->where('has_passed', false)->count();

        return [
            'total_enrollments' => $student->class_students_count,
            'total_exam_attempts' => $student->exam_attempts_count,
            'completed_exams' => $completedExams->count(),
            'total_questions_answered' => $student->question_answers_count,
            'passed_questions' => $passedExams,
            'failed_questions' => $failedExams,
            'total_points_earned' => $totalPoints,
            'average_score_percentage' => $averageScore,
            'enrolled_classrooms' => $student->classStudents->map(function($enrollment) {
                return [
                    'classroom_name' => $enrollment->classRoom->name,
                    'grade' => $enrollment->classRoom->grade,
                    'has_passed' => $enrollment->has_passed,
                    'rapport' => $enrollment->rapport
                ];
            })
        ];
    }

    /**
     * Upload photo and return storage path
     */
    private function uploadPhoto(UploadedFile $photo): string
    {
        return $photo->store('students', 'public');
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