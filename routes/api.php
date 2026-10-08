<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\ClassRoomController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClassStudentController;
use App\Http\Controllers\Api\ClassSubjectController;
use App\Http\Controllers\Api\SubjectExamController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentExamController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\QuestionAnswerController;
use App\Http\Controllers\Api\ExamQuestionController;
use App\Http\Controllers\Api\QuestionOptionController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\ClassroomActivityController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\ExamProctorController;
use App\Http\Controllers\Api\SuperAdmin\InstitutionController as SuperAdminInstitutionController;
use App\Http\Controllers\Api\SuperAdmin\OverviewController as SuperAdminOverviewController;
use App\Http\Controllers\Api\SuperAdmin\AuditLogController as SuperAdminAuditLogController;
use App\Models\Institution;

/*
|--------------------------------------------------------------------------
| Public & Platform Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('login', [AuthController::class, 'login']);
Route::post('token-login', [AuthController::class, 'tokenLogin']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('superadmin/login', [AuthController::class, 'superAdminLogin']);

// Public institution lookup (for frontend validation & login screen branding)
Route::get('institutions/{slug}', function ($slug) {
    $inst = Institution::where('slug', $slug)->first();
    if (!$inst) {
        return response()->json([
            'message' => "Lembaga '{$slug}' tidak ditemukan.",
            'code' => 'INSTITUTION_NOT_FOUND',
        ], 404);
    }
    return response()->json([
        'data' => [
            'id' => $inst->id,
            'name' => $inst->name,
            'slug' => $inst->slug,
            'logo' => $inst->logo,
            'status' => $inst->status,
            'is_read_only' => $inst->isReadOnly(),
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| Global Authenticated User Routes (Session & Identity)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);
    Route::put('user/password', [ProfileController::class, 'updatePassword']);
});

/*
|--------------------------------------------------------------------------
| Platform Super Admin Routes: /api/superadmin/...
|--------------------------------------------------------------------------
*/
Route::prefix('superadmin')
    ->middleware(['auth:sanctum', 'role:superadmin'])
    ->group(function () {
        Route::get('overview', [SuperAdminOverviewController::class, 'index']);
        Route::get('institutions', [SuperAdminInstitutionController::class, 'index']);
        Route::post('institutions', [SuperAdminInstitutionController::class, 'store']);
        Route::get('institutions/{id}', [SuperAdminInstitutionController::class, 'show']);
        Route::put('institutions/{id}', [SuperAdminInstitutionController::class, 'update']);
        Route::delete('institutions/{id}', [SuperAdminInstitutionController::class, 'destroy']);
        Route::post('institutions/{id}/reset-pic-password', [SuperAdminInstitutionController::class, 'resetPicPassword']);
        Route::get('audit-logs', [SuperAdminAuditLogController::class, 'index']);
    });

