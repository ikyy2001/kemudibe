<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\ClassStudentBulkEnrollRequest;
use App\Http\Requests\ClassStudentBulkUnenrollRequest;
use App\Http\Requests\ClassStudentRequest;
use App\Http\Requests\ClassStudentSearchRequest;
use App\Http\Requests\ClassStudentUpdateStatusRequest;
use App\Http\Requests\ClassStudentUploadRapportRequest;
use App\Http\Resources\Api\ClassStudentResource;
use App\Http\Resources\Api\UserResource;
use App\Services\ClassStudentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassStudentController extends Controller
{
    private ClassStudentService $classStudentService;

    public function __construct(ClassStudentService $classStudentService)
    {
        $this->classStudentService = $classStudentService;
    }

    /**
     * Display a listing of enrollments
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'student_id', 'class_room_id', 'has_passed', 'rapport'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $enrollments = $this->classStudentService->searchEnrollments(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return ClassStudentResource::collection($enrollments);
            }

            // Filter by student
            if ($request->filled('student_id')) {
                $enrollments = $this->classStudentService->findEnrollmentsByStudent(
                    $request->integer('student_id'),
                    $fields
                );
                return ClassStudentResource::collection($enrollments);
            }

            // Filter by classroom
            if ($request->filled('class_room_id')) {
                $enrollments = $this->classStudentService->findEnrollmentsByClassRoom(
                    $request->integer('class_room_id'),
                    $fields
                );
                return ClassStudentResource::collection($enrollments);
            }

            // Filter by grade
            if ($request->filled('grade')) {
                $enrollments = $this->classStudentService->findEnrollmentsByGrade(
                    $request->integer('grade'),
                    $fields,
                    $perPage
                );
                return ClassStudentResource::collection($enrollments);
            }

            // Filter by pass status
            if ($request->filled('passed')) {
                $classRoomId = $request->integer('class_room_id');
                if (!$classRoomId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Classroom ID is required when filtering by pass status'
                    ], 400);
                }

                $enrollments = $request->boolean('passed')
                    ? $this->classStudentService->getPassedStudents($classRoomId, $fields)
                    : $this->classStudentService->getFailedStudents($classRoomId, $fields);

                return ClassStudentResource::collection($enrollments);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $enrollments = $this->classStudentService->getAll($fields);
                return ClassStudentResource::collection($enrollments);
            }

            // Default paginated response
            $enrollments = $this->classStudentService->getPaginated($fields, $perPage);
            return ClassStudentResource::collection($enrollments);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve enrollments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified enrollment
     */
    public function show(int $id)
    {
        try {
            $enrollment = $this->classStudentService->findEnrollment($id, ['*']);
            return response()->json([
                'success' => true,
                'data' => new ClassStudentResource($enrollment)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrollment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Enroll student in classroom
     */
    public function store(ClassStudentRequest $request)
    {
        try {
            $enrollment = $this->classStudentService->enrollStudent($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Student enrolled successfully',
                'data' => new ClassStudentResource($enrollment)
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
                'message' => 'Failed to enroll student: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified enrollment
     */
    public function update(ClassStudentRequest $request, int $id)
    {
        try {
            $enrollment = $this->classStudentService->updateEnrollment($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Enrollment updated successfully',
                'data' => new ClassStudentResource($enrollment)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment not found'
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
                'message' => 'Failed to update enrollment: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified enrollment (unenroll student)
     */
    public function destroy(int $id)
    {
        try {
            $this->classStudentService->unenrollStudent($id);
            return response()->json([
                'success' => true,
                'message' => 'Student unenrolled successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unenroll student',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search enrollments with pagination for frontend modal
     */
    public function search(ClassStudentSearchRequest $request)
    {
        try {

            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'student_id', 'class_room_id', 'has_passed'];

            $result = $this->classStudentService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => ClassStudentResource::collection($result['data']),
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
                'message' => 'Failed to search enrollments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk enroll students in classroom
     */
    public function bulkEnroll(ClassStudentBulkEnrollRequest $request, int $classRoomId)
    {
        try {

            $additionalData = $request->only(['has_passed', 'rapport']);
            $enrollments = $this->classStudentService->bulkEnrollStudents(
                $classRoomId,
                $request->input('student_ids'),
                $additionalData
            );

            return response()->json([
                'success' => true,
                'message' => 'Students enrolled successfully',
                'data' => ClassStudentResource::collection($enrollments)
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
                'message' => 'Failed to enroll students: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update student status in classroom
     */
    public function updateStatus(ClassStudentUpdateStatusRequest $request, int $studentId, int $classRoomId)
    {
        try {

            $enrollment = $this->classStudentService->updateStudentStatusByIds(
                $studentId,
                $classRoomId,
                $request->boolean('has_passed'),
                $request->string('rapport')
            );

            return response()->json([
                'success' => true,
                'message' => 'Student status updated successfully',
                'data' => new ClassStudentResource($enrollment)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment not found'
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
                'message' => 'Failed to update student status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }



    /**
     * Upload rapport PDF for student-classroom enrollment (Manager only)
     */
    public function uploadRapport(ClassStudentUploadRapportRequest $request, int $studentId, int $classRoomId)
    {
        try {

            $enrollment = $this->classStudentService->uploadRapport(
                $studentId,
                $classRoomId,
                $request->file('rapport')
            );

            return response()->json([
                'success' => true,
                'message' => 'Rapport PDF uploaded successfully',
                'data' => new ClassStudentResource($enrollment)
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student enrollment not found in this classroom'
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
                'message' => 'Failed to upload rapport PDF',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Download rapport PDF for student-classroom enrollment (All roles with authorization)
     */
    public function downloadRapport(Request $request, int $studentId, int $classRoomId)
    {
        try {
            $user = $request->user();

            // Students can only access their own rapport
            if ($user->hasRole('student') && $user->id !== $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only access your own rapport'
                ], 403);
            }

            $rapportData = $this->classStudentService->downloadRapport($studentId, $classRoomId);

            return response()->download(
                storage_path('app/public/' . $rapportData['path']),
                $rapportData['filename'],
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => $rapportData['size']
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student enrollment not found in this classroom'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get rapport PDF info for student-classroom enrollment (All roles with authorization)
     */
    public function getRapportInfo(Request $request, int $studentId, int $classRoomId)
    {
        try {
            $user = $request->user();

            // Students can only access their own rapport
            if ($user->hasRole('student') && $user->id !== $studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only access your own rapport'
                ], 403);
            }

            $rapportData = $this->classStudentService->downloadRapport($studentId, $classRoomId);

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $rapportData['filename'],
                    'size' => $rapportData['size'],
                    'url' => $rapportData['url'],
                    'uploaded_at' => 'Available' // Could add timestamp from enrollment
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student enrollment not found in this classroom'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Delete rapport PDF for student-classroom enrollment (Manager only)
     */
    public function deleteRapport(int $studentId, int $classRoomId)
    {
        try {
            $enrollment = $this->classStudentService->deleteRapport($studentId, $classRoomId);

            return response()->json([
                'success' => true,
                'message' => 'Rapport PDF deleted successfully',
                'data' => new ClassStudentResource($enrollment)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student enrollment not found in this classroom'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Get student's own rapport PDF info (Student-specific endpoint)
     */
    public function getStudentRapportInfo(Request $request, int $classRoomId)
    {
        try {
            $student = $request->user();
            $rapportData = $this->classStudentService->downloadRapport($student->id, $classRoomId);

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $rapportData['filename'],
                    'size' => $rapportData['size'],
                    'url' => $rapportData['url'],
                    'uploaded_at' => 'Available',
                    'classroom_id' => $classRoomId
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not enrolled in this classroom'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Download student's own rapport PDF (Student-specific endpoint)
     */
    public function downloadStudentRapport(Request $request, int $classRoomId)
    {
        try {
            $student = $request->user();
            $rapportData = $this->classStudentService->downloadRapport($student->id, $classRoomId);

            return response()->download(
                storage_path('app/public/' . $rapportData['path']),
                $rapportData['filename'],
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => $rapportData['size']
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not enrolled in this classroom'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Get authenticated student's enrolled classrooms
     */
    public function studentClassRooms(Request $request)
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

            $classRooms = $this->classStudentService->getStudentClassRooms($student->id, ['*']);

            return response()->json([
                'success' => true,
                'data' => ClassStudentResource::collection($classRooms)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found or not enrolled in any classrooms'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student classrooms',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

}
