<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\QuestionOptionBulkStoreRequest;
use App\Http\Requests\QuestionOptionBulkUpdateRequest;
use App\Http\Requests\QuestionOptionReorderRequest;
use App\Http\Requests\QuestionOptionRequest;
use App\Http\Requests\QuestionOptionSearchRequest;
use App\Http\Requests\QuestionOptionSetCorrectRequest;
use App\Http\Resources\Api\QuestionOptionResource;
use App\Services\QuestionOptionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestionOptionController extends Controller
{
    private QuestionOptionService $questionOptionService;

    public function __construct(QuestionOptionService $questionOptionService)
    {
        $this->questionOptionService = $questionOptionService;
    }

    /**
     * Display a listing of question options
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->integer('per_page', 6);
            $filters = [];

            // Apply filters
            if ($request->filled('exam_question_id')) {
                $filters['exam_question_id'] = $request->integer('exam_question_id');
            }

            if ($request->filled('is_correct')) {
                $filters['is_correct'] = $request->boolean('is_correct');
            }

            if ($request->filled('subject_exam_id')) {
                $filters['subject_exam_id'] = $request->integer('subject_exam_id');
            }

            if ($request->filled('subject_id')) {
                $filters['subject_id'] = $request->integer('subject_id');
            }

            if ($request->filled('teacher_id')) {
                $filters['teacher_id'] = $request->integer('teacher_id');
            }

            // Handle search
            if ($request->filled('search')) {
                $options = $this->questionOptionService->searchOptions(
                    $request->string('search'),
                    $perPage
                );
                return QuestionOptionResource::collection($options);
            }

            $options = $this->questionOptionService->getPaginated($filters, $perPage);

            return QuestionOptionResource::collection($options);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve question options',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified question option
     */
    public function show(int $id)
    {
        try {
            $option = $this->questionOptionService->findQuestionOption($id);

            return response()->json([
                'success' => true,
                'data' => new QuestionOptionResource($option)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question option not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve question option',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created question option
     */
    public function store(QuestionOptionRequest $request)
    {
        try {
            $data = $request->validated();
            $option = $this->questionOptionService->createQuestionOption($data);

            return response()->json([
                'success' => true,
                'message' => 'Question option created successfully',
                'data' => new QuestionOptionResource($option)
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
     * Update the specified question option
     */
    public function update(QuestionOptionRequest $request, int $id)
    {
        try {
            $data = $request->validated();
            $option = $this->questionOptionService->updateQuestionOption($id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Question option updated successfully',
                'data' => new QuestionOptionResource($option)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question option not found'
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
     * Remove the specified question option
     */
    public function destroy(int $id)
    {
        try {
            $this->questionOptionService->deleteQuestionOption($id);

            return response()->json([
                'success' => true,
                'message' => 'Question option deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question option not found'
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
     * Get options by exam question
     */
    public function getByExamQuestion(int $examQuestionId)
    {
        try {
            $options = $this->questionOptionService->getOptionsByExamQuestion($examQuestionId);

            return response()->json([
                'success' => true,
                'data' => QuestionOptionResource::collection($options)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve options for this question',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get correct options for a question
     */
    public function getCorrectOptions(int $examQuestionId)
    {
        try {
            $options = $this->questionOptionService->getCorrectOptions($examQuestionId);

            return response()->json([
                'success' => true,
                'data' => QuestionOptionResource::collection($options)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve correct options',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Set correct option for a question
     */
    public function setCorrectOption(QuestionOptionSetCorrectRequest $request, int $examQuestionId)
    {
        try {

            $option = $this->questionOptionService->setCorrectOption(
                $examQuestionId,
                $request->integer('option_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Correct option set successfully',
                'data' => new QuestionOptionResource($option)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question or option not found'
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
     * Bulk create options for a question
     */
    public function bulkStore(QuestionOptionBulkStoreRequest $request, int $examQuestionId)
    {
        try {

            $options = $this->questionOptionService->bulkCreateOptionsForQuestion(
                $examQuestionId,
                $request->input('options')
            );

            return response()->json([
                'success' => true,
                'message' => 'Options created successfully',
                'data' => QuestionOptionResource::collection($options)
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
     * Bulk update options
     */
    public function bulkUpdate(QuestionOptionBulkUpdateRequest $request)
    {
        try {

            $result = $this->questionOptionService->bulkUpdateOptions($request->input('options'));

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$result['updated_count']} options",
                'updated_count' => $result['updated_count'],
                'errors' => $result['errors']
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
                'message' => 'Failed to update options',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Get option statistics
     */
    public function statistics(int $examQuestionId)
    {
        try {
            $statistics = $this->questionOptionService->getOptionStatistics($examQuestionId);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve option statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search options
     */
    public function search(QuestionOptionSearchRequest $request)
    {
        try {

            $perPage = $request->integer('per_page', 6);
            $options = $this->questionOptionService->searchOptions(
                $request->string('query'),
                $perPage
            );

            return QuestionOptionResource::collection($options);
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
     * Reorder options for a question
     */
    public function reorder(QuestionOptionReorderRequest $request, int $examQuestionId)
    {
        try {

            $options = $this->questionOptionService->reorderOptions(
                $examQuestionId,
                $request->input('order')
            );

            return response()->json([
                'success' => true,
                'message' => 'Options reordered successfully',
                'data' => QuestionOptionResource::collection($options)
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
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }
}
