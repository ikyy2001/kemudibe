<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\ClassroomActivity;
use App\Models\ExamAttempt;
use App\Models\Project;
use App\Models\SubjectExam;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Get student schedule events (Exams, Activities, Deadlines)
     */
    public function index(Request $request)
    {
        try {
            $student = $request->user();

            // 1. Get classrooms where student is enrolled
            $enrolledClassrooms = ClassStudent::with('classRoom')
                ->where('student_id', $student->id)
                ->get();

            // 2. Get subjects linked to these classrooms (or all subjects if not enrolled)
            if ($classroomIds->isNotEmpty()) {
                $subjectIds = ClassSubject::whereIn('class_room_id', $classroomIds)
                    ->pluck('subject_id')
                    ->filter()
                    ->unique()
                    ->values();

                $exams = SubjectExam::with(['subject', 'topic'])
                    ->whereIn('subject_id', $subjectIds)
                    ->get();

                $activities = ClassroomActivity::with('classRoom')
                    ->whereIn('class_room_id', $classroomIds)
                    ->get();

                $projects = Project::with('classRoom')
                    ->whereIn('class_room_id', $classroomIds)
                    ->get();
            } else {
                // Fallback for students not yet enrolled: show all upcoming/active exams
                $exams = SubjectExam::with(['subject', 'topic'])->get();
                $activities = ClassroomActivity::with('classRoom')->get();
                $projects = Project::with('classRoom')->get();
            }

            // 4. Fetch student attempts for these exams
            $examIds = $exams->pluck('id');
            $attempts = ExamAttempt::where('student_id', $student->id)
                ->whereIn('subject_exam_id', $examIds)
                ->get()
                ->keyBy('subject_exam_id');

            // 5. Fetch Classroom Activities (challenges, homeworks)
            $activities = ClassroomActivity::with('classRoom')
                ->whereIn('class_room_id', $classroomIds)
                ->get();

            // 6. Fetch Projects
            $projects = Project::with('classRoom')
                ->whereIn('class_room_id', $classroomIds)
                ->get();

            $events = collect();
            $now = Carbon::now();

            // Process Exams
            foreach ($exams as $exam) {
                $attempt = $attempts->get($exam->id);
                $isCompleted = $attempt && $attempt->is_completed;

                $startDate = $exam->started_at ? Carbon::parse($exam->started_at) : null;
                $endDate = $exam->ended_at ? Carbon::parse($exam->ended_at)->endOfDay() : null;

                $status = 'upcoming';
                if ($isCompleted) {
                    $status = 'completed';
                } elseif ($endDate && $now->gt($endDate)) {
                    $status = 'expired';
                } elseif ($startDate && $now->gte($startDate)) {
                    $status = 'active';
                }

                $events->push([
                    'id' => 'exam-' . $exam->id,
                    'real_id' => $exam->id,
                    'type' => 'exam',
                    'title' => $exam->name,
                    'category' => $exam->category ?? 'CBT Exam',
                    'subject_name' => $exam->subject?->name ?? 'General',
                    'topic_name' => $exam->topic?->name,
                    'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : null,
                    'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : null,
                    'date' => $startDate ? $startDate->format('Y-m-d') : ($endDate ? $endDate->format('Y-m-d') : $now->format('Y-m-d')),
                    'duration_minutes' => $exam->duration_minutes ?? 60,
                    'passing_grade' => $exam->passing_grade ?? 75,
                    'total_points' => $exam->total_points ?? 100,
                    'status' => $status,
                    'is_completed' => $isCompleted,
                    'points_earned' => $attempt?->points_earned,
                    'url' => $isCompleted ? "/dashboard/exams/{$exam->id}/results" : "/dashboard/exams/{$exam->id}/guidelines",
                ]);
            }

            // Process Classroom Activities
            foreach ($activities as $act) {
                $dueDate = $act->due_date ? Carbon::parse($act->due_date) : null;
                $status = 'pending';
                if ($dueDate && $now->gt($dueDate)) {
                    $status = 'expired';
                }

                $events->push([
                    'id' => 'activity-' . $act->id,
                    'real_id' => $act->id,
                    'type' => 'activity',
                    'title' => $act->title,
                    'category' => ucfirst($act->type ?? 'Homework'),
                    'subject_name' => $act->classRoom?->name ?? 'Classroom',
                    'classroom_name' => $act->classRoom?->name,
                    'start_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'end_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'date' => $dueDate ? $dueDate->format('Y-m-d') : $now->format('Y-m-d'),
                    'points' => $act->points ?? 0,
                    'status' => $status,
                    'url' => "/dashboard/classrooms",
                ]);
            }

            // Process Projects
            foreach ($projects as $proj) {
                $dueDate = $proj->due_date ? Carbon::parse($proj->due_date) : null;
                $events->push([
                    'id' => 'project-' . $proj->id,
                    'real_id' => $proj->id,
                    'type' => 'project',
                    'title' => $proj->title,
                    'category' => 'Class Project',
                    'subject_name' => $proj->classRoom?->name ?? 'Classroom Project',
                    'classroom_name' => $proj->classRoom?->name,
                    'start_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'end_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'date' => $dueDate ? $dueDate->format('Y-m-d') : $now->format('Y-m-d'),
                    'status' => $proj->status ?? 'in_progress',
                    'progress' => $proj->progress ?? 0,
                    'url' => "/dashboard/classrooms",
                ]);
            }

            // Sort events by date ascending
            $sortedEvents = $events->sortBy('date')->values();

            $summary = [
                'total_events' => $sortedEvents->count(),
                'total_exams' => $exams->count(),
                'upcoming_exams' => $sortedEvents->where('type', 'exam')->where('status', 'upcoming')->count(),
                'active_exams' => $sortedEvents->where('type', 'exam')->where('status', 'active')->count(),
                'total_activities' => $activities->count() + $projects->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'events' => $sortedEvents,
                    'classrooms' => $enrolledClassrooms->map(fn($cs) => [
                        'id' => $cs->classRoom?->id,
                        'name' => $cs->classRoom?->name,
                    ])->unique('id')->values(),
                    'summary' => $summary,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedule: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store custom manual event into the schedule
     */
    public function storeEvent(Request $request)
    {
        try {
            $user = $request->user();
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'nullable|string',
                'due_date' => 'required|date',
                'description' => 'nullable|string',
                'class_room_id' => 'nullable|integer',
            ]);

            // Assign default class_room_id if not given
            $classroomId = $data['class_room_id'] ?? null;
            if (!$classroomId) {
                $firstClassroom = \App\Models\ClassRoom::first();
                $classroomId = $firstClassroom ? $firstClassroom->id : 1;
            }

            $activity = ClassroomActivity::create([
                'class_room_id' => $classroomId,
                'title' => $data['title'],
                'type' => 'other',
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'],
                'status' => 'active',
                'points' => 0,
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agenda kegiatan berhasil ditambahkan ke kalender.',
                'data' => $activity
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan agenda: ' . $e->getMessage()
            ], 500);
        }
    }
}
