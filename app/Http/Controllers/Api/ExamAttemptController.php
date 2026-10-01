<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamAttemptBulkActionRequest;
use App\Http\Requests\ExamAttemptRequest;
use App\Http\Resources\Api\ExamAttemptResource;
use App\Services\ExamAttemptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamAttemptController extends Controller
{
    private ExamAttemptService $examAttemptService;

    public function __construct(ExamAttemptService $examAttemptService)
    {
        $this->examAttemptService = $examAttemptService;
    }
    /**
     * Display a listing of exam attempts
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->integer('per_page', 6);
            $filters = [];

            if ($request->filled('student_id')) {
                $filters['student_id'] = $request->integer('student_id');
            }

            if ($request->filled('exam_id')) {
                $filters['exam_id'] = $request->integer('exam_id');
            }

            if ($request->filled('status')) {
                $filters['status'] = $request->string('status');
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $filters['start_date'] = $request->string('start_date');
                $filters['end_date'] = $request->string('end_date');
            }

            $attempts = $this->examAttemptService->getPaginated($filters, $perPage);

            return ExamAttemptResource::collection($attempts);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve exam attempts',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified exam attempt
     */
    public function show(int $id)
    {
        try {
            $attempt = $this->examAttemptService->findExamAttempt($id);

            return response()->json([
                'success' => true,
                'data' => new ExamAttemptResource($attempt)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam attempt not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam attempt',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created exam attempt
     */
    public function store(ExamAttemptRequest $request)
    {
        try {
            $attempt = $this->examAttemptService->createExamAttempt($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Exam attempt created successfully',
                'data' => new ExamAttemptResource($attempt->load(['student', 'subjectExam.subject']))
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
            ], $e->getMessage() === 'Student already has an attempt for this exam' ? 409 : 500);
        }
    }

    /**
     * Update the specified exam attempt
     */
    public function update(ExamAttemptRequest $request, int $id)
    {
        try {
            $attempt = $this->examAttemptService->updateExamAttempt($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Exam attempt updated successfully',
                'data' => new ExamAttemptResource($attempt)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam attempt not found'
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
     * Remove the specified exam attempt
     */
    public function destroy(int $id)
    {
        try {
            $this->examAttemptService->deleteExamAttempt($id);

            return response()->json([
                'success' => true,
                'message' => 'Exam attempt deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam attempt not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exam attempt',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get statistics for exam attempts
     */
    public function statistics(Request $request)
    {
        try {
            $filters = [];

            if ($request->filled('exam_id')) {
                $filters['exam_id'] = $request->integer('exam_id');
            }

            if ($request->filled('student_id')) {
                $filters['student_id'] = $request->integer('student_id');
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $filters['start_date'] = $request->string('start_date');
                $filters['end_date'] = $request->string('end_date');
            }

            $stats = $this->examAttemptService->getStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get student's exam attempt for specific exam
     */
    public function getStudentAttempt(Request $request, int $studentId, int $examId)
    {
        try {
            $result = $this->examAttemptService->getStudentAttempt($studentId, $examId);

            return response()->json([
                'success' => true,
                'data' => [
                    'attempt' => new ExamAttemptResource($result['attempt']),
                    'answers' => \App\Http\Resources\Api\QuestionAnswerResource::collection($result['answers']),
                    'progress' => $result['progress']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $e->getMessage() === 'No exam attempt found for this student and exam' ? 404 : 500);
        }
    }
}
