<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\SubjectExamDuplicateRequest;
use App\Http\Requests\SubjectExamRequest;
use App\Http\Requests\SubjectExamSearchRequest;
use App\Http\Resources\Api\SubjectExamResource;
use App\Services\SubjectExamService;
use App\Services\SubjectService;
use App\Services\ExamAttemptService;
use App\Services\StudentService;
use App\Services\QuestionAnswerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubjectExamController extends Controller
{
    private SubjectExamService $subjectExamService;
    private SubjectService $subjectService;
    private ExamAttemptService $examAttemptService;
    private StudentService $studentService;
    private QuestionAnswerService $questionAnswerService;

    public function __construct(
        SubjectExamService $subjectExamService,
        SubjectService $subjectService,
        ExamAttemptService $examAttemptService,
        StudentService $studentService,
        QuestionAnswerService $questionAnswerService
    ) {
        $this->subjectExamService = $subjectExamService;
        $this->subjectService = $subjectService;
        $this->examAttemptService = $examAttemptService;
        $this->studentService = $studentService;
        $this->questionAnswerService = $questionAnswerService;
    }

    /**
     * Display a listing of exams
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'subject_id', 'name', 'about', 'started_at', 'ended_at'];
            $perPage = $request->integer('per_page', 6);

            // Handle search
            if ($request->filled('search')) {
                $exams = $this->subjectExamService->searchExams(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return SubjectExamResource::collection($exams);
            }

            // Filter by subject
            if ($request->filled('subject_id')) {
                $exams = $this->subjectExamService->findExamsBySubject(
                    $request->integer('subject_id'),
                    $fields
                );
                return SubjectExamResource::collection($exams);
            }

            // Filter by teacher
            if ($request->filled('teacher_id')) {
                $exams = $this->subjectExamService->findExamsByTeacher(
                    $request->integer('teacher_id'),
                    $fields
                );
                return SubjectExamResource::collection($exams);
            }

            // Filter by topic
            if ($request->filled('topic_id')) {
                $exams = $this->subjectExamService->findExamsByTopic(
                    $request->integer('topic_id'),
                    $fields
                );
                return SubjectExamResource::collection($exams);
            }

            // Filter by status
            if ($request->filled('status')) {
                $status = $request->string('status');
                switch ($status) {
                    case 'active':
                        $exams = $this->subjectExamService->getActiveExams($fields);
                        break;
                    case 'upcoming':
                        $exams = $this->subjectExamService->getUpcomingExams($fields);
                        break;
                    case 'completed':
                        $exams = $this->subjectExamService->getCompletedExams($fields);
                        break;
                    default:
                        $exams = collect();
                }
                return SubjectExamResource::collection($exams);
            }

            // Filter by date range
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $exams = $this->subjectExamService->findExamsByDateRange(
                    $request->string('start_date'),
                    $request->string('end_date'),
                    $fields,
                    $perPage
                );
                return SubjectExamResource::collection($exams);
            }

            // Filter for student (exams available to student)
            if ($request->filled('student_id')) {
                $exams = $this->subjectExamService->getExamsForStudent(
                    $request->integer('student_id'),
                    $fields
                );
                return SubjectExamResource::collection($exams);
            }

            // Get exams needing grading
            if ($request->boolean('needs_grading') && $request->filled('teacher_id')) {
                $exams = $this->subjectExamService->getExamsNeedingGrading(
                    $request->integer('teacher_id'),
                    $fields
                );
                return SubjectExamResource::collection($exams);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $exams = $this->subjectExamService->getAll($fields);
                return SubjectExamResource::collection($exams);
            }

            // Default paginated response
            $exams = $this->subjectExamService->getPaginated($fields, $perPage);
            return SubjectExamResource::collection($exams);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve exams',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified exam
     */
    public function show(int $id)
    {
        try {
            $exam = $this->subjectExamService->findExamWithQuestions($id);
            return response()->json([
                'success' => true,
                'data' => new SubjectExamResource($exam)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new exam
     */
    public function store(SubjectExamRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Check authorization - managers can create for any subject, teachers only for their own
            if ($user->hasRole('teacher')) {
                try {
                    $subject = $this->subjectService->findSubject($data['subject_id'], ['id', 'teacher_id']);
                    if ($subject->teacher_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You can only create exams for your assigned subjects'
                        ], 403);
                    }
                } catch (ModelNotFoundException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Subject not found'
                    ], 404);
                }
            }

            $exam = $this->subjectExamService->createExam($data);
            return response()->json([
                'success' => true,
                'message' => 'Exam created successfully',
                'data' => new SubjectExamResource($exam)
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
                'message' => 'Failed to create exam: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified exam
     */
    public function update(SubjectExamRequest $request, int $id)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Check authorization - managers can update any exam, teachers only their own
            if ($user->hasRole('teacher')) {
                try {
                    $exam = $this->subjectExamService->findExam($id, ['*']);
                    if ($exam->subject->teacher_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Exam not found or you do not have permission to edit it'
                        ], 404);
                    }

                    // If subject_id is being changed, verify the new subject belongs to this teacher
                    if (isset($data['subject_id']) && $data['subject_id'] !== $exam->subject_id) {
                        try {
                            $newSubject = $this->subjectService->findSubject($data['subject_id'], ['id', 'teacher_id']);
                            if ($newSubject->teacher_id !== $user->id) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'You can only assign exams to your own subjects'
                                ], 403);
                            }
                        } catch (ModelNotFoundException $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Subject not found'
                            ], 404);
                        }
                    }
                } catch (ModelNotFoundException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to edit it'
                    ], 404);
                }
            }

            $exam = $this->subjectExamService->updateExam($id, $data);
            return response()->json([
                'success' => true,
                'message' => 'Exam updated successfully',
                'data' => new SubjectExamResource($exam)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
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
                'message' => 'Failed to update exam: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified exam
     */
    public function destroy(Request $request, int $id)
    {
        try {
            $user = $request->user();

            // Check authorization - managers can delete any exam, teachers only their own
            if ($user->hasRole('teacher')) {
                try {
                    $exam = $this->subjectExamService->findExam($id, ['*']);
                    if ($exam->subject->teacher_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Exam not found or you do not have permission to delete it'
                        ], 404);
                    }

                    // Check if exam has been attempted by students using service
                    $attempts = $this->examAttemptService->getAll(['exam_id' => $id]);
                    if ($attempts->isNotEmpty()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot delete exam that has been attempted by students. Consider archiving it instead.'
                        ], 422);
                    }
                } catch (ModelNotFoundException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to delete it'
                    ], 404);
                }
            }

            $this->subjectExamService->deleteExam($id);
            return response()->json([
                'success' => true,
                'message' => 'Exam deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exam: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search exams with pagination for frontend modal
     */
    public function search(SubjectExamSearchRequest $request)
    {
        try {

            $search = $request->get('q', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'subject_id', 'name', 'started_at', 'ended_at'];

            $result = $this->subjectExamService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => SubjectExamResource::collection($result['data']),
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
                'message' => 'Failed to search exams',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * Duplicate exam
     */
    public function duplicate(SubjectExamDuplicateRequest $request, int $id)
    {
        try {

            $newExam = $this->subjectExamService->duplicateExam($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Exam duplicated successfully',
                'data' => new SubjectExamResource($newExam)
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Original exam not found'
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
                'message' => 'Failed to duplicate exam: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get exam statistics
     */
    public function statistics(int $id)
    {
        try {
            $statistics = $this->subjectExamService->getExamStatistics($id);
            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get exam status
     */
    public function status(int $id)
    {
        try {
            $status = $this->subjectExamService->getExamStatus($id);
            $canEdit = $this->subjectExamService->canEditExam($id);
            $canDelete = $this->subjectExamService->canDeleteExam($id);
            $remainingTime = $this->subjectExamService->getRemainingTime($id);
            $duration = $this->subjectExamService->getExamDuration($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $status,
                    'can_edit' => $canEdit,
                    'can_delete' => $canDelete,
                    'remaining_time_minutes' => $remainingTime,
                    'duration_minutes' => $duration
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
                'message' => 'Failed to retrieve exam status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Get exams for authenticated teacher
     */
    public function teacherExams(Request $request)
    {
        try {
            $teacher = $request->user();
            $perPage = $request->integer('per_page', 6);

            $exams = $this->subjectExamService->getTeacherExams($teacher->id, $perPage);

            return SubjectExamResource::collection($exams);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher exams',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create exam for authenticated teacher's subject
     */
    public function createTeacherExam(SubjectExamRequest $request)
    {
        try {
            $teacher = $request->user();
            $data = $request->validated();

            // Verify that the subject belongs to this teacher
            try {
                $subject = $this->subjectService->findSubject($data['subject_id'], ['id', 'teacher_id']);
                if ($subject->teacher_id !== $teacher->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only create exams for your assigned subjects'
                    ], 403);
                }
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject not found'
                ], 404);
            }

            // Check if exam name already exists for this subject
            $existingExams = $this->subjectExamService->findExamsBySubject($data['subject_id'], ['id', 'name']);
            $nameExists = $existingExams->contains('name', $data['name']);

            if ($nameExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'An exam with this name already exists for this subject. Please choose a different name.',
                    'errors' => [
                        'name' => ['This exam name is already used for this subject']
                    ]
                ], 422);
            }

            $exam = $this->subjectExamService->createExam($data);

            return response()->json([
                'success' => true,
                'message' => 'Exam created successfully',
                'data' => new SubjectExamResource($exam)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            if ($e->errorInfo[1] == 1062) { // Duplicate entry error
                return response()->json([
                    'success' => false,
                    'message' => 'An exam with this name already exists for this subject. Please choose a different name.',
                    'errors' => [
                        'name' => ['This exam name is already used for this subject']
                    ]
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating the exam. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the exam. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get exams for a specific subject available to authenticated student
     */
    public function getSubjectExams(Request $request, int $subjectId)
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

            // Check student access to subject using service
            $studentData = $this->studentService->findStudent($student->id, ['*']);
            $studentClassrooms = $studentData->classStudents->load(['classRoom.classSubjects']);
            
            $hasAccess = $studentClassrooms->pluck('classRoom.classSubjects')->flatten()
                ->contains('subject_id', $subjectId);

            if (!$hasAccess) {
                // Add debug info
                $debugInfo = config('app.debug') ? [
                    'student_id' => $student->id,
                    'subject_id' => $subjectId,
                    'enrolled_classrooms' => $studentClassrooms->pluck('classRoom.id'),
                    'classrooms_with_subject' => $studentClassrooms->filter(function($enrollment) {
                        return $enrollment->classRoom->classSubjects->isNotEmpty();
                    })->pluck('classRoom.id')
                ] : null;

                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to exams for this subject',
                    'debug' => $debugInfo
                ], 403);
            }

            // Get exams for this subject with all necessary fields and relationships
            $exams = $this->subjectExamService->findExamsBySubject(
                $subjectId,
                ['id', 'subject_id', 'name', 'about', 'total_points', 'started_at', 'ended_at']
            );

            // Get student's exam attempts for these exams
            $examIds = $exams->pluck('id');
            $allAttempts = collect();
            foreach ($examIds as $examId) {
                $attempts = $this->examAttemptService->getAll([
                    'student_id' => $student->id,
                    'exam_id' => $examId
                ]);
                $allAttempts = $allAttempts->merge($attempts);
            }
            $studentAttempts = $allAttempts->keyBy('subject_exam_id');

            // Add student-specific status to each exam
            $examsWithStudentStatus = $exams->map(function($exam) use ($studentAttempts) {
                $attempt = $studentAttempts->get($exam->id);

                // Set student-specific attributes for the resource
                $exam->student_attempt = $attempt;

                return $exam;
            });

            // Use SubjectExamResource for consistent response format
            return SubjectExamResource::collection($examsWithStudentStatus);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subject exams',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get exams available to authenticated student
     */
    public function studentExams(Request $request)
    {
        try {
            $student = $request->user();

            // Get exams available to student through classroom enrollment
            $exams = $this->subjectExamService->getExamsForStudent($student->id, ['*']);

            // Get all attempts for this student
            $examIds = $exams->pluck('id');
            $allAttempts = collect();
            foreach ($examIds as $examId) {
                $attempts = $this->examAttemptService->getAll([
                    'student_id' => $student->id,
                    'exam_id' => $examId
                ]);
                $allAttempts = $allAttempts->merge($attempts);
            }
            $studentAttempts = $allAttempts->keyBy('subject_exam_id');

            // Add attempt status for each exam
            $examsWithStatus = $exams->map(function($exam) use ($studentAttempts) {
                $attempt = $studentAttempts->get($exam->id);

                $examData = $exam->toArray();
                $examData['attempt_status'] = [
                    'has_attempt' => $attempt !== null,
                    'is_completed' => $attempt ? $attempt->is_completed : false,
                    'answered_questions' => $attempt ? $attempt->answered_questions : 0,
                    'completed_at' => $attempt ? $attempt->completed_at : null,
                ];

                // Check if exam is currently active
                $now = now();
                $examData['is_active'] = $now->between($exam->started_at, $exam->ended_at);
                $examData['can_take'] = $examData['is_active'] && !$examData['attempt_status']['is_completed'];

                return $examData;
            });

            return response()->json([
                'success' => true,
                'data' => $examsWithStatus,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student exams',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get students status for teacher's exam (Teacher Dashboard)
     */
    public function getExamStudentsStatus(Request $request, int $examId)
    {
        try {
            $teacher = $request->user();

            // Verify that the exam belongs to the teacher's subject
            try {
                $exam = $this->subjectExamService->findExam($examId, ['*']);
                if ($exam->subject->teacher_id !== $teacher->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to view it'
                    ], 404);
                }
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exam not found or you do not have permission to view it'
                ], 404);
            }

            // Get all students who have access to this exam through classroom enrollment
            $allStudents = $this->studentService->getAll(['*']);
            $studentsWithAccess = $allStudents->filter(function($student) use ($exam) {
                return $student->classStudents
                    ->pluck('classRoom.classSubjects')
                    ->flatten()
                    ->contains('subject_id', $exam->subject_id);
            });

            // Get exam attempts for all students
            $examAttempts = $this->examAttemptService->getAll(['exam_id' => $examId])
                ->keyBy('student_id');

            // Categorize students by status
            $notStarted = collect();
            $inProgress = collect();
            $completed = collect();

            foreach ($studentsWithAccess as $student) {
                $attempt = $examAttempts->get($student->id);

                $studentData = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'photo' => $student->photo,
                    'classrooms' => $student->classStudents->map(function($enrollment) {
                        return [
                            'id' => $enrollment->classRoom->id,
                            'name' => $enrollment->classRoom->name
                        ];
                    })
                ];

                if (!$attempt) {
                    // Student hasn't started the exam
                    $studentData['status'] = 'not_started';
                    $studentData['attempt'] = null;
                    $notStarted->push($studentData);
                } elseif ($attempt->is_completed) {
                    // Student completed the exam
                    $studentData['status'] = 'completed';
                    $studentData['attempt'] = [
                        'id' => $attempt->id,
                        'answered_questions' => $attempt->answered_questions,
                        'total_questions' => $attempt->total_questions,
                        'total_attempts' => $attempt->total_attempts,
                        'completed_at' => $attempt->completed_at,
                        'started_at' => $attempt->created_at
                    ];
                    $completed->push($studentData);
                } else {
                    // Student started but didn't finish
                    $studentData['status'] = 'in_progress';
                    $studentData['attempt'] = [
                        'id' => $attempt->id,
                        'answered_questions' => $attempt->answered_questions,
                        'total_questions' => $attempt->total_questions,
                        'total_attempts' => $attempt->total_attempts,
                        'started_at' => $attempt->created_at,
                        'last_activity' => $attempt->updated_at
                    ];
                    $inProgress->push($studentData);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => [
                        'id' => $exam->id,
                        'name' => $exam->name,
                        'subject' => $exam->subject->name,
                        'started_at' => $exam->started_at,
                        'ended_at' => $exam->ended_at
                    ],
                    'statistics' => [
                        'total_students' => $studentsWithAccess->count(),
                        'not_started' => $notStarted->count(),
                        'in_progress' => $inProgress->count(),
                        'completed' => $completed->count(),
                        'completion_rate' => $studentsWithAccess->count() > 0 ?
                            round(($completed->count() / $studentsWithAccess->count()) * 100, 2) : 0
                    ],
                    'students' => [
                        'not_started' => $notStarted->values(),
                        'in_progress' => $inProgress->values(),
                        'completed' => $completed->values()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam students status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get individual student's detailed exam answers (Teacher Dashboard)
     */
    public function getStudentExamDetails(Request $request, int $examId, int $studentId)
    {
        try {
            $teacher = $request->user();

            // Verify that the exam belongs to the teacher's subject
            try {
                $exam = $this->subjectExamService->findExamWithQuestions($examId);
                if ($exam->subject->teacher_id !== $teacher->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to view it'
                    ], 404);
                }
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exam not found or you do not have permission to view it'
                ], 404);
            }

            // Get student
            try {
                $student = $this->studentService->findStudent($studentId, ['id', 'name', 'email', 'photo']);
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Get student's exam attempt
            $attempts = $this->examAttemptService->getAll([
                'student_id' => $studentId,
                'exam_id' => $examId
            ]);
            $attempt = $attempts->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student has not attempted this exam'
                ], 404);
            }

            // Get student's answers using the service
            $answers = $this->questionAnswerService->getAll([
                'student_id' => $studentId,
                'exam_id' => $examId
            ]);

            // Calculate scores
            $totalPointsEarned = $answers->sum('points_earned');
            $totalPossiblePoints = $exam->examQuestions->sum('points');
            $percentage = $totalPossiblePoints > 0 ?
                round(($totalPointsEarned / $totalPossiblePoints) * 100, 2) : 0;

            // Format answers with detailed info
            $formattedAnswers = $answers->map(function($answer) {
                $question = $answer->examQuestion;
                $questionData = [
                    'id' => $question->id,
                    'text' => $question->name,
                    'type' => $question->type,
                    'points' => $question->points,
                    'timer' => $question->timer
                ];

                if ($question->type === 'multiple_choice') {
                    $correctOption = $question->questionOptions->where('is_correct', true)->first();
                    $questionData['options'] = $question->questionOptions->map(function($option) use ($answer, $correctOption) {
                        return [
                            'id' => $option->id,
                            'text' => $option->name,
                            'is_correct' => $option->is_correct,
                            'is_student_answer' => trim($answer->answer_text) === trim($option->name)
                        ];
                    });
                    $questionData['correct_answer'] = $correctOption ? $correctOption->name : null;
                }

                return [
                    'answer_id' => $answer->id,
                    'question' => $questionData,
                    'student_answer' => $answer->answer_text,
                    'points_earned' => $answer->points_earned,
                    'has_passed' => $answer->has_passed,
                    'is_correct' => $answer->has_passed,
                    'needs_grading' => $question->type === 'essay' && $answer->points_earned === 0,
                    'feedback' => $answer->feedback,
                    'answered_at' => $answer->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'email' => $student->email,
                        'photo' => $student->photo
                    ],
                    'exam' => [
                        'id' => $exam->id,
                        'name' => $exam->name,
                        'subject' => $exam->subject->name,
                        'started_at' => $exam->started_at,
                        'ended_at' => $exam->ended_at
                    ],
                    'attempt' => [
                        'id' => $attempt->id,
                        'is_completed' => $attempt->is_completed,
                        'answered_questions' => $attempt->answered_questions,
                        'total_questions' => $attempt->total_questions,
                        'total_attempts' => $attempt->total_attempts,
                        'has_passed' => $attempt->has_passed,
                        'violation_count' => $attempt->violation_count ?? 0,
                        'max_violations' => $attempt->max_violations ?? 3,
                        'forced_reason' => $attempt->forced_reason,
                        'violations' => $attempt->violations()->orderBy('occurred_at', 'asc')->get(['id', 'violation_type', 'details', 'occurred_at']),
                        'started_at' => $attempt->created_at,
                        'completed_at' => $attempt->completed_at
                    ],
                    'results' => [
                        'total_points_earned' => $totalPointsEarned,
                        'total_possible_points' => $totalPossiblePoints,
                        'percentage' => $percentage,
                        'grade' => $this->calculateGrade($percentage),
                        'passed' => $percentage >= 60,
                        'questions_answered' => $answers->count(),
                        'questions_correct' => $answers->where('has_passed', true)->count(),
                        'needs_grading' => $answers->where('points_earned', 0)->where('examQuestion.type', 'essay')->count()
                    ],
                    'answers' => $formattedAnswers
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student exam details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate letter grade based on percentage
     */
    private function calculateGrade(float $percentage): string
    {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }

    /**
     * Update exam for authenticated teacher's subject
     */
    public function updateTeacherExam(SubjectExamRequest $request, int $id)
    {
        try {
            $teacher = $request->user();
            $data = $request->validated();

            // Get the exam and verify ownership
            try {
                $exam = $this->subjectExamService->findExam($id, ['*']);
                if ($exam->subject->teacher_id !== $teacher->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to edit it'
                    ], 404);
                }
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exam not found or you do not have permission to edit it'
                ], 404);
            }

            // If subject_id is being changed, verify the new subject belongs to this teacher
            if (isset($data['subject_id']) && $data['subject_id'] !== $exam->subject_id) {
                try {
                    $newSubject = $this->subjectService->findSubject($data['subject_id'], ['id', 'teacher_id']);
                    if ($newSubject->teacher_id !== $teacher->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You can only assign exams to your own subjects'
                        ], 403);
                    }
                } catch (ModelNotFoundException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Subject not found'
                    ], 404);
                }
            }

            // Check if exam name already exists for the target subject (excluding current exam)
            $targetSubjectId = $data['subject_id'] ?? $exam->subject_id;
            $existingExams = $this->subjectExamService->findExamsBySubject($targetSubjectId, ['id', 'name']);
            $nameExists = $existingExams->where('id', '!=', $id)->contains('name', $data['name']);

            if ($nameExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'An exam with this name already exists for this subject. Please choose a different name.',
                    'errors' => [
                        'name' => ['This exam name is already used for this subject']
                    ]
                ], 422);
            }

            $updatedExam = $this->subjectExamService->updateExam($id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Exam updated successfully',
                'data' => new SubjectExamResource($updatedExam)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            if ($e->errorInfo[1] == 1062) { // Duplicate entry error
                return response()->json([
                    'success' => false,
                    'message' => 'An exam with this name already exists for this subject. Please choose a different name.',
                    'errors' => [
                        'name' => ['This exam name is already used for this subject']
                    ]
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating the exam. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the exam. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete exam for authenticated teacher's subject
     */
    public function deleteTeacherExam(Request $request, int $id)
    {
        try {
            $teacher = $request->user();

            // Get the exam and verify ownership
            try {
                $exam = $this->subjectExamService->findExam($id, ['*']);
                if ($exam->subject->teacher_id !== $teacher->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to delete it'
                    ], 404);
                }
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exam not found or you do not have permission to delete it'
                ], 404);
            }

            // Check if exam has been attempted by students
            $attempts = $this->examAttemptService->getAll(['exam_id' => $id]);
            if ($attempts->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete exam that has been attempted by students. Consider archiving it instead.'
                ], 422);
            }

            $this->subjectExamService->deleteExam($id);

            return response()->json([
                'success' => true,
                'message' => 'Exam deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exam: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get student answers for a specific exam (Teacher only)
     */
    public function getExamAnswers(Request $request, int $examId)
    {
        try {
            $teacher = $request->user();

            // Verify that the exam belongs to the teacher's subject
            try {
                $exam = $this->subjectExamService->findExam($examId, ['*']);
                if ($exam->subject->teacher_id !== $teacher->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Exam not found or you do not have permission to view its answers'
                    ], 404);
                }
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exam not found or you do not have permission to view its answers'
                ], 404);
            }

            // Get exam attempts with student details
            $examAttempts = $this->examAttemptService->getAll(['exam_id' => $examId])
                ->sortByDesc('completed_at');

            // Get all question answers for this exam
            $questionAnswers = $this->questionAnswerService->getAll([
                'exam_id' => $examId
            ]);

            // Group answers by student
            $answersByStudent = collect();
            if ($questionAnswers->count() > 0) {
                $answersByStudent = $questionAnswers->groupBy('student_id')->map(function($studentAnswers) {
                    $student = $studentAnswers->first()->student;
                    $totalPoints = $studentAnswers->sum('points_earned');
                    $maxPoints = $studentAnswers->sum(function($answer) {
                        return $answer->examQuestion->points ?? 0;
                    });

                    return [
                        'student' => $student,
                        'total_points_earned' => $totalPoints,
                        'total_possible_points' => $maxPoints,
                        'percentage' => $maxPoints > 0 ? round(($totalPoints / $maxPoints) * 100, 2) : 0,
                        'answers_count' => $studentAnswers->count(),
                        'answers' => \App\Http\Resources\Api\QuestionAnswerResource::collection($studentAnswers)
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => [
                        'id' => $exam->id,
                        'name' => $exam->name,
                        'subject' => $exam->subject->name,
                        'started_at' => $exam->started_at,
                        'ended_at' => $exam->ended_at
                    ],
                    'attempts_count' => $examAttempts->count(),
                    'completed_attempts' => $examAttempts->where('is_completed', true)->count(),
                    'student_answers' => $answersByStudent->values()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exam answers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
