<?php

namespace App\Services;

use App\Repositories\StudentExamRepository;
use App\Repositories\SubjectExamRepository;
use App\Repositories\ExamAttemptRepository;
use App\Repositories\QuestionAnswerRepository;
use App\Repositories\ExamQuestionRepository;
use App\Models\SubjectExam;
use App\Models\ExamViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StudentExamService
{
    private StudentExamRepository $studentExamRepository;
    private SubjectExamRepository $subjectExamRepository;
    private ExamAttemptRepository $examAttemptRepository;
    private QuestionAnswerRepository $questionAnswerRepository;
    private ExamQuestionRepository $examQuestionRepository;

    public function __construct(
        StudentExamRepository $studentExamRepository,
        SubjectExamRepository $subjectExamRepository,
        ExamAttemptRepository $examAttemptRepository,
        QuestionAnswerRepository $questionAnswerRepository,
        ExamQuestionRepository $examQuestionRepository
    ) {
        $this->studentExamRepository = $studentExamRepository;
        $this->subjectExamRepository = $subjectExamRepository;
        $this->examAttemptRepository = $examAttemptRepository;
        $this->questionAnswerRepository = $questionAnswerRepository;
        $this->examQuestionRepository = $examQuestionRepository;
    }

    /**
     * Get exam details with questions for student
     */
    public function getExamForStudent(int $studentId, int $examId): array
    {
        // Check if student has access to this exam
        if (!$this->studentExamRepository->hasExamAccess($studentId, $examId)) {
            throw new \Exception('You do not have access to this exam');
        }

        $exam = $this->studentExamRepository->getExamWithQuestions($examId);

        // Check if exam is currently active
        $now = now();
        $isActive = $now->between($exam->started_at, $exam->ended_at);

        // Get student's attempt if exists
        $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);

        return [
            'exam' => $exam,
            'questions' => $exam->examQuestions,
            'is_active' => $isActive,
            'can_take' => $isActive && (!$attempt || !$attempt->is_completed),
            'attempt' => $attempt,
            'total_questions' => $exam->examQuestions->count(),
            'total_points' => $exam->examQuestions->sum('points')
        ];
    }

    /**
     * Validate device session token for single-session anti-concurrent login
     */
    public function validateDeviceSession($attempt, ?string $deviceToken): void
    {
        if (!$attempt || empty($attempt->current_exam_device_token) || empty($deviceToken)) {
            return;
        }

        if ($attempt->current_exam_device_token !== $deviceToken) {
            $e = new \Exception('Sesi ujian telah diambil alih dari perangkat atau jendela lain.');
            $e->errorCode = 'CONCURRENT_DEVICE_DETECTED';
            throw $e;
        }
    }

    /**
     * Start an exam attempt
     */
    public function startExam(int $studentId, int $examId, ?string $providedToken = null): array
    {
        return DB::transaction(function () use ($studentId, $examId, $providedToken) {
            // Check if student has access to this exam
            if (!$this->studentExamRepository->hasExamAccess($studentId, $examId)) {
                throw new \Exception('You do not have access to this exam');
            }

            $exam = $this->studentExamRepository->getExamWithQuestions($examId);

            // Check if exam is currently active
            $now = now();
            if (!$now->between($exam->started_at, $exam->ended_at)) {
                $debugInfo = config('app.debug') ?
                    " (Current time: {$now->toDateTimeString()}, Exam start: {$exam->started_at->toDateTimeString()}, Exam end: {$exam->ended_at->toDateTimeString()})" : '';
                throw new \Exception('This exam is not currently active' . $debugInfo);
            }

            // Check Mandatory Access Token requirement (Spesifikasi 2 & 3)
            if (!empty($exam->token)) {
                if (empty($providedToken)) {
                    throw new \Exception('Token ujian wajib dimasukkan untuk memulai ujian ini.');
                }

                if (strtoupper(trim($providedToken)) !== strtoupper(trim($exam->token))) {
                    throw new \Exception('Token ujian tidak valid. Pastikan kode token yang Anda masukkan benar.');
                }

                // Check Token Expiration (Spesifikasi 3)
                if ($exam->token_expires_at && $now->gt($exam->token_expires_at)) {
                    throw new \Exception('Token ujian telah kedaluwarsa. Batas waktu penggunaan token telah berakhir.');
                }
            }

            // Check if student already has a completed attempt
            $existingAttempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);

            if ($existingAttempt && $existingAttempt->is_completed) {
                throw new \Exception('You have already completed this exam');
            }

            // Generate single session device token UUID
            $deviceToken = (string) Str::uuid();

            // Create or update exam attempt
            if ($existingAttempt) {
                // Student is restarting the exam
                $existingAttempt->update([
                    'is_completed' => false,
                    'current_exam_device_token' => $deviceToken,
                    'last_activity_at' => now(),
                    'total_questions' => $exam->examQuestions->count(),
                    'answered_questions' => 0,
                    'total_points' => $exam->total_points,
                    'points_earned' => 0,
                    'has_passed' => false,
                    'completed_at' => null,
                    'forced_reason' => null,
                ]);
                $attempt = $existingAttempt->fresh();
            } else {
                // First time starting this exam
                $attempt = $this->examAttemptRepository->create([
                    'student_id' => $studentId,
                    'subject_exam_id' => $examId,
                    'total_attempts' => 1,
                    'is_completed' => false,
                    'current_exam_device_token' => $deviceToken,
                    'last_activity_at' => now(),
                    'violation_count' => 0,
                    'max_violations' => 3,
                    'total_questions' => $exam->examQuestions->count(),
                    'answered_questions' => 0,
                    'total_points' => $exam->total_points,
                    'points_earned' => 0,
                    'has_passed' => false,
                    'completed_at' => null,
                ]);
            }

            $durationMinutes = $exam->duration_minutes ?: $this->getTimeRemaining($exam);

            return [
                'attempt' => $attempt,
                'device_token' => $deviceToken,
                'exam' => $exam,
                'questions' => $exam->examQuestions,
                'time_remaining_minutes' => $durationMinutes
            ];
        });
    }

    /**
     * Submit answer to a question
     */
    public function submitAnswer(int $studentId, int $examId, int $questionId, array $data, ?string $deviceToken = null): array
    {
        return DB::transaction(function () use ($studentId, $examId, $questionId, $data, $deviceToken) {
            // Check if student has access to this exam
            if (!$this->studentExamRepository->hasExamAccess($studentId, $examId)) {
                throw new \Exception('You do not have access to this exam');
            }

            $exam = $this->subjectExamRepository->findWithRelations($examId);
            $question = $this->examQuestionRepository->findWithRelations($questionId);

            // Verify question belongs to this exam
            if ($question->subject_exam_id !== $examId) {
                throw new \Exception('Question does not belong to this exam');
            }

            // Check if exam is currently active
            $now = now();
            if (!$now->between($exam->started_at, $exam->ended_at)) {
                throw new \Exception('This exam is not currently active');
            }

            // Check if student has an active attempt
            $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);

            if (!$attempt) {
                throw new \Exception('No active exam attempt found. Please start the exam first.');
            }

            if ($deviceToken) {
                $this->validateDeviceSession($attempt, $deviceToken);
            }

            if ($attempt->is_completed) {
                throw new \Exception('This exam attempt has already been completed');
            }

            $attempt->update(['last_activity_at' => now()]);

            // Process the answer
            $answerText = $data['answer_text'];
            $pointsEarned = 0;
            $hasPassed = false;

            if ($question->type === 'multiple_choice') {
                // Auto-grade multiple choice
                $correctOption = $question->questionOptions->where('is_correct', true)->first();
                if ($correctOption && $answerText !== null && strcasecmp(trim((string)$answerText), trim((string)$correctOption->name)) === 0) {
                    $pointsEarned = $question->points;
                    $hasPassed = true;
                }
            } else {
                // Essay questions: has_passed = true if answer submitted, false if empty
                $hasPassed = !empty(trim($answerText ?? ''));
            }

            // Save or update the answer
            $existingAnswer = $this->questionAnswerRepository->findByQuestionAndStudent($questionId, $studentId);

            if ($existingAnswer) {
                $answer = $this->questionAnswerRepository->update($existingAnswer->id, [
                    'answer_text' => $answerText,
                    'has_passed' => $hasPassed,
                    'points_earned' => $pointsEarned
                ]);
            } else {
                $answer = $this->questionAnswerRepository->create([
                    'exam_question_id' => $questionId,
                    'student_id' => $studentId,
                    'answer_text' => $answerText,
                    'has_passed' => $hasPassed,
                    'points_earned' => $pointsEarned
                ]);
            }

            // Update attempt totals (questions count and points earned)
            $this->updateAttemptTotals($studentId, $examId);

            // Get updated answer count for response
            $answeredCount = $this->studentExamRepository->getAnsweredCount($studentId, $examId);

            return [
                'answer' => $answer,
                'question_type' => $question->type,
                'auto_graded' => $question->type === 'multiple_choice',
                'points_earned' => $pointsEarned,
                'total_answered' => $answeredCount,
                'total_questions' => $attempt->total_questions
            ];
        });
    }

    /**
     * Complete and submit exam
     */
    public function completeExam(int $studentId, int $examId, ?string $forcedReason = null, ?string $deviceToken = null): array
    {
        return DB::transaction(function () use ($studentId, $examId, $forcedReason, $deviceToken) {
            // Check if student has access to this exam
            if (!$this->studentExamRepository->hasExamAccess($studentId, $examId)) {
                throw new \Exception('You do not have access to this exam');
            }

            $exam = $this->studentExamRepository->getExamWithQuestions($examId);

            // Get student's attempt
            $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);

            if (!$attempt) {
                throw new \Exception('No exam attempt found');
            }

            if ($deviceToken) {
                $this->validateDeviceSession($attempt, $deviceToken);
            }

            if ($attempt->is_completed) {
                throw new \Exception('Exam has already been completed');
            }

            // Create placeholder records for unanswered questions
            $this->createPlaceholderAnswers($studentId, $exam);

            // Mark attempt as completed
            $attempt->update([
                'is_completed' => true,
                'completed_at' => now(),
                'forced_reason' => $forcedReason,
                'last_activity_at' => now(),
            ]);

            // Update score totals
            $this->updateAttemptTotals($studentId, $examId);
            $attempt->refresh();

            // Calculate current score from all answers (including placeholders)
            $answers = $this->studentExamRepository->getStudentAnswers($studentId, $examId);

            $totalPointsEarned = $answers->sum('points_earned');
            $totalPossiblePoints = $exam->examQuestions->sum('points');
            $percentage = $totalPossiblePoints > 0 ? round(($totalPointsEarned / $totalPossiblePoints) * 100, 2) : 0;

            return [
                'attempt' => $attempt,
                'results' => [
                    'total_points_earned' => $totalPointsEarned,
                    'total_possible_points' => $totalPossiblePoints,
                    'percentage' => $percentage,
                    'answered_questions' => $answers->count(),
                    'total_questions' => $exam->examQuestions->count(),
                    'completed_at' => $attempt->completed_at,
                    'forced_reason' => $attempt->forced_reason,
                    'needs_manual_grading' => $answers->where('points_earned', 0)->count() > 0
                ]
            ];
        });
    }

    /**
     * Log a proctoring violation from the client
     */
    public function logViolation(int $studentId, int $examId, string $violationType, $details = null, ?string $deviceToken = null): array
    {
        return DB::transaction(function () use ($studentId, $examId, $violationType, $details, $deviceToken) {
            $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);
            if (!$attempt) {
                throw new \Exception('No active exam attempt found');
            }

            if ($deviceToken) {
                $this->validateDeviceSession($attempt, $deviceToken);
            }

            if ($attempt->is_completed) {
                return [
                    'violation_count' => $attempt->violation_count ?? 0,
                    'max_violations' => $attempt->max_violations ?? 3,
                    'is_locked' => true,
                    'forced_submit' => false,
                    'message' => 'Ujian sudah diselesaikan sebelumnya.'
                ];
            }

            // Create violation log record
            ExamViolation::create([
                'exam_attempt_id' => $attempt->id,
                'violation_type' => $violationType,
                'details' => is_array($details) ? json_encode($details) : (string) $details,
                'occurred_at' => now(),
            ]);

            $newCount = ($attempt->violation_count ?? 0) + 1;
            $maxViolations = $attempt->max_violations ?? 3;
            $isLocked = $newCount >= $maxViolations;

            if ($isLocked) {
                $exam = $this->studentExamRepository->getExamWithQuestions($examId);
                $this->createPlaceholderAnswers($studentId, $exam);

                $attempt->update([
                    'violation_count' => $newCount,
                    'is_completed' => true,
                    'completed_at' => now(),
                    'forced_reason' => 'violation_limit_reached',
                    'last_activity_at' => now(),
                ]);

                $this->updateAttemptTotals($studentId, $examId);
                $attempt->refresh();

                return [
                    'violation_count' => $newCount,
                    'max_violations' => $maxViolations,
                    'is_locked' => true,
                    'forced_submit' => true,
                    'message' => 'Batas maksimal pelanggaran telah tercapai. Ujian Anda telah dikunci dan dikumpulkan otomatis.'
                ];
            }

            $attempt->update([
                'violation_count' => $newCount,
                'last_activity_at' => now(),
            ]);

            return [
                'violation_count' => $newCount,
                'max_violations' => $maxViolations,
                'is_locked' => false,
                'forced_submit' => false,
                'message' => "Peringatan! Pelanggaran ke-{$newCount} dari {$maxViolations} tercatat."
            ];
        });
    }

    /**
     * Heartbeat check for active exam attempt & device session
     */
    public function heartbeat(int $studentId, int $examId, ?string $deviceToken = null): array
    {
        $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);
        if (!$attempt) {
            throw new \Exception('No active exam attempt found');
        }

        if ($deviceToken) {
            $this->validateDeviceSession($attempt, $deviceToken);
        }

        $attempt->update(['last_activity_at' => now()]);

        $exam = $this->subjectExamRepository->findWithRelations($examId);

        return [
            'status' => 'active',
            'is_completed' => (bool)$attempt->is_completed,
            'violation_count' => $attempt->violation_count ?? 0,
            'max_violations' => $attempt->max_violations ?? 3,
            'forced_reason' => $attempt->forced_reason,
            'time_remaining_minutes' => $exam ? $this->getTimeRemaining($exam) : 0,
        ];
    }

    /**
     * Get current exam progress
     */
    public function getProgress(int $studentId, int $examId): array
    {
        // Check if student has access to this exam
        if (!$this->studentExamRepository->hasExamAccess($studentId, $examId)) {
            throw new \Exception('You do not have access to this exam');
        }

        $exam = $this->studentExamRepository->getExamWithQuestions($examId);

        // Get student's attempt
        $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);

        if (!$attempt) {
            throw new \Exception('No exam attempt found');
        }

        // Get submitted answers
        $answers = $this->studentExamRepository->getStudentAnswers($studentId, $examId);

        return [
            'attempt' => $attempt,
            'progress' => [
                'answered_questions' => $answers->count(),
                'total_questions' => $exam->examQuestions->count(),
                'percentage_complete' => $exam->examQuestions->count() > 0 ?
                    round(($answers->count() / $exam->examQuestions->count()) * 100, 2) : 0,
                'time_remaining_minutes' => $this->getTimeRemaining($exam),
                'is_completed' => $attempt->is_completed
            ],
            'answers' => $answers
        ];
    }

    /**
     * Get exam results (completed exams only)
     */
    public function getResults(int $studentId, int $examId): array
    {
        // Check if student has access to this exam
        if (!$this->studentExamRepository->hasExamAccess($studentId, $examId)) {
            throw new \Exception('You do not have access to this exam');
        }

        $exam = $this->studentExamRepository->getExamWithQuestions($examId);

        // Get student's completed attempt
        $attempt = $this->studentExamRepository->getCompletedAttempt($studentId, $examId);

        if (!$attempt) {
            throw new \Exception('No completed exam found');
        }

        $attempt->load(['violations' => function ($q) {
            $q->orderBy('occurred_at', 'asc');
        }, 'student']);

        // Get all answers with questions
        $answers = $this->studentExamRepository->getStudentAnswersWithOptions($studentId, $examId);

        $totalPointsEarned = $answers->sum('points_earned');
        $totalPossiblePoints = $exam->examQuestions->sum('points');
        $percentage = $totalPossiblePoints > 0 ? round(($totalPointsEarned / $totalPossiblePoints) * 100, 2) : 0;
        $passingGrade = $exam->passing_grade ?? $exam->subject?->passing_grade ?? 75;

        return [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'subject' => $exam->subject->name,
                'passing_grade' => $passingGrade,
                'started_at' => $exam->started_at,
                'ended_at' => $exam->ended_at
            ],
            'attempt' => $attempt,
            'results' => [
                'total_points_earned' => $totalPointsEarned,
                'total_possible_points' => $totalPossiblePoints,
                'percentage' => $percentage,
                'grade' => $this->getGrade($percentage),
                'passed' => $percentage >= $passingGrade,
                'completed_at' => $attempt->completed_at
            ],
            'answers' => $answers
        ];
    }

    /**
     * Calculate time remaining for exam in minutes
     */
    private function getTimeRemaining(SubjectExam $exam): int
    {
        $now = now();
        $endTime = Carbon::parse($exam->ended_at);

        if ($now->gt($endTime)) {
            return 0;
        }

        return $now->diffInMinutes($endTime);
    }

    /**
     * Get letter grade based on percentage
     */
    private function getGrade(float $percentage): string
    {
        if ($percentage >= 90)
            return 'A';
        if ($percentage >= 80)
            return 'B';
        if ($percentage >= 70)
            return 'C';
        if ($percentage >= 60)
            return 'D';
        return 'F';
    }

    /**
     * Create placeholder records for unanswered questions
     */
    private function createPlaceholderAnswers(int $studentId, SubjectExam $exam): void
    {
        // Get all exam questions
        $allQuestions = $exam->examQuestions;

        // Get questions that already have answers
        $existingAnswers = $this->questionAnswerRepository->getAll([
            'student_id' => $studentId,
            'exam_id' => $exam->id
        ]);
        $answeredQuestionIds = $existingAnswers->pluck('exam_question_id')->toArray();

        // Find unanswered questions
        $unansweredQuestions = $allQuestions->whereNotIn('id', $answeredQuestionIds);

        // Create placeholder records for unanswered questions
        foreach ($unansweredQuestions as $question) {
            $this->questionAnswerRepository->create([
                'exam_question_id' => $question->id,
                'student_id' => $studentId,
                'answer_text' => null, // NULL indicates no answer provided
                'has_passed' => false,
                'points_earned' => 0,
                'feedback' => 'Question not answered within time limit'
            ]);
        }
    }

    /**
     * Update exam attempt totals including points and percentages
     */
    private function updateAttemptTotals(int $studentId, int $examId): void
    {
        $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);

        if ($attempt) {
            // Get all student answers for this exam
            $answers = $this->studentExamRepository->getStudentAnswers($studentId, $examId);

            // Calculate totals
            $answeredCount = $answers->count();
            $totalPointsEarned = $answers->sum('points_earned');

            // Get total possible points from exam questions
            $exam = $this->studentExamRepository->getExamWithQuestions($examId);
            $totalPossiblePoints = $exam->examQuestions->sum('points');

            // Calculate percentage and pass status based on exam's or subject's passing_grade
            $scorePercentage = $totalPossiblePoints > 0 ?
                round(($totalPointsEarned / $totalPossiblePoints) * 100, 2) : 0;
            $passingGrade = $exam->passing_grade ?? $exam->subject?->passing_grade ?? 75;
            $hasPassed = $scorePercentage >= $passingGrade;

            $attempt->update([
                'answered_questions' => $answeredCount,
                'points_earned' => $totalPointsEarned,
                'score_percentage' => $scorePercentage,
                'has_passed' => $hasPassed
            ]);
        }
    }
}
