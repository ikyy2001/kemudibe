<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    /**
     * Platform-wide aggregated metrics and overview.
     */
    public function index(): JsonResponse
    {
        $totalInstitutions = Institution::count();
        $activeInstitutions = Institution::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })->count();
        $suspendedInstitutions = Institution::where('status', 'suspended')->count();
        $expiredInstitutions = Institution::where('expires_at', '<', now())->count();

        // Platform-wide user metrics
        $totalStudents = User::withoutTenant()->role('student')->count();
        $totalTeachers = User::withoutTenant()->role('teacher')->count();
        $totalPics = User::withoutTenant()->role('pic')->count();

        // Academic counts
        $totalClassrooms = DB::table('class_rooms')->count();
        $totalSubjects = DB::table('subjects')->count();
        $totalExams = DB::table('subject_exams')->count();
        $totalQuestions = DB::table('exam_questions')->count();
        $totalExamAttempts = DB::table('exam_attempts')->count();

        // Platform Storage
        $totalStorageUsedBytes = (int) Institution::sum('storage_used_bytes');
        $totalStorageCapacityMb = (int) Institution::sum('max_storage_mb');
        $totalStorageUsedMb = round($totalStorageUsedBytes / (1024 * 1024), 2);

        // Recent 5 institutions
        $recentInstitutions = Institution::latest()
            ->limit(5)
            ->get()
            ->map(fn ($inst) => [
                'id' => $inst->id,
                'name' => $inst->name,
                'slug' => $inst->slug,
                'status' => $inst->status,
                'max_students' => $inst->max_students,
                'active_students' => $inst->getActiveStudentsCount(),
                'storage_used_mb' => round($inst->storage_used_bytes / (1024 * 1024), 2),
                'created_at' => $inst->created_at?->format('Y-m-d H:i:s'),
            ]);

        // Recent 10 audit logs
        $recentAuditLogs = AuditLog::with([
            'institution:id,name,slug',
            'user:id,name,username',
        ])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'institutions' => [
                    'total' => $totalInstitutions,
                    'active' => $activeInstitutions,
                    'suspended' => $suspendedInstitutions,
                    'expired' => $expiredInstitutions,
                ],
                'users' => [
                    'students' => $totalStudents,
                    'teachers' => $totalTeachers,
                    'pics' => $totalPics,
                    'total' => $totalStudents + $totalTeachers + $totalPics,
                ],
                'academic' => [
                    'classrooms' => $totalClassrooms,
                    'subjects' => $totalSubjects,
                    'exams' => $totalExams,
                    'questions' => $totalQuestions,
                    'exam_attempts' => $totalExamAttempts,
                ],
                'storage' => [
                    'used_bytes' => $totalStorageUsedBytes,
                    'used_mb' => $totalStorageUsedMb,
                    'used_gb' => round($totalStorageUsedMb / 1024, 2),
                    'total_capacity_mb' => $totalStorageCapacityMb,
                    'total_capacity_gb' => round($totalStorageCapacityMb / 1024, 2),
                    'percentage' => $totalStorageCapacityMb > 0
                        ? min(100, round(($totalStorageUsedMb / $totalStorageCapacityMb) * 100, 1))
                        : 0,
                ],
                'recent_institutions' => $recentInstitutions,
                'recent_audit_logs' => $recentAuditLogs,
            ]
        ]);
    }
}
