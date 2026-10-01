<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionAnswerBulkGradeRequest;
use App\Http\Requests\QuestionAnswerGradeRequest;
use App\Http\Requests\QuestionAnswerRequest;
use App\Http\Resources\Api\QuestionAnswerResource;
use App\Services\QuestionAnswerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestionAnswerController extends Controller
{
    private QuestionAnswerService $questionAnswerService;

    public function __construct(QuestionAnswerService $questionAnswerService)
    {
        $this->questionAnswerService = $questionAnswerService;
    }

    /**
     * Display a listing of question answers
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

            if ($request->filled('question_id')) {
                $filters['question_id'] = $request->integer('question_id');
            }

            if ($request->filled('has_passed')) {
                $filters['has_passed'] = $request->boolean('has_passed');
            }

            if ($request->filled('min_points')) {
                $filters['min_points'] = $request->integer('min_points');
            }

            if ($request->filled('max_points')) {
                $filters['max_points'] = $request->integer('max_points');
            }

            $answers = $this->questionAnswerService->getPaginated($filters, $perPage);

            return QuestionAnswerResource::collection($answers);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve question answers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified question answer
     */
    public function show(int $id)
    {
        try {
            $answer = $this->questionAnswerService->findQuestionAnswer($id);

            return response()->json([
                'success' => true,
                'data' => new QuestionAnswerResource($answer)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question answer not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve question answer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Grade essay answers (Teachers only)
     */
    public function gradeAnswer(QuestionAnswerGradeRequest $request, int $id)
    {
        try {
            $answer = $this->questionAnswerService->gradeAnswer($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Answer graded successfully',
                'data' => new QuestionAnswerResource($answer)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question answer not found'
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
     * Bulk grade multiple answers
     */
    public function bulkGrade(QuestionAnswerBulkGradeRequest $request)
    {
        try {

            $result = $this->questionAnswerService->bulkGrade($request->input('answers'));

            return response()->json([
                'success' => true,
                'message' => "Successfully graded {$result['graded_count']} answers",
                'graded_count' => $result['graded_count'],
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
                'message' => 'Failed to perform bulk grading',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get answers needing grading (essay questions with 0 points)
     */
    public function needsGrading(Request $request)
    {
        try {
            $perPage = $request->integer('per_page', 6);
            $filters = [];

            if ($request->filled('exam_id')) {
                $filters['exam_id'] = $request->integer('exam_id');
            }

            if ($request->filled('teacher_id')) {
                $filters['teacher_id'] = $request->integer('teacher_id');
            }

            $answers = $this->questionAnswerService->needsGrading($filters, $perPage);

            return QuestionAnswerResource::collection($answers);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve answers needing grading',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get student's answers for a specific exam
     */
    public function getStudentAnswers(Request $request, int $studentId, int $examId)
    {
        try {
            $result = $this->questionAnswerService->getStudentAnswers($studentId, $examId);

            return response()->json([
                'success' => true,
                'data' => [
                    'answers' => QuestionAnswerResource::collection($result['answers']),
                    'summary' => $result['summary']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $e->getMessage() === 'No answers found for this student and exam' ? 404 : 500);
        }
    }

}
