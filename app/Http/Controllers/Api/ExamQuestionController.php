<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamQuestionRequest;
use App\Http\Requests\DuplicateQuestionRequest;
use App\Http\Requests\BulkStoreQuestionsRequest;
use App\Http\Requests\BulkDeleteExamQuestionsRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\Api\ExamQuestionResource;
use App\Services\ExamQuestionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamQuestionController extends Controller
{
    private ExamQuestionService $examQuestionService;

    public function __construct(ExamQuestionService $examQuestionService)
    {
        $this->examQuestionService = $examQuestionService;
    }

    /**
     * Display a listing of exam questions
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->integer('per_page', 6);
            $filters = [];

            // Apply filters
            if ($request->filled('subject_exam_id')) {
                $filters['subject_exam_id'] = $request->integer('subject_exam_id');
            }

            if ($request->filled('type')) {
                $filters['type'] = $request->string('type');
            }

            if ($request->filled('subject_id')) {
                $filters['subject_id'] = $request->integer('subject_id');
            }

            if ($request->filled('teacher_id')) {
                $filters['teacher_id'] = $request->integer('teacher_id');
            }

            if ($request->filled('min_points')) {
                $filters['min_points'] = $request->integer('min_points');
            }

            if ($request->filled('max_points')) {
                $filters['max_points'] = $request->integer('max_points');
            }

            // Handle search
            if ($request->filled('search')) {
                $questions = $this->examQuestionService->searchQuestions(
                    $request->string('search'),
                    ['*'],
                    $perPage
                );
                return ExamQuestionResource::collection($questions);
            }

            // Handle all=true parameter
            if ($request->boolean('all')) {
                $questions = $this->examQuestionService->getAll($filters);
                return ExamQuestionResource::collection($questions);
            }

            $questions = $this->examQuestionService->getPaginated($filters, $perPage);

            return ExamQuestionResource::collection($questions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve exam questions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified exam question
     */
    public function show(int $id)
    {
        try {
            $question = $this->examQuestionService->findExamQuestion($id);

            return response()->json([
                'success' => true,
                'data' => new ExamQuestionResource($question)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam question not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam question',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created exam question
     */
    public function store(ExamQuestionRequest $request)
    {
        try {
            $data = $request->validated();
            if (!isset($data['timer']) || $data['timer'] === null) {
                $data['timer'] = 0;
            }

            // Handle options from request
            if ($request->has('options')) {
                $data['options'] = $request->input('options');
            }

            $question = $this->examQuestionService->createExamQuestion($data);

            return response()->json([
                'success' => true,
                'message' => 'Exam question created successfully',
                'data' => new ExamQuestionResource($question)
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
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Update the specified exam question
     */
    public function update(ExamQuestionRequest $request, int $id)
    {
        try {
            $data = $request->validated();
            if (!isset($data['timer']) || $data['timer'] === null) {
                $data['timer'] = 0;
            }

            // Handle options from request
            if ($request->has('options')) {
                $data['options'] = $request->input('options');
            }

            $question = $this->examQuestionService->updateExamQuestion($id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Exam question updated successfully',
                'data' => new ExamQuestionResource($question)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam question not found'
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
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Remove the specified exam question
     */
    public function destroy(int $id)
    {
        try {
            $this->examQuestionService->deleteExamQuestion($id);

            return response()->json([
                'success' => true,
                'message' => 'Exam question deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam question not found'
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
     * Get questions by subject exam
     */
    public function getBySubjectExam(int $subjectExamId)
    {
        try {
            $questions = $this->examQuestionService->getQuestionsBySubjectExam($subjectExamId);

            return response()->json([
                'success' => true,
                'data' => ExamQuestionResource::collection($questions)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve questions for this exam',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Duplicate question to another exam
     */
    public function duplicate(DuplicateQuestionRequest $request, int $id)
    {
        try {
            $question = $this->examQuestionService->duplicateQuestion(
                $id,
                $request->integer('subject_exam_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Question duplicated successfully',
                'data' => new ExamQuestionResource($question)
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question or target exam not found'
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
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Bulk create questions
     */
    public function bulkStore(BulkStoreQuestionsRequest $request)
    {
        try {
            $result = $this->examQuestionService->bulkCreateQuestions($request->input('questions'));

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$result['created_count']} questions",
                'data' => ExamQuestionResource::collection($result['created']),
                'created_count' => $result['created_count'],
                'error_count' => $result['error_count'],
                'errors' => $result['errors']
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
                'message' => 'Failed to create questions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * Get exam statistics
     */
    public function statistics(int $subjectExamId)
    {
        try {
            $statistics = $this->examQuestionService->getExamStatistics($subjectExamId);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search questions
     */
    public function search(SearchRequest $request)
    {
        try {
            $perPage = $request->getLimit(6);
            $questions = $this->examQuestionService->searchQuestions(
                $request->getSearchQuery(),
                ['*'],
                $perPage
            );

            return ExamQuestionResource::collection($questions);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get questions for grading
     */
    public function forGrading(int $subjectExamId)
    {
        try {
            $questions = $this->examQuestionService->getQuestionsForGrading($subjectExamId);

            return response()->json([
                'success' => true,
                'data' => ExamQuestionResource::collection($questions)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve questions for grading',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
