<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    /**
     * Get global or class-filtered public leaderboard & student achievements
     */
    public function index(Request $request)
    {
        try {
            $currentUser = $request->user();
            $classroomId = $request->query('classroom_id');

            // 1. Fetch classrooms for filtering dropdown
            $classrooms = ClassRoom::select('id', 'name')->orderBy('name')->get();

            // 2. Query students
            $studentQuery = User::role('student')->with(['classStudents.classRoom']);

            if ($classroomId) {
                $studentQuery->whereHas('classStudents', function ($q) use ($classroomId) {
                    $q->where('class_room_id', $classroomId);
                });
            }

            $students = $studentQuery->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'leaderboard' => [],
                        'current_user_rank' => null,
                        'classrooms' => $classrooms,
                        'total_participants' => 0,
                    ]
                ]);
            }

            $studentIds = $students->pluck('id');

            // 3. Fetch completed exam attempts for these students
            $attempts = ExamAttempt::whereIn('student_id', $studentIds)
                ->where('is_completed', true)
                ->get()
                ->groupBy('student_id');

            // 4. Calculate stats & badges for each student
            $leaderboard = $students->map(function ($student) use ($attempts) {
                $studentAttempts = $attempts->get($student->id, collect());

                $totalPoints = (int) $studentAttempts->sum('points_earned');
                $completedCount = $studentAttempts->count();
                $passedCount = $studentAttempts->where('has_passed', true)->count();
                $totalViolations = (int) $studentAttempts->sum('violation_count');

                // Check for perfect score (100% or points_earned >= total_points)
                $hasPerfectScore = $studentAttempts->contains(function ($att) {
                    return ($att->total_points > 0 && $att->points_earned >= $att->total_points);
                });

                // Calculate average percentage
                $avgPercentage = 0;
                if ($completedCount > 0) {
                    $percentages = $studentAttempts->map(function ($att) {
                        return $att->total_points > 0 ? ($att->points_earned / $att->total_points) * 100 : 0;
                    });
                    $avgPercentage = round($percentages->avg(), 1);
                }

                // Award Badges
                $badges = [];

                if ($completedCount >= 2 && $totalViolations === 0) {
                    $badges[] = [
                        'id' => 'integrity',
                        'name' => 'Integritas Tinggi',
                        'icon' => 'ShieldCheck',
                        'color' => '#10B981',
                        'description' => 'Menyelesaikan ujian tanpa catatan pelanggaran',
                    ];
                }

                if ($hasPerfectScore) {
                    $badges[] = [
                        'id' => 'perfect',
                        'name' => 'Nilai Sempurna',
                        'icon' => 'Sparkles',
                        'color' => '#F59E0B',
                        'description' => 'Mencapai skor maksimal 100 poin',
                    ];
                }

                if ($completedCount >= 3) {
                    $badges[] = [
                        'id' => 'veteran',
                        'name' => 'CBT Veteran',
                        'icon' => 'Award',
                        'color' => '#6366F1',
                        'description' => 'Telah menyelesaikan 3 atau lebih sesi ujian',
                    ];
                }

                if ($passedCount >= 2) {
                    $badges[] = [
                        'id' => 'consistent',
                        'name' => 'Siswa Konsisten',
                        'icon' => 'Zap',
                        'color' => '#EF3F09',
                        'description' => 'Berhasil lulus minimal 2 mata pelajaran',
                    ];
                }

                $primaryClassroom = $student->classStudents->first()?->classRoom?->name ?? 'Belum ada kelas';

                return [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'photo' => $student->photo,
                    'classroom' => $primaryClassroom,
                    'total_points' => $totalPoints,
                    'completed_exams' => $completedCount,
                    'passed_exams' => $passedCount,
                    'total_violations' => $totalViolations,
                    'average_score' => $avgPercentage,
                    'badges' => $badges,
                ];
            });

            // 5. Sort by points desc, then completed exams desc, then average score desc
            $sortedLeaderboard = $leaderboard->sort(function ($a, $b) {
                if ($a['total_points'] !== $b['total_points']) {
                    return $b['total_points'] <=> $a['total_points'];
                }
                if ($a['completed_exams'] !== $b['completed_exams']) {
                    return $b['completed_exams'] <=> $a['completed_exams'];
                }
                return $b['average_score'] <=> $a['average_score'];
            })->values();

            // 6. Assign ranks and podium badges
            $rankedLeaderboard = $sortedLeaderboard->map(function ($item, $index) {
                $rank = $index + 1;
                $item['rank'] = $rank;

                // Add podium badge for top 3
                if ($rank === 1) {
                    array_unshift($item['badges'], [
                        'id' => 'gold_podium',
                        'name' => 'Juara 1 Sekolah',
                        'icon' => 'Trophy',
                        'color' => '#EAB308',
                        'description' => 'Peringkat 1 perolehan poin tertinggi',
                    ]);
                } elseif ($rank === 2) {
                    array_unshift($item['badges'], [
                        'id' => 'silver_podium',
                        'name' => 'Juara 2',
                        'icon' => 'Medal',
                        'color' => '#94A3B8',
                        'description' => 'Peringkat 2 perolehan poin tertinggi',
                    ]);
                } elseif ($rank === 3) {
                    array_unshift($item['badges'], [
                        'id' => 'bronze_podium',
                        'name' => 'Juara 3',
                        'icon' => 'Medal',
                        'color' => '#D97706',
                        'description' => 'Peringkat 3 perolehan poin tertinggi',
                    ]);
                }

                return $item;
            });

            // 7. Find current user's position
            $currentUserRank = null;
            if ($currentUser) {
                $found = $rankedLeaderboard->firstWhere('student_id', $currentUser->id);
                if ($found) {
                    $currentUserRank = $found;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'leaderboard' => $rankedLeaderboard,
                    'current_user_rank' => $currentUserRank,
                    'classrooms' => $classrooms,
                    'total_participants' => $rankedLeaderboard->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leaderboard: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
