<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\SubjectExam;
use App\Models\ClassRoom;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    /**
     * Get grades list and overview
     */
    public function index(Request $request)
    {
        try {
            $query = ExamAttempt::with([
                'student:id,name,email,photo',
                'subjectExam:id,name,subject_id,total_points',
                'subjectExam.subject:id,name'
            ]);

            if ($request->filled('exam_id')) {
                $query->where('subject_exam_id', $request->integer('exam_id'));
            }

            if ($request->user() && $request->user()->hasRole('student')) {
                $query->where('student_id', $request->user()->id);
            } elseif ($request->filled('student_id')) {
                $query->where('student_id', $request->integer('student_id'));
            }

            if ($request->filled('status')) {
                if ($request->string('status') === 'passed') {
                    $query->where('has_passed', true);
                } elseif ($request->string('status') === 'failed') {
                    $query->where('has_passed', false)->where('is_completed', true);
                } elseif ($request->string('status') === 'in_progress') {
                    $query->where('is_completed', false);
                }
            }

            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->whereHas('student', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $attempts = $query->orderBy('created_at', 'desc')->get();

            // Transform into grade items
            $grades = $attempts->map(function ($attempt) {
                $score = $attempt->score_percentage ?? 0;
                $letter = 'F';
                if ($score >= 90) $letter = 'A';
                elseif ($score >= 80) $letter = 'B';
                elseif ($score >= 70) $letter = 'C';
                elseif ($score >= 60) $letter = 'D';

                return [
                    'id' => $attempt->id,
                    'student_id' => $attempt->student_id,
                    'student_name' => $attempt->student?->name ?? 'Unknown Student',
                    'student_email' => $attempt->student?->email,
                    'student_photo' => $attempt->student?->photo,
                    'exam_id' => $attempt->subject_exam_id,
                    'exam_name' => $attempt->subjectExam?->name ?? 'General Exam',
                    'subject_name' => $attempt->subjectExam?->subject?->name ?? 'General',
                    'points_earned' => $attempt->points_earned ?? 0,
                    'total_points' => $attempt->total_points ?? $attempt->subjectExam?->total_points ?? 100,
                    'score_percentage' => round($score, 1),
                    'grade' => $letter,
                    'has_passed' => (bool)$attempt->has_passed,
                    'is_completed' => (bool)$attempt->is_completed,
                    'completed_at' => $attempt->completed_at?->format('Y-m-d H:i') ?? $attempt->updated_at->format('Y-m-d H:i'),
                ];
            });

            // Calculate stats
            $totalAttempts = $grades->count();
            $avgScore = $totalAttempts > 0 ? round($grades->avg('score_percentage'), 1) : 0;
            $passedCount = $grades->where('has_passed', true)->count();
            $passRate = $totalAttempts > 0 ? round(($passedCount / $totalAttempts) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => $grades,
                'stats' => [
                    'total_records' => $totalAttempts,
                    'average_score' => $avgScore,
                    'passed_count' => $passedCount,
                    'failed_count' => $totalAttempts - $passedCount,
                    'pass_rate' => $passRate,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grades',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