/*
|--------------------------------------------------------------------------
| Tenant-Scoped Academic Routes: /api/{institution}/...
|--------------------------------------------------------------------------
| Handled by ResolveInstitution middleware:
| - Validates institution exists (or 404)
| - Validates user belongs to institution (or 403 INSTITUTION_MISMATCH)
| - Enforces read-only mode if suspended/expired
| - Sets active InstitutionContext
|--------------------------------------------------------------------------
*/
Route::prefix('{institution}')->middleware(['auth:sanctum', 'institution'])->group(function () {

    // Common Profile, Usage & Leaderboard
    Route::get('user/profile', [ProfileController::class, 'getProfile']);
    Route::match(['put', 'post'], 'user/profile', [ProfileController::class, 'updateProfile']);
    Route::get('achievements/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('usage', function () {
        $inst = app(\App\Services\InstitutionContext::class)->getInstitution();
        if (!$inst) {
            return response()->json(['message' => 'Lembaga tidak ditemukan.'], 404);
        }
        return response()->json([
            'data' => [
                'id' => $inst->id,
                'name' => $inst->name,
                'slug' => $inst->slug,
                'logo' => $inst->logo,
                'status' => $inst->status,
                'starts_at' => $inst->starts_at?->format('Y-m-d'),
                'expires_at' => $inst->expires_at?->format('Y-m-d'),
                'max_students' => $inst->max_students,
                'active_students' => $inst->getActiveStudentsCount(),
                'max_storage_mb' => $inst->max_storage_mb,
                'storage_used_bytes' => $inst->storage_used_bytes,
                'storage_used_mb' => round($inst->storage_used_bytes / (1024 * 1024), 2),
                'is_read_only' => $inst->isReadOnly(),
            ]
        ]);
    });

    // PIC (Manager) Only Routes
    Route::middleware('role:pic|manager')->group(function () {
        Route::post('topics', [TopicController::class, 'store']);
        Route::put('topics/{id}', [TopicController::class, 'update']);
        Route::patch('topics/{id}', [TopicController::class, 'update']);
        Route::delete('topics/{id}', [TopicController::class, 'destroy']);

        // Subjects
        Route::get('subjects/search', [SubjectController::class, 'search']);
        Route::apiResource('subjects', SubjectController::class)->except(['show', 'update']);

        // ClassRooms
        Route::get('class-rooms/search', [ClassRoomController::class, 'search']);
        Route::get('class-rooms/{id}/students', [ClassRoomController::class, 'students']);
        Route::get('class-rooms/{id}/subjects', [ClassRoomController::class, 'subjects']);
        Route::post('class-rooms/{id}/enroll-student', [ClassRoomController::class, 'enrollStudent']);
        Route::delete('class-rooms/{id}/students/{studentId}', [ClassRoomController::class, 'unenrollStudent']);
        Route::post('class-rooms/{id}/assign-subject', [ClassRoomController::class, 'assignSubject']);
        Route::delete('class-rooms/{id}/subjects/{subjectId}', [ClassRoomController::class, 'unassignSubject']);
        Route::apiResource('class-rooms', ClassRoomController::class)->except(['show']);
        Route::post('teachers', [TeacherController::class, 'store']);
        Route::put('teachers/{id}', [TeacherController::class, 'update']);
        Route::patch('teachers/{id}', [TeacherController::class, 'update']);
        Route::delete('teachers/{id}', [TeacherController::class, 'destroy']);

        // Students
        Route::get('students/search', [StudentController::class, 'search']);
        Route::get('students/{id}/statistics', [StudentController::class, 'statistics']);
        Route::apiResource('students', StudentController::class);

        // Bulk Account Creation (PIC/Manager Only)
        Route::get('users/bulk-template', [UserController::class, 'downloadBulkTemplate']);
        Route::post('users/bulk-import', [UserController::class, 'bulkImport']);

        // Class Students (Enrollments)
        Route::get('class-students/search', [ClassStudentController::class, 'search']);
        Route::post('classrooms/{classRoomId}/bulk-enroll', [ClassStudentController::class, 'bulkEnroll']);
        Route::put('students/{studentId}/classrooms/{classRoomId}/status', [ClassStudentController::class, 'updateStatus']);

        // Rapport PDF Management (PIC/Manager only - upload/delete)
        Route::post('students/{studentId}/classrooms/{classRoomId}/rapport', [ClassStudentController::class, 'uploadRapport']);
        Route::delete('students/{studentId}/classrooms/{classRoomId}/rapport', [ClassStudentController::class, 'deleteRapport']);

        Route::apiResource('class-students', ClassStudentController::class);

        // Class Subjects (Subject-Classroom Assignments)
        Route::get('class-subjects/search', [ClassSubjectController::class, 'search']);
        Route::get('classrooms/{classRoomId}/available-subjects', [ClassSubjectController::class, 'availableSubjects']);
        Route::get('subjects/{subjectId}/available-classrooms', [ClassSubjectController::class, 'availableClassRooms']);
        Route::post('classrooms/{classRoomId}/bulk-assign-subjects', [ClassSubjectController::class, 'bulkAssignToClassRoom']);
        Route::apiResource('class-subjects', ClassSubjectController::class);

        // Subject Exams (PIC/Manager only operations)
        Route::get('subject-exams/search', [SubjectExamController::class, 'search']);
        Route::post('subject-exams/{id}/duplicate', [SubjectExamController::class, 'duplicate']);
    });

    // Shared Detail Routes - Accessible by PIC/Manager, Teacher, and Student
    Route::middleware('role:pic|manager|teacher|student')->group(function () {
        // Classroom Details
        Route::get('class-rooms/{id}', [ClassRoomController::class, 'show']);
        Route::get('class-rooms/{id}/students', [ClassRoomController::class, 'students']);
        Route::get('class-rooms/{id}/subjects', [ClassRoomController::class, 'subjects']);
        Route::get('class-rooms/{id}/statistics', [ClassRoomController::class, 'statistics']);

        // Subject Details
        Route::get('subjects/{id}', [SubjectController::class, 'show']);

        // Topic Details
        Route::get('topics', [TopicController::class, 'index']);
        Route::get('topics/search', [TopicController::class, 'search']);
        Route::get('topics/{id}', [TopicController::class, 'show']);

        // Lessons (Materials, Drive, Videos)
        Route::get('lessons', [LessonController::class, 'index']);
        Route::get('lessons/{id}', [LessonController::class, 'show']);

        // Subject Exam Details
        Route::get('subject-exams/{id}', [SubjectExamController::class, 'show']);
        Route::get('subject-exams/{id}/statistics', [SubjectExamController::class, 'statistics']);
        Route::get('subject-exams/{id}/status', [SubjectExamController::class, 'status']);

        // Rapport PDF viewing
        Route::get('students/{studentId}/classrooms/{classRoomId}/rapport/info', [ClassStudentController::class, 'getRapportInfo']);
        Route::get('students/{studentId}/classrooms/{classRoomId}/rapport/download', [ClassStudentController::class, 'downloadRapport']);

        // Classroom Activities (Challenges, Homeworks, Others)
        Route::get('class-rooms/{classroomId}/activities', [ClassroomActivityController::class, 'index']);
        Route::get('class-rooms/{classroomId}/activities/{id}', [ClassroomActivityController::class, 'show']);

        // Academic Schedule & Calendar
        Route::get('student/schedule', [ScheduleController::class, 'index']);
        Route::get('schedule', [ScheduleController::class, 'index']);
        Route::post('schedule/events', [ScheduleController::class, 'storeEvent']);
    });

    // Teacher-specific routes
    Route::middleware('role:teacher')->group(function () {
        Route::get('teacher/subjects', [SubjectController::class, 'teacherSubjects']);
        Route::get('teacher/subjects/search', [SubjectController::class, 'teacherSubjectsSearch']);
        Route::get('teacher/profile', [TeacherController::class, 'profile']);
        Route::get('teacher/subject-exams', [SubjectExamController::class, 'teacherExams']);
        Route::post('teacher/subject-exams', [SubjectExamController::class, 'createTeacherExam']);
        Route::put('teacher/subject-exams/{id}', [SubjectExamController::class, 'updateTeacherExam']);
        Route::delete('teacher/subject-exams/{id}', [SubjectExamController::class, 'deleteTeacherExam']);
        Route::get('teacher/subject-exams/{id}/answers', [SubjectExamController::class, 'getExamAnswers']);
        Route::get('teacher/exams/{id}/students', [SubjectExamController::class, 'getExamStudentsStatus']);
        Route::get('teacher/exams/{examId}/students/{studentId}', [SubjectExamController::class, 'getStudentExamDetails']);
        Route::get('teacher/classrooms', [ClassSubjectController::class, 'teacherClassRooms']);
        Route::get('teacher/students/{studentId}/profile', [StudentController::class, 'teacherStudentProfile']);
    });

    // Student-specific routes
    Route::middleware('role:student')->group(function () {
        Route::get('student/profile', [StudentController::class, 'profile']);
        Route::get('student/classrooms', [ClassStudentController::class, 'studentClassRooms']);
        Route::get('student/exams', [SubjectExamController::class, 'studentExams']);
        Route::get('student/subjects/{subjectId}/exams', [SubjectExamController::class, 'getSubjectExams']);
        Route::get('student/exams/{examId}', [StudentExamController::class, 'show']);
        Route::post('student/exams/{examId}/start', [StudentExamController::class, 'startExam']);
        Route::post('student/exams/{examId}/questions/{questionId}/answer', [StudentExamController::class, 'submitAnswer']);
        Route::post('student/exams/{examId}/complete', [StudentExamController::class, 'completeExam']);
        Route::post('student/exams/{examId}/log-violation', [StudentExamController::class, 'logViolation']);
        Route::post('student/exams/{examId}/verify-supervisor-pin', [StudentExamController::class, 'verifySupervisorPin']);
        Route::post('student/exams/{examId}/heartbeat', [StudentExamController::class, 'heartbeat']);
        Route::get('student/exams/{examId}/progress', [StudentExamController::class, 'getProgress']);
        Route::get('student/exams/{examId}/results', [StudentExamController::class, 'getResults']);
        Route::get('student/results', [GradeController::class, 'index']);
        Route::get('student/classrooms/{classRoomId}/rapport/info', [ClassStudentController::class, 'getStudentRapportInfo']);
        Route::get('student/classrooms/{classRoomId}/rapport/download', [ClassStudentController::class, 'downloadStudentRapport']);
    });

    // PIC/Manager & Teacher routes
    Route::middleware('role:pic|manager|teacher')->group(function () {
        Route::get('exams/{id}/live-monitor', [ExamProctorController::class, 'getLiveStatus']);
        Route::get('subject-exams/{id}/live-monitor', [ExamProctorController::class, 'getLiveStatus']);
        Route::post('exams/{id}/students/{studentId}/force-submit', [ExamProctorController::class, 'forceSubmit']);
        Route::post('subject-exams/{id}/students/{studentId}/force-submit', [ExamProctorController::class, 'forceSubmit']);
        Route::post('exams/{id}/students/{studentId}/reset-session', [ExamProctorController::class, 'resetSession']);
        Route::post('subject-exams/{id}/students/{studentId}/reset-session', [ExamProctorController::class, 'resetSession']);
        Route::post('class-rooms/{classroomId}/activities', [ClassroomActivityController::class, 'store']);
        Route::put('class-rooms/{classroomId}/activities/{id}', [ClassroomActivityController::class, 'update']);
        Route::delete('class-rooms/{classroomId}/activities/{id}', [ClassroomActivityController::class, 'destroy']);
        Route::apiResource('projects', ProjectController::class);
        Route::get('grades', [GradeController::class, 'index']);
        Route::get('analytics/sales', [AnalyticsController::class, 'sales']);
        Route::get('analytics/performance', [AnalyticsController::class, 'performance']);
        Route::get('reports/summary', [ReportController::class, 'summary']);
        Route::get('reports/exams', [ReportController::class, 'exams']);
        Route::get('security/overview', [SecurityController::class, 'overview']);
        Route::post('security/settings', [SecurityController::class, 'updateSettings']);
        Route::get('statistics', [StatisticsController::class, 'index']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('topics', [TopicController::class, 'store']);
        Route::put('topics/{id}', [TopicController::class, 'update']);
        Route::delete('topics/{id}', [TopicController::class, 'destroy']);
        Route::post('lessons', [LessonController::class, 'store']);
        Route::post('lessons/{id}', [LessonController::class, 'update']);
        Route::put('lessons/{id}', [LessonController::class, 'update']);
        Route::delete('lessons/{id}', [LessonController::class, 'destroy']);
        Route::get('teachers', [TeacherController::class, 'index']);
        Route::get('teachers/search', [TeacherController::class, 'search']);
        Route::get('teachers/{id}', [TeacherController::class, 'show']);
        Route::put('subjects/{id}', [SubjectController::class, 'update']);
        Route::patch('subjects/{id}', [SubjectController::class, 'update']);
        Route::get('exam-attempts', [ExamAttemptController::class, 'index']);
        Route::get('exam-attempts/{id}', [ExamAttemptController::class, 'show']);
        Route::post('exam-attempts', [ExamAttemptController::class, 'store']);
        Route::put('exam-attempts/{id}', [ExamAttemptController::class, 'update']);
        Route::delete('exam-attempts/{id}', [ExamAttemptController::class, 'destroy']);
        Route::get('exam-attempts/statistics', [ExamAttemptController::class, 'statistics']);
        Route::get('students/{studentId}/exams/{examId}/attempt', [ExamAttemptController::class, 'getStudentAttempt']);
        Route::get('question-answers', [QuestionAnswerController::class, 'index']);
        Route::get('question-answers/{id}', [QuestionAnswerController::class, 'show']);
        Route::get('question-answers/needs-grading', [QuestionAnswerController::class, 'needsGrading']);
        Route::get('students/{studentId}/exams/{examId}/answers', [QuestionAnswerController::class, 'getStudentAnswers']);
        Route::get('exam-questions', [ExamQuestionController::class, 'index']);
        Route::get('exam-questions/search', [ExamQuestionController::class, 'search']);
        Route::get('exam-questions/{id}', [ExamQuestionController::class, 'show']);
        Route::post('exam-questions', [ExamQuestionController::class, 'store']);
        Route::put('exam-questions/{id}', [ExamQuestionController::class, 'update']);
        Route::delete('exam-questions/{id}', [ExamQuestionController::class, 'destroy']);
        Route::post('exam-questions/bulk-create', [ExamQuestionController::class, 'bulkStore']);
        Route::post('exam-questions/{id}/duplicate', [ExamQuestionController::class, 'duplicate']);
        Route::get('subject-exams/{subjectExamId}/questions', [ExamQuestionController::class, 'getBySubjectExam']);
        Route::get('subject-exams/{subjectExamId}/questions/statistics', [ExamQuestionController::class, 'statistics']);
        Route::get('subject-exams/{subjectExamId}/questions/for-grading', [ExamQuestionController::class, 'forGrading']);
        Route::get('question-options', [QuestionOptionController::class, 'index']);
        Route::get('question-options/search', [QuestionOptionController::class, 'search']);
        Route::get('question-options/{id}', [QuestionOptionController::class, 'show']);
        Route::post('question-options', [QuestionOptionController::class, 'store']);
        Route::put('question-options/{id}', [QuestionOptionController::class, 'update']);
        Route::delete('question-options/{id}', [QuestionOptionController::class, 'destroy']);
        Route::put('question-options/bulk-update', [QuestionOptionController::class, 'bulkUpdate']);
        Route::get('exam-questions/{examQuestionId}/options', [QuestionOptionController::class, 'getByExamQuestion']);
        Route::get('exam-questions/{examQuestionId}/options/correct', [QuestionOptionController::class, 'getCorrectOptions']);
        Route::get('exam-questions/{examQuestionId}/options/statistics', [QuestionOptionController::class, 'statistics']);
        Route::post('exam-questions/{examQuestionId}/options', [QuestionOptionController::class, 'bulkStore']);
        Route::post('exam-questions/{examQuestionId}/options/set-correct', [QuestionOptionController::class, 'setCorrectOption']);
        Route::post('exam-questions/{examQuestionId}/options/reorder', [QuestionOptionController::class, 'reorder']);
        Route::apiResource('subject-exams', SubjectExamController::class)->except(['show']);
    });

    // Teacher-only routes for grading
    Route::middleware('role:teacher')->group(function () {
        Route::post('question-answers/{id}/grade', [QuestionAnswerController::class, 'gradeAnswer']);
        Route::post('question-answers/bulk-grade', [QuestionAnswerController::class, 'bulkGrade']);
    });
});
