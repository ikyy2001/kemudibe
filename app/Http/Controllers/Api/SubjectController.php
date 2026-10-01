<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTeacherRequest;
use App\Http\Requests\BulkDeleteSubjectsRequest;
use App\Http\Requests\SubjectRequest;
use App\Http\Requests\SubjectSearchRequest;
use App\Http\Requests\SubjectTeacherSubjectsSearchRequest;
use App\Http\Resources\Api\SubjectResource;
use App\Services\SubjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    private SubjectService $subjectService;

    public function __construct(SubjectService $subjectService)
    {
        $this->subjectService = $subjectService;
    }

    /**
     * Display a listing of subjects
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'name', 'photo', 'content', 'tagline', 'topic_id', 'teacher_id'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $subjects = $this->subjectService->searchSubjects(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return SubjectResource::collection($subjects);
            }

            // Filter by teacher
            if ($request->filled('teacher_id')) {
                $subjects = $this->subjectService->findSubjectsByTeacher(
                    $request->integer('teacher_id'),
                    $fields,
                    $perPage
                );
                return SubjectResource::collection($subjects);
            }

            // Filter by topic
            if ($request->filled('topic_id')) {
                $subjects = $this->subjectService->findSubjectsByTopic(
                    $request->integer('topic_id'),
                    $fields,
                    $perPage
                );
                return SubjectResource::collection($subjects);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $subjects = $this->subjectService->getAll($fields);
                return SubjectResource::collection($subjects);
            }

            // Default paginated response
            $subjects = $this->subjectService->getPaginated($fields, $perPage);
            return SubjectResource::collection($subjects);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified subject
     */
    public function show(int $id)
    {
        try {
            $subject = $this->subjectService->findSubject($id, ['*']);
            return response()->json([
                'success' => true,
                'data' => new SubjectResource($subject)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subject',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created subject
     */
    public function store(SubjectRequest $request)
    {
        try {
            $subject = $this->subjectService->createSubject($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Subject created successfully',
                'data' => new SubjectResource($subject)
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
                'message' => 'Failed to create subject',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified subject
     */
    public function update(SubjectRequest $request, int $id)
    {
        try {
            // Check authorization - managers can edit any subject, teachers can only edit their own
            $user = auth()->user();
            $subject = $this->subjectService->findSubject($id, ['id', 'teacher_id']);

            // If user is teacher, check if they own this subject
            if ($user->hasRole('teacher') && $subject->teacher_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit subjects assigned to you'
                ], 403);
            }

            $subject = $this->subjectService->updateSubject($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Subject updated successfully',
                'data' => new SubjectResource($subject)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
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
                'message' => 'Failed to update subject',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified subject
     */
    public function destroy(int $id)
    {
        try {
            $this->subjectService->deleteSubject($id);
            return response()->json([
                'success' => true,
                'message' => 'Subject deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subject',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * Assign subject to teacher
     */
    public function assignTeacher(AssignTeacherRequest $request, int $id)
    {
        try {

            $subject = $this->subjectService->assignToTeacher($id, $request->integer('teacher_id'));

            return response()->json([
                'success' => true,
                'message' => 'Teacher assigned successfully',
                'data' => new SubjectResource($subject)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
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
                'message' => 'Failed to assign teacher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove teacher assignment from subject
     */
    public function unassignTeacher(int $id)
    {
        try {
            $subject = $this->subjectService->unassignTeacher($id);

            return response()->json([
                'success' => true,
                'message' => 'Teacher unassigned successfully',
                'data' => new SubjectResource($subject)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign teacher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search subjects with pagination for frontend modal
     */
    public function search(SubjectSearchRequest $request)
    {
        try {

            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'name', 'tagline', 'photo', 'content', 'topic_id', 'teacher_id'];

            $result = $this->subjectService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => SubjectResource::collection($result['data']),
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
                'message' => 'Failed to search subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get subjects available for classroom assignment
     */
    public function availableForClassroom(int $classroomId)
    {
        try {
            $subjects = $this->subjectService->getAvailableForClassRoom($classroomId);
            return response()->json([
                'success' => true,
                'data' => SubjectResource::collection($subjects)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get subjects assigned to the authenticated teacher
     */
    public function teacherSubjects(Request $request)
    {
        try {
            $teacher = $request->user();
            $perPage = $request->integer('per_page', 6);

            // Handle search parameter
            if ($request->filled('search')) {
                $search = $request->string('search');
                $page = $request->integer('page', 1);
                $fields = ['id', 'name', 'tagline', 'photo', 'content', 'topic_id', 'teacher_id'];

                $result = $this->subjectService->searchTeacherSubjects($teacher->id, $search, $fields, $page, $perPage);

                return response()->json([
                    'success' => true,
                    'data' => SubjectResource::collection($result['data']),
                    'total' => $result['total'],
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'has_more' => $result['has_more']
                ]);
            }

            // Default: return all teacher subjects with pagination
            $subjects = $this->subjectService->getTeacherSubjects($teacher->id, $perPage);

            return SubjectResource::collection($subjects);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search subjects assigned to the authenticated teacher
     */
    public function teacherSubjectsSearch(SubjectTeacherSubjectsSearchRequest $request)
    {
        try {

            $teacher = $request->user();
            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'name', 'tagline', 'photo', 'content', 'topic_id', 'teacher_id'];

            $result = $this->subjectService->searchTeacherSubjects($teacher->id, $search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => SubjectResource::collection($result['data']),
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
                'message' => 'Failed to search teacher subjects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
