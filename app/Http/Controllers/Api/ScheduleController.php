<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\ClassroomActivity;
use App\Models\ExamAttempt;
use App\Models\Project;
use App\Models\Subject;
use App\Models\SubjectExam;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Get schedule events with strict role and classroom access filtering
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $isManager = $user->hasRole('manager');
            $isTeacher = $user->hasRole('teacher');
            $isStudent = !$isManager && !$isTeacher;

            $now = Carbon::now();
            $events = collect();
            $availableClassrooms = collect();
            $isUnenrolled = false;

            if ($isStudent) {
                // 1. Strict Student Access: only show exams, activities, and projects for classrooms they are enrolled in
                $enrolledRecords = ClassStudent::with('classRoom')
                    ->where('student_id', $user->id)
                    ->get();

                $classroomIds = $enrolledRecords->pluck('class_room_id')->filter()->unique()->values();

                if ($classroomIds->isEmpty()) {
                    // Student is not enrolled in any classroom. Return empty calendar with an unenrolled notice.
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'events' => [],
                            'classrooms' => [],
                            'is_unenrolled' => true,
                            'summary' => [
                                'total_events' => 0,
                                'total_exams' => 0,
                                'upcoming_exams' => 0,
                                'active_exams' => 0,
                                'total_activities' => 0,
                            ]
                        ]
                    ]);
                }

                $availableClassrooms = $enrolledRecords->map(fn($cs) => [
                    'id' => $cs->classRoom?->id,
                    'name' => $cs->classRoom?->name,
                ])->filter(fn($c) => !empty($c['id']))->unique('id')->values();

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

                // Fetch student attempts
                $examIds = $exams->pluck('id');
                $attempts = ExamAttempt::where('student_id', $user->id)
                    ->whereIn('subject_exam_id', $examIds)
                    ->get()
                    ->keyBy('subject_exam_id');

            } elseif ($isTeacher) {
                // 2. Teacher Access: show subjects they teach and classes associated with those subjects
                $teacherSubjectIds = Subject::where('teacher_id', $user->id)->pluck('id');
                $teacherClassroomIds = ClassSubject::whereIn('subject_id', $teacherSubjectIds)
                    ->pluck('class_room_id')
                    ->unique()
                    ->values();

                $availableClassrooms = ClassRoom::whereIn('id', $teacherClassroomIds)
                    ->select('id', 'name')
                    ->get();

                $filterClassId = $request->input('class_room_id');
                if ($filterClassId && $filterClassId !== 'all') {
                    $subjectIds = ClassSubject::where('class_room_id', $filterClassId)
                        ->whereIn('subject_id', $teacherSubjectIds)
                        ->pluck('subject_id');

                    $exams = SubjectExam::with(['subject', 'topic'])->whereIn('subject_id', $subjectIds)->get();
                    $activities = ClassroomActivity::with('classRoom')->where('class_room_id', $filterClassId)->get();
                    $projects = Project::with('classRoom')->where('class_room_id', $filterClassId)->get();
                } else {
                    $exams = SubjectExam::with(['subject', 'topic'])->whereIn('subject_id', $teacherSubjectIds)->get();
                    $activities = ClassroomActivity::with('classRoom')->whereIn('class_room_id', $teacherClassroomIds)->get();
                    $projects = Project::with('classRoom')->whereIn('class_room_id', $teacherClassroomIds)->get();
                }

                $attempts = collect();

            } else {
                // 3. Manager (Admin) Access: full school-wide schedule with optional classroom filter
                $availableClassrooms = ClassRoom::select('id', 'name')->get();

                $filterClassId = $request->input('class_room_id');
                if ($filterClassId && $filterClassId !== 'all') {
                    $subjectIds = ClassSubject::where('class_room_id', $filterClassId)
                        ->pluck('subject_id')
                        ->filter()
                        ->unique()
                        ->values();

                    $exams = SubjectExam::with(['subject', 'topic'])->whereIn('subject_id', $subjectIds)->get();
                    $activities = ClassroomActivity::with('classRoom')->where('class_room_id', $filterClassId)->get();
                    $projects = Project::with('classRoom')->where('class_room_id', $filterClassId)->get();
                } else {
                    $exams = SubjectExam::with(['subject', 'topic'])->get();
                    $activities = ClassroomActivity::with('classRoom')->get();
                    $projects = Project::with('classRoom')->get();
                }

                $attempts = collect();
            }

            // Map Exams
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
                    'category' => $exam->category ?? 'Ujian CBT',
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

            // Map Classroom Activities
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
                    'category' => ucfirst($act->type ?? 'Tugas'),
                    'subject_name' => $act->classRoom?->name ?? 'Kelas',
                    'classroom_name' => $act->classRoom?->name,
                    'start_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'end_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'date' => $dueDate ? $dueDate->format('Y-m-d') : $now->format('Y-m-d'),
                    'points' => $act->points ?? 0,
                    'status' => $status,
                    'description' => $act->description,
                    'url' => "/dashboard/classrooms",
                ]);
            }

            // Map Projects
            foreach ($projects as $proj) {
                $dueDate = $proj->due_date ? Carbon::parse($proj->due_date) : null;
                $events->push([
                    'id' => 'project-' . $proj->id,
                    'real_id' => $proj->id,
                    'type' => 'project',
                    'title' => $proj->title,
                    'category' => 'Proyek Kelas',
                    'subject_name' => $proj->classRoom?->name ?? 'Proyek',
                    'classroom_name' => $proj->classRoom?->name,
                    'start_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'end_date' => $dueDate ? $dueDate->format('Y-m-d H:i:s') : null,
                    'date' => $dueDate ? $dueDate->format('Y-m-d') : $now->format('Y-m-d'),
                    'status' => $proj->status ?? 'in_progress',
                    'progress' => $proj->progress ?? 0,
                    'description' => $proj->description ?? null,
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
                    'classrooms' => $availableClassrooms,
                    'is_unenrolled' => $isUnenrolled,
                    'summary' => $summary,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store custom manual event into the schedule (Only Admin & Teacher)
     */
    public function storeEvent(Request $request)
    {
        try {
            $user = $request->user();

            // Authorization: only manager and teacher can create events
            if (!$user->hasAnyRole(['manager', 'teacher'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Administrator dan Guru yang memiliki izin untuk menambahkan agenda.'
                ], 403);
            }

            $data = $request->validate([
                'title' => 'required|string|max:255',
                'category' => 'nullable|string',
                'due_date' => 'required|date',
                'description' => 'nullable|string',
                'class_room_id' => 'nullable',
            ]);

            $targetClassId = $data['class_room_id'] ?? null;
            $createdActivities = [];

            if (!$targetClassId || $targetClassId === 'all') {
                // School-wide: assign to all existing classrooms so every enrolled student sees it
                $allClassrooms = ClassRoom::all();
                if ($allClassrooms->isEmpty()) {
                    // Fallback create dummy or single record
                    $act = ClassroomActivity::create([
                        'class_room_id' => 1,
                        'title' => $data['title'],
                        'type' => 'other',
                        'description' => $data['description'] ?? null,
                        'due_date' => $data['due_date'],
                        'status' => 'active',
                        'points' => 0,
                        'created_by' => $user->id,
                    ]);
                    $createdActivities[] = $act;
                } else {
                    foreach ($allClassrooms as $cr) {
                        $act = ClassroomActivity::create([
                            'class_room_id' => $cr->id,
                            'title' => $data['title'],
                            'type' => 'other',
                            'description' => $data['description'] ?? null,
                            'due_date' => $data['due_date'],
                            'status' => 'active',
                            'points' => 0,
                            'created_by' => $user->id,
                        ]);
                        $createdActivities[] = $act;
                    }
                }
            } else {
                // Targeted to specific classroom
                $act = ClassroomActivity::create([
                    'class_room_id' => (int) $targetClassId,
                    'title' => $data['title'],
                    'type' => 'other',
                    'description' => $data['description'] ?? null,
                    'due_date' => $data['due_date'],
                    'status' => 'active',
                    'points' => 0,
                    'created_by' => $user->id,
                ]);
                $createdActivities[] = $act;
            }

            return response()->json([
                'success' => true,
                'message' => 'Agenda kegiatan berhasil ditambahkan ke kalender.',
                'data' => $createdActivities[0] ?? null
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan agenda: ' . $e->getMessage()
            ], 500);
        }
    }
}
