<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRoomRequest;
use App\Http\Requests\BulkDeleteClassRoomsRequest;
use App\Http\Requests\ClassRoomSearchRequest;
use App\Http\Requests\ClassStudentEnrollRequest;
use App\Http\Requests\ClassStudentUpdateStatusRequest;
use App\Http\Requests\ClassSubjectAssignRequest;
use App\Http\Resources\Api\ClassRoomResource;
use App\Http\Resources\Api\ClassStudentResource;
use App\Http\Resources\Api\ClassSubjectResource;
use App\Http\Resources\Api\UserResource;
use App\Services\ClassRoomService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassRoomController extends Controller
{
    private ClassRoomService $classRoomService;

    public function __construct(ClassRoomService $classRoomService)
    {
        $this->classRoomService = $classRoomService;
    }

    /**
     * Display a listing of classrooms
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'name', 'photo', 'grade'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $classRooms = $this->classRoomService->searchClassRooms(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return ClassRoomResource::collection($classRooms);
            }

            // Filter by grade
            if ($request->filled('grade')) {
                $classRooms = $this->classRoomService->findClassRoomsByGrade(
                    $request->integer('grade'),
                    $fields,
                    $perPage
                );
                return ClassRoomResource::collection($classRooms);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $classRooms = $this->classRoomService->getAll($fields);
                return ClassRoomResource::collection($classRooms);
            }

            // Default paginated response
            $classRooms = $this->classRoomService->getPaginated($fields, $perPage);
            return ClassRoomResource::collection($classRooms);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve classrooms',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified classroom
     */
    public function show(int $id)
    {
        try {
            $classRoom = $this->classRoomService->findClassRoom($id, ['*']);
            return response()->json([
                'success' => true,
                'data' => new ClassRoomResource($classRoom)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve classroom',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created classroom
     */
    public function store(ClassRoomRequest $request)
    {
        try {
            $classRoom = $this->classRoomService->createClassRoom($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Classroom created successfully',
                'data' => new ClassRoomResource($classRoom)
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
                'message' => 'Failed to create classroom',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified classroom
     */
    public function update(ClassRoomRequest $request, int $id)
    {
        try {
            $classRoom = $this->classRoomService->updateClassRoom($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Classroom updated successfully',
                'data' => new ClassRoomResource($classRoom)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
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
                'message' => 'Failed to update classroom',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified classroom
     */
    public function destroy(int $id)
    {
        try {
            $this->classRoomService->deleteClassRoom($id);
            return response()->json([
                'success' => true,
                'message' => 'Classroom deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete classroom',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get students enrolled in classroom
     */
    public function students(int $id)
    {
        try {
            $students = $this->classRoomService->getEnrolledStudents($id);
            return response()->json([
                'success' => true,
                'data' => ClassStudentResource::collection($students)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve students',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get subjects assigned to classroom
     */
    public function subjects(int $id)
    {
        try {
            $subjects = $this->classRoomService->getAssignedSubjects($id);
            return response()->json([
                'success' => true,
                'data' => ClassSubjectResource::collection($subjects)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Enroll student in classroom
     */
    public function enrollStudent(ClassStudentEnrollRequest $request, int $id)
    {
        try {

            $enrollment = $this->classRoomService->enrollStudent(
                $id,
                $request->integer('student_id'),
                $request->only(['has_passed', 'rapport'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Student enrolled successfully',
                'data' => new ClassStudentResource($enrollment)
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom or student not found'
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
                'message' => 'Failed to enroll student',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove student from classroom
     */
    public function unenrollStudent(int $id, int $studentId)
    {
        try {
            $this->classRoomService->unenrollStudent($id, $studentId);
            return response()->json([
                'success' => true,
                'message' => 'Student unenrolled successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom or enrollment not found'
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
     * Assign subject to classroom
     */
    public function assignSubject(ClassSubjectAssignRequest $request, int $id)
    {
        try {

            $assignment = $this->classRoomService->assignSubject($id, $request->integer('subject_id'));

            return response()->json([
                'success' => true,
                'message' => 'Subject assigned successfully',
                'data' => new ClassSubjectResource($assignment)
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom or subject not found'
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
                'message' => 'Failed to assign subject',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove subject from classroom
     */
    public function unassignSubject(int $id, int $subjectId)
    {
        try {
            $this->classRoomService->unassignSubject($id, $subjectId);
            return response()->json([
                'success' => true,
                'message' => 'Subject unassigned successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom or assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign subject',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search classrooms with pagination for frontend modal
     */
    public function search(ClassRoomSearchRequest $request)
    {
        try {
            $search = $request->getSearchQuery();
            $page = $request->getPage();
            $perPage = $request->getLimit(6);
            $fields = ['id', 'name', 'photo', 'grade'];

            $result = $this->classRoomService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => ClassRoomResource::collection($result['data']),
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
                'message' => 'Failed to search classrooms',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

