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

        // Deterministically shuffle multiple choice options for this student
        $this->shuffleOptionsForStudent($exam, $studentId);

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
                $savedAnswersCount = \App\Models\QuestionAnswer::where('exam_attempt_id', $existingAttempt->id)->count();
                $savedPointsEarned = \App\Models\QuestionAnswer::where('exam_attempt_id', $existingAttempt->id)->sum('points_earned');

                // Student is restarting or resuming the exam
                $existingAttempt->update([
                    'is_completed' => false,
                    'current_exam_device_token' => $deviceToken,
                    'last_activity_at' => now(),
                    'last_heartbeat_at' => now(),
                    'total_questions' => $exam->examQuestions->count(),
                    'answered_questions' => $savedAnswersCount,
                    'total_points' => $exam->total_points,
                    'points_earned' => $savedPointsEarned,
                    'has_passed' => false,
                    'completed_at' => null,
                    'forced_reason' => null,
                    'violation_count' => 0,
                    'violation_score' => 0.0,
                    'max_violation_score' => 3.0,
                    'is_frozen' => false,
                    'frozen_at' => null,
                    'freeze_count' => 0,
                    'offline_gaps_count' => 0,
                    'total_offline_seconds' => 0,
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
                    'last_heartbeat_at' => now(),
                    'violation_count' => 0,
                    'max_violations' => 3,
                    'violation_score' => 0.0,
                    'max_violation_score' => 3.0,
                    'is_frozen' => false,
                    'frozen_at' => null,
                    'freeze_count' => 0,
                    'offline_gaps_count' => 0,
                    'total_offline_seconds' => 0,
                    'total_questions' => $exam->examQuestions->count(),
                    'answered_questions' => 0,
                    'total_points' => $exam->total_points,
                    'points_earned' => 0,
                    'has_passed' => false,
                    'completed_at' => null,
                ]);
            }

            // Deterministically shuffle multiple choice options for this student
            $this->shuffleOptionsForStudent($exam, $studentId);

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
     * Log a proctoring violation from the client with weighted scoring and freeze support
     */
    public function logViolation(
        int $studentId, 
        int $examId, 
        string $violationType, 
        $details = null, 
        ?string $deviceToken = null, 
        ?float $weight = null, 
        int $durationSeconds = 0, 
        bool $triggerFreeze = false
    ): array {
        return DB::transaction(function () use ($studentId, $examId, $violationType, $details, $deviceToken, $weight, $durationSeconds, $triggerFreeze) {
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
                    'violation_score' => (float)($attempt->violation_score ?? 0.0),
                    'max_violation_score' => (float)($attempt->max_violation_score ?? 3.0),
                    'is_frozen' => false,
                    'is_locked' => true,
                    'forced_submit' => false,
                    'message' => 'Ujian sudah diselesaikan sebelumnya.'
                ];
            }

            // Determine violation weight:
            // Light (0.5 pt): brief blur, brief tab switch (< 30s), notification interruption
            // Full (1.0 pt): fullscreen exit, prolonged away (> 30s)
            if ($weight === null) {
                if ($violationType === 'fullscreen_exit' || $durationSeconds >= 30) {
                    $weight = 1.0;
                } else {
                    $weight = 0.5;
                }
            }

            // Create violation log record
            ExamViolation::create([
                'exam_attempt_id' => $attempt->id,
                'violation_type' => $violationType,
                'weight' => $weight,
                'duration_seconds' => $durationSeconds,
                'is_offline_gap' => ($violationType === 'offline_gap'),
                'details' => is_array($details) ? json_encode($details) : (string) $details,
                'occurred_at' => now(),
            ]);

            $newCount = ($attempt->violation_count ?? 0) + 1;
            $newScore = round((float)($attempt->violation_score ?? 0.0) + $weight, 1);
            $maxScore = (float)($attempt->max_violation_score ?? 3.0);
            $maxViolations = $attempt->max_violations ?? 3;

            $isLocked = ($newScore >= $maxScore);

            $updateData = [
                'violation_count' => $newCount,
                'violation_score' => $newScore,
                'last_activity_at' => now(),
            ];

            if ($triggerFreeze && !$isLocked) {
                $updateData['is_frozen'] = true;
                $updateData['frozen_at'] = now();
                $updateData['freeze_count'] = ($attempt->freeze_count ?? 0) + 1;
            }

            if ($isLocked) {
                $exam = $this->studentExamRepository->getExamWithQuestions($examId);
                $this->createPlaceholderAnswers($studentId, $exam);

                $updateData['is_completed'] = true;
                $updateData['is_frozen'] = false;
                $updateData['completed_at'] = now();
                $updateData['forced_reason'] = 'violation_limit_reached';

                $attempt->update($updateData);

                $this->updateAttemptTotals($studentId, $examId);
                $attempt->refresh();

                return [
                    'violation_count' => $newCount,
                    'max_violations' => $maxViolations,
                    'violation_score' => $newScore,
                    'max_violation_score' => $maxScore,
                    'is_frozen' => false,
                    'is_locked' => true,
                    'forced_submit' => true,
                    'message' => 'Batas maksimal pelanggaran telah tercapai. Ujian Anda telah dikunci dan dikumpulkan otomatis.'
                ];
            }

            $attempt->update($updateData);

            return [
                'violation_count' => $newCount,
                'max_violations' => $maxViolations,
                'violation_score' => $newScore,
                'max_violation_score' => $maxScore,
                'is_frozen' => (bool)($updateData['is_frozen'] ?? $attempt->is_frozen),
                'is_locked' => false,
                'forced_submit' => false,
                'message' => "Insiden tercatat (+{$weight} poin). Total skor pelanggaran: {$newScore} dari {$maxScore}."
            ];
        });
    }

    /**
     * Heartbeat check with offline gap detection
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

        $now = now();
        $lastHeartbeat = $attempt->last_heartbeat_at ?: $attempt->last_activity_at;

        // Detect offline gap (if gap > 35 seconds, missed multiple heartbeats)
        if ($lastHeartbeat && $now->diffInSeconds($lastHeartbeat) > 35 && !$attempt->is_completed) {
            $gapSeconds = $now->diffInSeconds($lastHeartbeat);
            $gapWeight = $gapSeconds >= 60 ? 1.0 : 0.5;

            ExamViolation::create([
                'exam_attempt_id' => $attempt->id,
                'violation_type' => 'offline_gap',
                'weight' => $gapWeight,
                'duration_seconds' => $gapSeconds,
                'is_offline_gap' => true,
                'details' => "Terdeteksi jeda koneksi/offline selama {$gapSeconds} detik (potensi Airplane Mode/Network Interruption).",
                'occurred_at' => $now,
            ]);

            $attempt->increment('offline_gaps_count');
            $attempt->increment('total_offline_seconds', $gapSeconds);

            $newScore = round((float)($attempt->violation_score ?? 0.0) + $gapWeight, 1);
            $maxScore = (float)($attempt->max_violation_score ?? 3.0);

            if ($newScore >= $maxScore) {
                $exam = $this->studentExamRepository->getExamWithQuestions($examId);
                $this->createPlaceholderAnswers($studentId, $exam);

                $attempt->update([
                    'violation_score' => $newScore,
                    'is_completed' => true,
                    'is_frozen' => false,
                    'completed_at' => $now,
                    'forced_reason' => 'violation_limit_reached',
                    'last_activity_at' => $now,
                    'last_heartbeat_at' => $now,
                ]);

                $this->updateAttemptTotals($studentId, $examId);
            } else {
                $attempt->update([
                    'violation_score' => $newScore,
                    'last_activity_at' => $now,
                    'last_heartbeat_at' => $now,
                ]);
            }
        } else {
            $attempt->update([
                'last_activity_at' => $now,
                'last_heartbeat_at' => $now,
            ]);
        }

        $exam = $this->subjectExamRepository->findWithRelations($examId);

        return [
            'status' => 'active',
            'is_completed' => (bool)$attempt->is_completed,
            'is_frozen' => (bool)$attempt->is_frozen,
            'violation_count' => $attempt->violation_count ?? 0,
            'violation_score' => (float)($attempt->violation_score ?? 0.0),
            'max_violation_score' => (float)($attempt->max_violation_score ?? 3.0),
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
                'subject' => $exam->subject ? [
                    'id' => $exam->subject->id,
                    'name' => $exam->subject->name,
                ] : [
                    'id' => $exam->subject_id ?? 0,
                    'name' => 'General Subject',
                ],
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

    /**
     * Deterministically shuffle multiple choice options for a student
     */
    private function shuffleOptionsForStudent(SubjectExam $exam, int $studentId): void
    {
        if (!$exam->relationLoaded('examQuestions')) {
            return;
        }

        foreach ($exam->examQuestions as $question) {
            if ($question->type === 'multiple_choice' && $question->relationLoaded('questionOptions')) {
                $options = $question->questionOptions->values();
                if ($options->count() > 1) {
                    // Seed PRNG deterministically based on student, question, and exam
                    $seed = crc32("opt_{$studentId}_{$question->id}_{$exam->id}");
                    mt_srand($seed);
                    $indices = range(0, $options->count() - 1);
                    for ($i = count($indices) - 1; $i > 0; $i--) {
                        $j = mt_rand(0, $i);
                        $temp = $indices[$i];
                        $indices[$i] = $indices[$j];
                        $indices[$j] = $temp;
                    }
                    mt_srand(); // reset seed back to random
                    $shuffled = collect($indices)->map(fn($idx) => $options[$idx]);
                    $question->setRelation('questionOptions', $shuffled);
                }
            }
        }
    }

    /**
     * Generate dynamic 6-digit Supervisor PIN changing every 60 seconds
     */
    public function getSupervisorPin(int $examId): array
    {
        $window = (int)floor(time() / 60);
        $secret = config('app.key') . '_supervisor_pin_' . $examId;
        $hash = hash_hmac('sha256', (string)$window, $secret);
        $pin = str_pad((string)(hexdec(substr($hash, 0, 8)) % 900000 + 100000), 6, '0', STR_PAD_LEFT);
        $secondsRemaining = 60 - (time() % 60);

        return [
            'pin' => $pin,
            'seconds_remaining' => $secondsRemaining,
        ];
    }

    /**
     * Compute client fallback PIN using JavaScript string hash logic
     */
    private function computeClientFallbackPin(int $examId, int $window): string
    {
        $str = "{$window}_{$examId}_supervisor_pin";
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = (($hash << 5) - $hash) + ord($str[$i]);
            $hash = $hash & 0xFFFFFFFF;
            if ($hash & 0x80000000) {
                $hash = -((~$hash & 0xFFFFFFFF) + 1);
            }
        }
        $pinNum = (abs($hash) % 900000) + 100000;
        return str_pad((string)$pinNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify Supervisor PIN to unlock a frozen student session
     */
    public function verifySupervisorPin(int $studentId, int $examId, string $pin): array
    {
        $pin = trim($pin);
        if (strlen($pin) < 4) {
            throw new \Exception('PIN verifikasi harus berupa digit angka atau token yang valid.');
        }

        $validPins = [];
        $currentWindow = (int)floor(time() / 60);
        $secret = config('app.key') . '_supervisor_pin_' . $examId;

        // Check a generous range of windows (-5 to +2 windows = ~7 minutes grace period)
        // This ensures that classroom walking delay, network latency, and clock drifts never cause false invalidation
        for ($w = $currentWindow - 5; $w <= $currentWindow + 2; $w++) {
            // 1. Primary backend HMAC SHA256 PIN
            $hash = hash_hmac('sha256', (string)$w, $secret);
            $validPins[] = str_pad((string)(hexdec(substr($hash, 0, 8)) % 900000 + 100000), 6, '0', STR_PAD_LEFT);

            // 2. Client-side fallback hash PIN (guarantees match if supervisor frontend fell back to client hash)
            $validPins[] = $this->computeClientFallbackPin($examId, $w);
        }

        // 3. Also allow exam access token if present
        $exam = SubjectExam::find($examId);
        if ($exam && !empty($exam->token)) {
            $validPins[] = trim($exam->token);
            $validPins[] = strtoupper(trim($exam->token));
        }

        if (!in_array($pin, $validPins, true) && !in_array(strtoupper($pin), $validPins, true)) {
            throw new \Exception('PIN verifikasi salah atau telah kedaluwarsa. Silakan periksa kembali PIN terbaru dari layar pengawas.');
        }

        $attempt = $this->studentExamRepository->getStudentAttempt($studentId, $examId);
        if (!$attempt) {
            throw new \Exception('Sesi ujian siswa tidak ditemukan.');
        }

        $updateData = [
            'is_frozen' => false,
            'frozen_at' => null,
            'last_activity_at' => now(),
        ];

        // Give safety leeway if violation score was maxed out so student is not instantly refrozen
        if (($attempt->violation_score ?? 0) >= ($attempt->max_violation_score ?? 3.0)) {
            $updateData['violation_score'] = max(0.0, ($attempt->max_violation_score ?? 3.0) - 1.0);
        }

        $attempt->update($updateData);

        ExamViolation::create([
            'exam_attempt_id' => $attempt->id,
            'violation_type' => 'supervisor_unlocked',
            'weight' => 0.0,
            'duration_seconds' => 0,
            'is_offline_gap' => false,
            'details' => 'Sesi ujian berhasil dibuka kembali oleh pengawas menggunakan PIN verifikasi.',
            'occurred_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'PIN terverifikasi! Sesi ujian berhasil dibuka kembali.',
            'attempt' => $attempt->fresh(),
        ];
    }
}
