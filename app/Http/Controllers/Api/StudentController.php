<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\StudentRequest;
use App\Http\Requests\StudentSearchRequest;
use App\Http\Resources\Api\StudentResource;
use App\Services\StudentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    private StudentService $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Display a listing of students
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'name', 'email', 'photo', 'gender'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $students = $this->studentService->searchStudents(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return StudentResource::collection($students);
            }

            // Filter by gender
            if ($request->filled('gender')) {
                $students = $this->studentService->findStudentsByGender(
                    $request->string('gender'),
                    $fields,
                    $perPage
                );
                return StudentResource::collection($students);
            }

            // Filter by classroom
            if ($request->filled('classroom_id')) {
                $students = $this->studentService->findStudentsByClassRoom(
                    $request->integer('classroom_id'),
                    $fields
                );
                return StudentResource::collection($students);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $students = $this->studentService->getAll($fields);
                return StudentResource::collection($students);
            }

            // Handle unenrolled parameter
            if ($request->boolean('unenrolled')) {
                $students = $this->studentService->getUnenrolledStudents($fields);
                return StudentResource::collection($students);
            }

            // Handle with_exam_performance parameter
            if ($request->boolean('with_exam_performance')) {
                $students = $this->studentService->getStudentsWithExamPerformance($fields, $perPage);
                return StudentResource::collection($students);
            }

            // Default paginated response
            $students = $this->studentService->getPaginated($fields, $perPage);
            return StudentResource::collection($students);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve students',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified student
     */
    public function show(Request $request, int $student)
    {
        try {
            if ($request->boolean('with_classroom_stats')) {
                $studentData = $this->studentService->findStudentWithClassroomStats($student, ['*']);
            } else {
                $studentData = $this->studentService->findStudent($student, ['*']);
            }

            return response()->json([
                'success' => true,
                'data' => new StudentResource($studentData)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created student
     */
    public function store(StudentRequest $request)
    {
        try {
            $student = $this->studentService->createStudent($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => new StudentResource($student)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified student
     */
    public function update(StudentRequest $request, int $id)
    {
        try {
            $student = $this->studentService->updateStudent($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully',
                'data' => new StudentResource($student)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified student
     */
    public function destroy(int $id)
    {
        try {
            $this->studentService->deleteStudent($id);
            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search students with pagination for frontend modal
     */
    public function search(StudentSearchRequest $request)
    {
        try {

            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'name', 'email', 'photo'];

            $result = $this->studentService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => StudentResource::collection($result['data']),
                'total' => $result['total'],
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'has_more' => $result['has_more']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search students',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get student statistics and performance
     */
    public function statistics(int $id)
    {
        try {
            $statistics = $this->studentService->getStudentStatistics($id);
            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get authenticated student's profile
     */
    public function profile(Request $request)
    {
        try {
            $student = $request->user();

            // Ensure the user is a student
            if (!$student->hasRole('student')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Student role required.'
                ], 403);
            }

            $studentProfile = $this->studentService->getStudentProfile($student->id, ['*']);

            return response()->json([
                'success' => true,
                'data' => new StudentResource($studentProfile)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get student profile for teacher (teacher-scoped access)
     */
    public function teacherStudentProfile(Request $request, int $studentId)
    {
        try {
            $teacher = $request->user();

            // Ensure the user is a teacher
            if (!$teacher->hasRole('teacher')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Teacher role required.'
                ], 403);
            }

            $studentProfile = $this->studentService->getStudentProfileForTeacher($studentId, $teacher->id, ['*']);

            return response()->json([
                'success' => true,
                'data' => new StudentResource($studentProfile)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        } catch (\Symfony\Component\HttpFoundation\Exception\BadRequestException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
