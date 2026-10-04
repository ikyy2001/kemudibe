<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\SubjectExam;
use App\Models\User;
use App\Services\StudentExamService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExamProctorController extends Controller
{
    private StudentExamService $studentExamService;

    public function __construct(StudentExamService $studentExamService)
    {
        $this->studentExamService = $studentExamService;
    }

    /**
     * Get live proctoring data for a specific exam
     */
    public function getLiveStatus(Request $request, int $id)
    {
        try {
            $exam = SubjectExam::with(['subject', 'examQuestions'])->findOrFail($id);

            // 1. Fetch all attempts for this exam with student & violations
            $attempts = ExamAttempt::with([
                'student.classStudents.classRoom',
                'violations' => function ($q) {
                    $q->orderBy('occurred_at', 'desc');
                }
            ])
            ->where('subject_exam_id', $id)
            ->get()
            ->keyBy('student_id');

            // 2. Find all students enrolled in the classrooms linked to this subject
            $enrolledStudents = User::role('student')
                ->whereHas('classStudents.classRoom.classSubjects', function ($query) use ($exam) {
                    $query->where('subject_id', $exam->subject_id);
                })
                ->with(['classStudents.classRoom'])
                ->get()
                ->keyBy('id');

            // 3. Merge: ALL students with attempts + ALL enrolled students
            $attemptStudents = $attempts->map(fn($att) => $att->student)->filter()->keyBy('id');
            $allTargetStudents = $enrolledStudents->union($attemptStudents)->values();

            $now = Carbon::now();
            $totalQuestions = $exam->examQuestions->count();

            $supervisorPinData = $this->studentExamService->getSupervisorPin($exam->id);

            $studentList = [];
            $stats = [
                'total_students' => $allTargetStudents->count(),
                'active' => 0,
                'idle' => 0,
                'frozen' => 0,
                'completed' => 0,
                'not_started' => 0,
                'with_violations' => 0,
            ];

            foreach ($allTargetStudents as $student) {
                $attempt = $attempts->get($student->id);
                $classroomName = $student->classStudents->first()?->classRoom?->name ?? 'Unassigned';

                if (!$attempt) {
                    $status = 'not_started';
                    $stats['not_started']++;
                    $answeredCount = 0;
                    $violationCount = 0;
                    $violationScore = 0.0;
                    $maxViolationScore = 3.0;
                    $isFrozen = false;
                    $frozenAt = null;
                    $freezeCount = 0;
                    $offlineGapsCount = 0;
                    $totalOfflineSeconds = 0;
                    $violations = [];
                    $lastActive = null;
                    $deviceToken = null;
                } elseif ($attempt->is_completed) {
                    $status = 'completed';
                    $stats['completed']++;
                    $answeredCount = $attempt->answered_questions ?? 0;
                    $violationCount = $attempt->violation_count ?? 0;
                    $violationScore = (float)($attempt->violation_score ?? 0.0);
                    $maxViolationScore = (float)($attempt->max_violation_score ?? 3.0);
                    $isFrozen = false;
                    $frozenAt = null;
                    $freezeCount = $attempt->freeze_count ?? 0;
                    $offlineGapsCount = $attempt->offline_gaps_count ?? 0;
                    $totalOfflineSeconds = $attempt->total_offline_seconds ?? 0;
                    $violations = $attempt->violations;
                    $lastActive = $attempt->completed_at ? $attempt->completed_at->format('Y-m-d H:i:s') : null;
                    $deviceToken = $attempt->current_exam_device_token;
                } else {
                    $answeredCount = $attempt->answered_questions ?? 0;
                    $violationCount = $attempt->violation_count ?? 0;
                    $violationScore = (float)($attempt->violation_score ?? 0.0);
                    $maxViolationScore = (float)($attempt->max_violation_score ?? 3.0);
                    $isFrozen = (bool)$attempt->is_frozen;
                    $frozenAt = $attempt->frozen_at ? $attempt->frozen_at->format('H:i:s') : null;
                    $freezeCount = $attempt->freeze_count ?? 0;
                    $offlineGapsCount = $attempt->offline_gaps_count ?? 0;
                    $totalOfflineSeconds = $attempt->total_offline_seconds ?? 0;
                    $violations = $attempt->violations;
                    $deviceToken = $attempt->current_exam_device_token;

                    $lastActivity = $attempt->last_activity_at;
                    $lastActive = $lastActivity ? $lastActivity->format('Y-m-d H:i:s') : null;

                    if ($isFrozen) {
                        $status = 'frozen';
                        $stats['frozen']++;
                    } elseif ($lastActivity && $now->diffInSeconds($lastActivity) <= 120) {
                        $status = 'active';
                        $stats['active']++;
                    } else {
                        $status = 'idle';
                        $stats['idle']++;
                    }
                }

                if ($violationCount > 0 || $violationScore > 0) {
                    $stats['with_violations']++;
                }

                $studentList[] = [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'photo' => $student->photo,
                    'classroom' => $classroomName,
                    'status' => $status,
                    'is_completed' => $attempt ? (bool) $attempt->is_completed : false,
                    'is_frozen' => $isFrozen,
                    'frozen_at' => $frozenAt,
                    'freeze_count' => $freezeCount,
                    'offline_gaps_count' => $offlineGapsCount,
                    'total_offline_seconds' => $totalOfflineSeconds,
                    'answered_questions' => $answeredCount,
                    'total_questions' => $totalQuestions,
                    'progress_percentage' => $totalQuestions > 0 ? round(($answeredCount / $totalQuestions) * 100) : 0,
                    'violation_count' => $violationCount,
                    'max_violations' => $attempt?->max_violations ?? 3,
                    'violation_score' => $violationScore,
                    'max_violation_score' => $maxViolationScore,
                    'forced_reason' => $attempt?->forced_reason,
                    'last_activity_at' => $lastActive,
                    'has_device_token' => !empty($deviceToken),
                    'violations' => $violations->map(function ($v) {
                        return [
                            'id' => $v->id,
                            'type' => $v->violation_type,
                            'weight' => (float)($v->weight ?? 1.0),
                            'duration_seconds' => (int)($v->duration_seconds ?? 0),
                            'is_offline_gap' => (bool)($v->is_offline_gap ?? false),
                            'details' => $v->details,
                            'occurred_at' => $v->occurred_at ? Carbon::parse($v->occurred_at)->format('H:i:s') : null,
                        ];
                    }),
                    'points_earned' => $attempt?->points_earned,
                ];
            }

            // Sort: students with violations first, then active/frozen, then idle, then completed, then not_started
            usort($studentList, function ($a, $b) {
                if ($b['violation_score'] !== $a['violation_score']) {
                    return $b['violation_score'] <=> $a['violation_score'];
                }
                if ($b['violation_count'] !== $a['violation_count']) {
                    return $b['violation_count'] <=> $a['violation_count'];
                }
                $order = ['frozen' => 1, 'active' => 2, 'idle' => 3, 'completed' => 4, 'not_started' => 5];
                return ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => [
                        'id' => $exam->id,
                        'name' => $exam->name,
                        'subject_name' => $exam->subject?->name,
                        'duration_minutes' => $exam->duration_minutes,
                        'total_questions' => $totalQuestions,
                        'started_at' => $exam->started_at ? Carbon::parse($exam->started_at)->format('Y-m-d H:i') : null,
                        'ended_at' => $exam->ended_at ? Carbon::parse($exam->ended_at)->format('Y-m-d H:i') : null,
                        'token' => $exam->token,
                        'supervisor_pin' => $supervisorPinData['pin'],
                        'supervisor_pin_expires_in' => $supervisorPinData['seconds_remaining'],
                    ],
                    'stats' => $stats,
                    'students' => $studentList,
                    'server_time' => now()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load live monitor: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Force submit exam for a student
     */
    public function forceSubmit(Request $request, int $id, int $studentId)
    {
        try {
            $reason = $request->input('reason', 'Dikumpulkan secara paksa oleh Pengawas Ujian.');
            
            $result = $this->studentExamService->completeExam($studentId, $id, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Ujian siswa berhasil dikumpulkan secara paksa.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengumpulkan ujian: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset student session / allow re-login after device conflict or disconnect
     */
    public function resetSession(Request $request, int $id, int $studentId)
    {
        try {
            $attempt = ExamAttempt::where('subject_exam_id', $id)
                ->where('student_id', $studentId)
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi ujian siswa tidak ditemukan.'
                ], 404);
            }

            // Clear device token and reset locked status if force-completed by mistake
            $resetCompletion = $request->boolean('reopen_exam', false);
            
            $updateData = [
                'current_exam_device_token' => null,
                'is_frozen' => false,
                'frozen_at' => null,
                'last_activity_at' => now(),
            ];

            if ($resetCompletion) {
                $updateData['is_completed'] = false;
                $updateData['completed_at'] = null;
                $updateData['forced_reason'] = null;
                // Give back 1 violation leeway if limit reached
                if ($attempt->violation_count >= $attempt->max_violations) {
                    $updateData['violation_count'] = max(0, $attempt->max_violations - 1);
                }
                if (($attempt->violation_score ?? 0) >= ($attempt->max_violation_score ?? 3.0)) {
                    $updateData['violation_score'] = max(0.0, ($attempt->max_violation_score ?? 3.0) - 1.0);
                }
            }

            $attempt->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Sesi perangkat siswa berhasil di-reset. Siswa kini dapat masuk kembali.',
                'data' => $attempt
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereset sesi: ' . $e->getMessage()
            ], 500);
        }
    }
}
