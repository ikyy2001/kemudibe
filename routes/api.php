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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('token-login', [AuthController::class, 'tokenLogin']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
    Route::get('user/profile', [ProfileController::class, 'getProfile']);
    Route::match(['put', 'post'], 'user/profile', [ProfileController::class, 'updateProfile']);
    Route::put('user/password', [ProfileController::class, 'updatePassword']);
});


Route::middleware(['auth:sanctum', 'role:manager'])->group(function () {
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

    // Bulk Account Creation (Manager/Admin Only)
    Route::get('users/bulk-template', [UserController::class, 'downloadBulkTemplate']);
    Route::post('users/bulk-import', [UserController::class, 'bulkImport']);

    // Class Students (Enrollments)
    Route::get('class-students/search', [ClassStudentController::class, 'search']);
    Route::post('classrooms/{classRoomId}/bulk-enroll', [ClassStudentController::class, 'bulkEnroll']);
    Route::put('students/{studentId}/classrooms/{classRoomId}/status', [ClassStudentController::class, 'updateStatus']);

    // Rapport PDF Management (Manager only - upload/delete)
    Route::post('students/{studentId}/classrooms/{classRoomId}/rapport', [ClassStudentController::class, 'uploadRapport']);
    Route::delete('students/{studentId}/classrooms/{classRoomId}/rapport', [ClassStudentController::class, 'deleteRapport']);

    Route::apiResource('class-students', ClassStudentController::class);

    // Class Subjects (Subject-Classroom Assignments)
    Route::get('class-subjects/search', [ClassSubjectController::class, 'search']);
    Route::get('classrooms/{classRoomId}/available-subjects', [ClassSubjectController::class, 'availableSubjects']);
    Route::get('subjects/{subjectId}/available-classrooms', [ClassSubjectController::class, 'availableClassRooms']);
    Route::post('classrooms/{classRoomId}/bulk-assign-subjects', [ClassSubjectController::class, 'bulkAssignToClassRoom']);
    Route::apiResource('class-subjects', ClassSubjectController::class);

    // Subject Exams (Manager only operations)
    Route::get('subject-exams/search', [SubjectExamController::class, 'search']);
    Route::post('subject-exams/{id}/duplicate', [SubjectExamController::class, 'duplicate']);
});

// Shared Detail Routes - Accessible by Manager, Teacher, and Student (with access control)
Route::middleware(['auth:sanctum', 'role:manager|teacher|student'])->group(function () {
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

    // Rapport PDF viewing (All roles with role-based authorization in controller)
    Route::get('students/{studentId}/classrooms/{classRoomId}/rapport/info', [ClassStudentController::class, 'getRapportInfo']);
    Route::get('students/{studentId}/classrooms/{classRoomId}/rapport/download', [ClassStudentController::class, 'downloadRapport']);

    // Classroom Activities (Challenges, Homeworks, Others)
    Route::get('class-rooms/{classroomId}/activities', [ClassroomActivityController::class, 'index']);
    Route::get('class-rooms/{classroomId}/activities/{id}', [ClassroomActivityController::class, 'show']);
});

// Teacher-specific routes
Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
    // Teacher Dashboard - View assigned subjects
    Route::get('teacher/subjects', [SubjectController::class, 'teacherSubjects']);
    Route::get('teacher/subjects/search', [SubjectController::class, 'teacherSubjectsSearch']);

    // Teacher Profile
    Route::get('teacher/profile', [TeacherController::class, 'profile']);

    // Teacher's Subject Exams
    Route::get('teacher/subject-exams', [SubjectExamController::class, 'teacherExams']);
    Route::post('teacher/subject-exams', [SubjectExamController::class, 'createTeacherExam']);
    Route::put('teacher/subject-exams/{id}', [SubjectExamController::class, 'updateTeacherExam']);
    Route::delete('teacher/subject-exams/{id}', [SubjectExamController::class, 'deleteTeacherExam']);
    Route::get('teacher/subject-exams/{id}/answers', [SubjectExamController::class, 'getExamAnswers']);

    // Teacher Dashboard - Student Status and Individual Answers
    Route::get('teacher/exams/{id}/students', [SubjectExamController::class, 'getExamStudentsStatus']);
    Route::get('teacher/exams/{examId}/students/{studentId}', [SubjectExamController::class, 'getStudentExamDetails']);

    // Teacher's ClassRoom assignments via subjects
    Route::get('teacher/classrooms', [ClassSubjectController::class, 'teacherClassRooms']);

    // Teacher-scoped student profile access
    Route::get('teacher/students/{studentId}/profile', [StudentController::class, 'teacherStudentProfile']);

});

