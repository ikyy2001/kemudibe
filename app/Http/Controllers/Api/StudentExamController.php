<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SubjectExamResource;
use App\Http\Resources\Api\ExamQuestionResource;
use App\Http\Resources\Api\StudentExamQuestionResource;
use App\Http\Resources\Api\ResultExamQuestionResource;
use App\Http\Resources\Api\QuestionAnswerResource;
use App\Http\Resources\Api\ResultQuestionAnswerResource;
use App\Http\Resources\Api\ExamAttemptResource;
use App\Services\StudentExamService;
use App\Http\Requests\StudentAnswerRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentExamController extends Controller
{
    private StudentExamService $studentExamService;

    public function __construct(StudentExamService $studentExamService)
    {
        $this->studentExamService = $studentExamService;
    }
    /**
     * Get exam details with questions for student
     */
    public function show(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            
            $result = $this->studentExamService->getExamForStudent($student->id, $examId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => new SubjectExamResource($result['exam']),
                    'questions' => StudentExamQuestionResource::collection($result['questions']),
                    'is_active' => $result['is_active'],
                    'can_take' => $result['can_take'],
                    'attempt' => $result['attempt'] ? new ExamAttemptResource($result['attempt']) : null,
                    'total_questions' => $result['total_questions'],
                    'total_points' => $result['total_points']
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $e->getMessage() === 'You do not have access to this exam' ? 403 : 500);
        }
    }
    
    /**
     * Start an exam attempt
     */
    public function startExam(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            
            $result = $this->studentExamService->startExam(
                $student->id, 
                $examId, 
                $request->input('token')
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Exam started successfully',
                'data' => [
                    'attempt' => new ExamAttemptResource($result['attempt']),
                    'device_token' => $result['device_token'],
                    'exam' => new SubjectExamResource($result['exam']),
                    'questions' => StudentExamQuestionResource::collection($result['questions']),
                    'time_remaining_minutes' => $result['time_remaining_minutes']
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
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
     * Submit answer to a question
     */
    public function submitAnswer(StudentAnswerRequest $request, int $examId, int $questionId)
    {
        try {
            $student = $request->user();
            $deviceToken = $request->header('X-Device-Token') ?: $request->input('device_token');
            
            $result = $this->studentExamService->submitAnswer(
                $student->id, 
                $examId, 
                $questionId, 
                $request->validated(),
                $deviceToken
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Answer submitted successfully',
                'data' => [
                    'answer' => new QuestionAnswerResource($result['answer']),
                    'question_type' => $result['question_type'],
                    'auto_graded' => $result['auto_graded'],
                    'points_earned' => $result['points_earned'],
                    'total_answered' => $result['total_answered'],
                    'total_questions' => $result['total_questions']
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam or question not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            if (isset($e->errorCode) && $e->errorCode === 'CONCURRENT_DEVICE_DETECTED') {
                return response()->json([
                    'success' => false,
                    'code' => 'CONCURRENT_DEVICE_DETECTED',
                    'message' => $e->getMessage()
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }
    
    /**
     * Complete and submit exam
     */
    public function completeExam(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            $forcedReason = $request->input('forced_reason');
            $deviceToken = $request->header('X-Device-Token') ?: $request->input('device_token');
            
            $result = $this->studentExamService->completeExam($student->id, $examId, $forcedReason, $deviceToken);
            
            return response()->json([
                'success' => true,
                'message' => 'Exam completed successfully',
                'data' => [
                    'attempt' => new ExamAttemptResource($result['attempt']),
                    'results' => $result['results']
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            if (isset($e->errorCode) && $e->errorCode === 'CONCURRENT_DEVICE_DETECTED') {
                return response()->json([
                    'success' => false,
                    'code' => 'CONCURRENT_DEVICE_DETECTED',
                    'message' => $e->getMessage()
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Log a proctoring violation from the client
     */
    public function logViolation(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            $violationType = $request->input('violation_type', 'unknown_violation');
            $details = $request->input('details');
            $deviceToken = $request->header('X-Device-Token') ?: $request->input('device_token');
            $weight = $request->has('weight') ? (float)$request->input('weight') : null;
            $durationSeconds = (int)$request->input('duration_seconds', 0);
            $triggerFreeze = $request->boolean('trigger_freeze', false);

            $result = $this->studentExamService->logViolation(
                $student->id,
                $examId,
                $violationType,
                $details,
                $deviceToken,
                $weight,
                $durationSeconds,
                $triggerFreeze
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $result['message']
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            if (isset($e->errorCode) && $e->errorCode === 'CONCURRENT_DEVICE_DETECTED') {
                return response()->json([
                    'success' => false,
                    'code' => 'CONCURRENT_DEVICE_DETECTED',
                    'message' => $e->getMessage()
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Verify Supervisor PIN to unlock frozen exam session
     */
    public function verifySupervisorPin(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            $pin = (string)$request->input('pin', '');

            $result = $this->studentExamService->verifySupervisorPin($student->id, $examId, $pin);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'attempt' => new ExamAttemptResource($result['attempt']),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Heartbeat check
     */
    public function heartbeat(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            $deviceToken = $request->header('X-Device-Token') ?: $request->input('device_token');

            $result = $this->studentExamService->heartbeat(
                $student->id,
                $examId,
                $deviceToken
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            if (isset($e->errorCode) && $e->errorCode === 'CONCURRENT_DEVICE_DETECTED') {
                return response()->json([
                    'success' => false,
                    'code' => 'CONCURRENT_DEVICE_DETECTED',
                    'message' => $e->getMessage()
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }
    
    /**
     * Get current exam progress
     */
    public function getProgress(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            
            $result = $this->studentExamService->getProgress($student->id, $examId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'attempt' => new ExamAttemptResource($result['attempt']),
                    'progress' => $result['progress'],
                    'answers' => QuestionAnswerResource::collection($result['answers'])
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
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
     * Get exam results (completed exams only)
     */
    public function getResults(Request $request, int $examId)
    {
        try {
            $student = $request->user();
            
            $result = $this->studentExamService->getResults($student->id, $examId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => $result['exam'],
                    'attempt' => new ExamAttemptResource($result['attempt']),
                    'results' => $result['results'],
                    'answers' => ResultQuestionAnswerResource::collection($result['answers'])
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $e->getMessage() === 'No completed exam found' ? 404 : 400);
        }
    }
    
}