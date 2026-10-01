<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\TeacherRequest;
use App\Http\Requests\TeacherSearchRequest;
use App\Http\Resources\Api\TeacherResource;
use App\Services\TeacherService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller
{
    private TeacherService $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Display a listing of teachers
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'name', 'email', 'photo', 'gender'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $teachers = $this->teacherService->searchTeachers(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return TeacherResource::collection($teachers);
            }

            // Filter by gender
            if ($request->filled('gender')) {
                $teachers = $this->teacherService->findTeachersByGender(
                    $request->string('gender'),
                    $fields,
                    $perPage
                );
                return TeacherResource::collection($teachers);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $teachers = $this->teacherService->getAll($fields);
                return TeacherResource::collection($teachers);
            }

            // Handle unassigned parameter
            if ($request->boolean('unassigned')) {
                $teachers = $this->teacherService->getUnassignedTeachers($fields);
                return TeacherResource::collection($teachers);
            }

            // Default paginated response
            $teachers = $this->teacherService->getPaginated($fields, $perPage);
            return TeacherResource::collection($teachers);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve teachers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified teacher
     */
    public function show(int $id)
    {
        try {
            $teacher = $this->teacherService->findTeacher($id, ['*']);
            return response()->json([
                'success' => true,
                'data' => new TeacherResource($teacher)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created teacher
     */
    public function store(TeacherRequest $request)
    {
        try {
            $teacher = $this->teacherService->createTeacher($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Teacher created successfully',
                'data' => new TeacherResource($teacher)
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
                'message' => 'Failed to create teacher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified teacher
     */
    public function update(TeacherRequest $request, int $id)
    {
        try {
            $teacher = $this->teacherService->updateTeacher($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Teacher updated successfully',
                'data' => new TeacherResource($teacher)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
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
                'message' => 'Failed to update teacher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified teacher
     */
    public function destroy(int $id)
    {
        try {
            $this->teacherService->deleteTeacher($id);
            return response()->json([
                'success' => true,
                'message' => 'Teacher deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete teacher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search teachers with pagination for frontend modal
     */
    public function search(TeacherSearchRequest $request)
    {
        try {

            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'name', 'email', 'photo'];

            $result = $this->teacherService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => TeacherResource::collection($result['data']),
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
                'message' => 'Failed to search teachers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get authenticated teacher's profile with subjects
     */
    public function profile(Request $request)
    {
        try {
            $teacher = $request->user();
            $teacherProfile = $this->teacherService->findTeacher($teacher->id);

            return response()->json([
                'success' => true,
                'data' => new TeacherResource($teacherProfile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