// Student-specific routes
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    // Student Dashboard & Profile
    Route::get('student/profile', [StudentController::class, 'profile']);
    Route::get('student/classrooms', [ClassStudentController::class, 'studentClassRooms']);

    // Student Exams - List and Overview
    Route::get('student/exams', [SubjectExamController::class, 'studentExams']);
    Route::get('student/subjects/{subjectId}/exams', [SubjectExamController::class, 'getSubjectExams']);

    // Student Exam Taking
    Route::get('student/exams/{examId}', [StudentExamController::class, 'show']);
    Route::post('student/exams/{examId}/start', [StudentExamController::class, 'startExam']);
    Route::post('student/exams/{examId}/questions/{questionId}/answer', [StudentExamController::class, 'submitAnswer']);
    Route::post('student/exams/{examId}/complete', [StudentExamController::class, 'completeExam']);
    Route::post('student/exams/{examId}/log-violation', [StudentExamController::class, 'logViolation']);
    Route::post('student/exams/{examId}/heartbeat', [StudentExamController::class, 'heartbeat']);
    Route::get('student/exams/{examId}/progress', [StudentExamController::class, 'getProgress']);
    Route::get('student/exams/{examId}/results', [StudentExamController::class, 'getResults']);
    Route::get('student/results', [GradeController::class, 'index']);

    // Student rapport endpoints (no need to pass studentId - gets from auth)
    Route::get('student/classrooms/{classRoomId}/rapport/info', [ClassStudentController::class, 'getStudentRapportInfo']);
    Route::get('student/classrooms/{classRoomId}/rapport/download', [ClassStudentController::class, 'downloadStudentRapport']);
});

// Manager & Teacher routes for managing exam attempts and answers
Route::middleware(['auth:sanctum', 'role:manager|teacher'])->group(function () {
    // Classroom Activities Management (Challenges, Homeworks, Others)
    Route::post('class-rooms/{classroomId}/activities', [ClassroomActivityController::class, 'store']);
    Route::put('class-rooms/{classroomId}/activities/{id}', [ClassroomActivityController::class, 'update']);
    Route::delete('class-rooms/{classroomId}/activities/{id}', [ClassroomActivityController::class, 'destroy']);

    // Projects Management
    Route::apiResource('projects', ProjectController::class);

    // Grades & Gradebook
    Route::get('grades', [GradeController::class, 'index']);

    // Analytics
    Route::get('analytics/sales', [AnalyticsController::class, 'sales']);
    Route::get('analytics/performance', [AnalyticsController::class, 'performance']);

    // Reports
    Route::get('reports/summary', [ReportController::class, 'summary']);
    Route::get('reports/exams', [ReportController::class, 'exams']);

    // System Security
    Route::get('security/overview', [SecurityController::class, 'overview']);
    Route::post('security/settings', [SecurityController::class, 'updateSettings']);

    // Statistics - Get counts for requested entities
    Route::get('statistics', [StatisticsController::class, 'index']);

    // Users - Get all users with filtering and pagination
    Route::get('users', [UserController::class, 'index']);

    // Topics - Read and Write access for both managers and teachers
    Route::post('topics', [TopicController::class, 'store']);
    Route::put('topics/{id}', [TopicController::class, 'update']);
    Route::delete('topics/{id}', [TopicController::class, 'destroy']);

    // Lessons - Management for both managers and teachers
    Route::post('lessons', [LessonController::class, 'store']);
    Route::post('lessons/{id}', [LessonController::class, 'update']); // for multipart/form-data upload
    Route::put('lessons/{id}', [LessonController::class, 'update']);
    Route::delete('lessons/{id}', [LessonController::class, 'destroy']);

    // Teachers - Read access for both managers and teachers
    Route::get('teachers', [TeacherController::class, 'index']);
    Route::get('teachers/search', [TeacherController::class, 'search']);
    Route::get('teachers/{id}', [TeacherController::class, 'show']);

    // Subject Management (with authorization checks in controller)
    Route::put('subjects/{id}', [SubjectController::class, 'update']);
    Route::patch('subjects/{id}', [SubjectController::class, 'update']);

    // Exam Attempts Management
    Route::get('exam-attempts', [ExamAttemptController::class, 'index']);
    Route::get('exam-attempts/{id}', [ExamAttemptController::class, 'show']);
    Route::post('exam-attempts', [ExamAttemptController::class, 'store']);
    Route::put('exam-attempts/{id}', [ExamAttemptController::class, 'update']);
    Route::delete('exam-attempts/{id}', [ExamAttemptController::class, 'destroy']);
    Route::get('exam-attempts/statistics', [ExamAttemptController::class, 'statistics']);
    Route::get('students/{studentId}/exams/{examId}/attempt', [ExamAttemptController::class, 'getStudentAttempt']);

    // Question Answers Management
    Route::get('question-answers', [QuestionAnswerController::class, 'index']);
    Route::get('question-answers/{id}', [QuestionAnswerController::class, 'show']);
    Route::get('question-answers/needs-grading', [QuestionAnswerController::class, 'needsGrading']);
    Route::get('students/{studentId}/exams/{examId}/answers', [QuestionAnswerController::class, 'getStudentAnswers']);

    // Exam Questions Management
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

    // Question Options Management
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

    // Subject Exams Management (Manager & Teacher)
    Route::apiResource('subject-exams', SubjectExamController::class)->except(['show']);
});

// Teacher-only routes for grading
Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
    // Answer Grading (Teachers only)
    Route::post('question-answers/{id}/grade', [QuestionAnswerController::class, 'gradeAnswer']);
    Route::post('question-answers/bulk-grade', [QuestionAnswerController::class, 'bulkGrade']);
});
