<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\ClassSubjectBulkAssignToClassRoomRequest;
use App\Http\Requests\ClassSubjectBulkAssignToSubjectRequest;
use App\Http\Requests\ClassSubjectBulkUnassignFromClassRoomRequest;
use App\Http\Requests\ClassSubjectBulkUnassignFromSubjectRequest;
use App\Http\Requests\ClassSubjectRequest;
use App\Http\Requests\ClassSubjectSearchRequest;
use App\Http\Resources\Api\ClassSubjectResource;
use App\Http\Resources\Api\SubjectResource;
use App\Http\Resources\Api\ClassRoomResource;
use App\Services\ClassSubjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassSubjectController extends Controller
{
    private ClassSubjectService $classSubjectService;

    public function __construct(ClassSubjectService $classSubjectService)
    {
        $this->classSubjectService = $classSubjectService;
    }

    /**
     * Display a listing of subject assignments
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'class_room_id', 'subject_id'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $assignments = $this->classSubjectService->searchAssignments(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return ClassSubjectResource::collection($assignments);
            }

            // Filter by classroom
            if ($request->filled('class_room_id')) {
                $assignments = $this->classSubjectService->findAssignmentsByClassRoom(
                    $request->integer('class_room_id'),
                    $fields
                );
                return ClassSubjectResource::collection($assignments);
            }

            // Filter by subject
            if ($request->filled('subject_id')) {
                $assignments = $this->classSubjectService->findAssignmentsBySubject(
                    $request->integer('subject_id'),
                    $fields
                );
                return ClassSubjectResource::collection($assignments);
            }

            // Filter by teacher
            if ($request->filled('teacher_id')) {
                $assignments = $this->classSubjectService->findAssignmentsByTeacher(
                    $request->integer('teacher_id'),
                    $fields
                );
                return ClassSubjectResource::collection($assignments);
            }

            // Filter by topic
            if ($request->filled('topic_id')) {
                $assignments = $this->classSubjectService->findAssignmentsByTopic(
                    $request->integer('topic_id'),
                    $fields
                );
                return ClassSubjectResource::collection($assignments);
            }

            // Filter by grade
            if ($request->filled('grade')) {
                $assignments = $this->classSubjectService->findAssignmentsByGrade(
                    $request->integer('grade'),
                    $fields,
                    $perPage
                );
                return ClassSubjectResource::collection($assignments);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $assignments = $this->classSubjectService->getAll($fields);
                return ClassSubjectResource::collection($assignments);
            }

            // Default paginated response
            $assignments = $this->classSubjectService->getPaginated($fields, $perPage);
            return ClassSubjectResource::collection($assignments);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve subject assignments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified assignment
     */
    public function show(int $id)
    {
        try {
            $assignment = $this->classSubjectService->findAssignment($id, ['*']);
            return response()->json([
                'success' => true,
                'data' => new ClassSubjectResource($assignment)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assignment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Assign subject to classroom
     */
    public function store(ClassSubjectRequest $request)
    {
        try {
            $assignment = $this->classSubjectService->assignSubjectToClassRoom($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Subject assigned to classroom successfully',
                'data' => new ClassSubjectResource($assignment)
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
                'message' => 'Failed to assign subject: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified assignment
     */
    public function update(ClassSubjectRequest $request, int $id)
    {
        try {
            $assignment = $this->classSubjectService->updateAssignment($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully',
                'data' => new ClassSubjectResource($assignment)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
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
                'message' => 'Failed to update assignment: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified assignment (unassign subject)
     */
    public function destroy(int $id)
    {
        try {
            $this->classSubjectService->unassignSubject($id);
            return response()->json([
                'success' => true,
                'message' => 'Subject unassigned successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
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
     * Search assignments with pagination for frontend modal
     */
    public function search(ClassSubjectSearchRequest $request)
    {
        try {

            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'class_room_id', 'subject_id'];

            $result = $this->classSubjectService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => ClassSubjectResource::collection($result['data']),
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
                'message' => 'Failed to search assignments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get available subjects for classroom
     */
    public function availableSubjects(int $classRoomId)
    {
        try {
            $subjects = $this->classSubjectService->getAvailableSubjectsForClassRoom($classRoomId);
            return response()->json([
                'success' => true,
                'data' => SubjectResource::collection($subjects)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get available classrooms for subject
     */
    public function availableClassRooms(int $subjectId)
    {
        try {
            $classrooms = $this->classSubjectService->getAvailableClassRoomsForSubject($subjectId);
            return response()->json([
                'success' => true,
                'data' => ClassRoomResource::collection($classrooms)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available classrooms',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk assign subjects to classroom
     */
    public function bulkAssignToClassRoom(ClassSubjectBulkAssignToClassRoomRequest $request, int $classRoomId)
    {
        try {

            $assignments = $this->classSubjectService->bulkAssignSubjectsToClassRoom(
                $classRoomId,
                $request->input('subject_ids')
            );

            return response()->json([
                'success' => true,
                'message' => 'Subjects assigned to classroom successfully',
                'data' => ClassSubjectResource::collection($assignments)
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
                'message' => 'Failed to assign subjects: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk assign classrooms to subject
     */
    public function bulkAssignToSubject(ClassSubjectBulkAssignToSubjectRequest $request, int $subjectId)
    {
        try {

            $assignments = $this->classSubjectService->bulkAssignClassRoomsToSubject(
                $subjectId,
                $request->input('class_room_ids')
            );

            return response()->json([
                'success' => true,
                'message' => 'Classrooms assigned to subject successfully',
                'data' => ClassSubjectResource::collection($assignments)
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
                'message' => 'Failed to assign classrooms: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get classrooms assigned to authenticated teacher via subjects
     */
    public function teacherClassRooms(Request $request)
    {
        try {
            $teacher = $request->user();
            $classRooms = $this->classSubjectService->getTeacherClassRooms($teacher->id);

            return response()->json([
                'success' => true,
                'data' => ClassRoomResource::collection($classRooms)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher classrooms',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
