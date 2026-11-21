<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Institution;
use App\Models\QuestionAnswer;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\SubjectExam;
use App\Models\Topic;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\StudentExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BugFixesRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Institution $instA;
    protected Institution $instB;
    protected User $picA;
    protected User $studentA;
    protected User $teacherA;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstitutionContext::class)->reset();

        foreach (['superadmin', 'pic', 'manager', 'teacher', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->instA = Institution::create([
            'name' => 'Demo Lembaga',
            'slug' => 'demo-lembaga',
            'status' => 'active',
            'max_students' => 100,
        ]);

        $this->instB = Institution::create([
            'name' => 'Lembaga Lain',
            'slug' => 'lembaga-lain',
            'status' => 'active',
            'max_students' => 100,
        ]);

        $this->picA = User::withoutTenant()->create([
            'institution_id' => $this->instA->id,
            'name' => 'PIC A',
            'username' => 'pic.a',
            'email' => 'pic.a@demo.id',
            'password' => bcrypt('password123'),
            'gender' => 'male',
        ]);
        $this->picA->assignRole('pic');

        $this->teacherA = User::withoutTenant()->create([
            'institution_id' => $this->instA->id,
            'name' => 'Teacher A',
            'username' => 'teacher.a',
            'email' => 'teacher.a@demo.id',
            'password' => bcrypt('password123'),
            'gender' => 'male',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->studentA = User::withoutTenant()->create([
            'institution_id' => $this->instA->id,
            'name' => 'Siswa Alpha',
            'username' => 'siswa.alpha',
            'email' => 'siswa.alpha@demo.id',
            'password' => bcrypt('password123'),
            'gender' => 'female',
        ]);
        $this->studentA->assignRole('student');
    }

    protected function tearDown(): void
    {
        app(InstitutionContext::class)->reset();
        parent::tearDown();
    }

    public function test_all_search_endpoints_succeed_without_500_on_empty_or_missing_q(): void
    {
        app(InstitutionContext::class)->set($this->instA);
        $topic = Topic::create(['name' => 'Matematika', 'about' => 'Pelajaran hitung']);
        $subject = Subject::create([
            'name' => 'Aljabar',
            'photo' => 'default.png',
            'tagline' => 'Dasar',
            'about' => 'Aljabar',
            'topic_id' => $topic->id,
            'teacher_id' => $this->teacherA->id,
            'passing_grade' => 75,
        ]);
        ClassRoom::create(['name' => 'Kelas 10-A', 'grade' => '10']);
        SubjectExam::create([
            'subject_id' => $subject->id,
            'name' => 'UTS Aljabar',
            'about' => 'Ujian Tengah Semester',
            'started_at' => now()->subHour(),
            'ended_at' => now()->addHour(),
            'total_points' => 100,
        ]);
        app(InstitutionContext::class)->reset();

        $endpoints = [
            '/api/demo-lembaga/students/search?page=1&limit=12',
            '/api/demo-lembaga/students/search?q=&page=1&limit=12',
            '/api/demo-lembaga/teachers/search?q=&page=1&limit=12',
            '/api/demo-lembaga/subjects/search?q=&page=1&limit=12',
            '/api/demo-lembaga/class-rooms/search?q=&page=1&limit=12',
            '/api/demo-lembaga/topics/search?q=&page=1&limit=12',
            '/api/demo-lembaga/subject-exams/search?q=&page=1&limit=12',
            '/api/demo-lembaga/class-students/search?q=&page=1&limit=12',
            '/api/demo-lembaga/class-subjects/search?q=&page=1&limit=12',
        ];

        foreach ($endpoints as $url) {
            $res = $this->actingAs($this->picA, 'sanctum')->getJson($url);
            $res->assertStatus(200, "Failed on {$url}: " . $res->getContent())
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['success', 'data', 'total', 'current_page', 'per_page', 'has_more']);
        }
    }

    public function test_search_or_where_does_not_leak_cross_tenant_records(): void
    {
        app(InstitutionContext::class)->set($this->instA);
        ClassRoom::create(['name' => 'Kelas A', 'grade' => '10']);
        Topic::create(['name' => 'Topik A', 'about' => 'Deskripsi A']);

        app(InstitutionContext::class)->set($this->instB);
        ClassRoom::create(['name' => 'Kelas Rahasia B', 'grade' => 'SECRET_GRADE_B']);
        Topic::create(['name' => 'Topik B', 'about' => 'SECRET_TOPIC_ABOUT_B']);
        app(InstitutionContext::class)->reset();

        $crRes = $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/class-rooms/search?q=SECRET_GRADE_B');
        $crRes->assertStatus(200)->assertJsonCount(0, 'data');

        $topicRes = $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/topics/search?q=SECRET_TOPIC_ABOUT_B');
        $topicRes->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_route_ordering_for_statistics_and_needs_grading_does_not_500(): void
    {
        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/exam-attempts/statistics')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/question-answers/needs-grading')
            ->assertStatus(200);
    }

    public function test_usage_blocked_for_student_and_schedule_recognizes_pic_role(): void
    {
        $this->actingAs($this->studentA, 'sanctum')
            ->getJson('/api/demo-lembaga/usage')
            ->assertStatus(403);

        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/usage')
            ->assertStatus(200);

        $scheduleRes = $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/schedule');
        $scheduleRes->assertStatus(200)
            ->assertJsonPath('data.is_unenrolled', false);
    }

    public function test_security_settings_and_audit_persist_to_institution(): void
    {
        $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/demo-lembaga/security/settings', [
                'two_factor_enforced' => true,
                'session_timeout_minutes' => 60,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.metrics.two_factor_enforced', true)
            ->assertJsonPath('data.metrics.session_timeout_minutes', 60);

        $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/demo-lembaga/security/audit')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/security/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.metrics.two_factor_enforced', true)
            ->assertJsonPath('data.metrics.session_timeout_minutes', 60);
    }

    public function test_resume_exam_and_retake_reset_do_not_crash_on_exam_attempt_id_and_export_csv_works(): void
    {
        app(InstitutionContext::class)->set($this->instA);
        $topic = Topic::create(['name' => 'Fisika', 'about' => 'Fisika']);
        $subject = Subject::create([
            'name' => 'Mekanika',
            'photo' => 'default.png',
            'tagline' => 'Gerak',
            'about' => 'Mekanika',
            'topic_id' => $topic->id,
            'teacher_id' => $this->teacherA->id,
            'passing_grade' => 70,
        ]);
        $classroom = ClassRoom::create(['name' => '10 IPA', 'grade' => '10']);
        $classroom->classStudents()->create(['student_id' => $this->studentA->id, 'has_passed' => false]);
        $classroom->classSubjects()->create(['subject_id' => $subject->id]);

        $exam = SubjectExam::create([
            'subject_id' => $subject->id,
            'name' => 'Kuis Mekanika',
            'about' => 'Kuis',
            'started_at' => now()->subDay(),
            'ended_at' => now()->addDay(),
            'total_points' => 10,
        ]);
        $q = ExamQuestion::create([
            'subject_exam_id' => $exam->id,
            'name' => '2 + 2 = ?',
            'type' => 'single_choice',
            'points' => 10,
            'timer' => 0,
        ]);
        $opt = QuestionOption::create([
            'exam_question_id' => $q->id,
            'name' => '4',
            'is_correct' => true,
        ]);

        // Initial start
        $start1 = app(StudentExamService::class)->startExam($this->studentA->id, $exam->id);
        $attemptId = $start1['attempt']->id;

        // Record an answer and a violation
        QuestionAnswer::create([
            'exam_question_id' => $q->id,
            'student_id' => $this->studentA->id,
            'answer_text' => '4',
            'answer_data' => ['selected' => [$opt->id]],
            'has_passed' => true,
            'result_status' => 'correct',
            'points_earned' => 10,
        ]);
        ExamAttempt::where('id', $attemptId)->update(['violation_score' => 1.5, 'violation_count' => 2]);

        // Resuming exam should NOT throw SQL error and MUST preserve violation_score
        $resumed = app(StudentExamService::class)->startExam($this->studentA->id, $exam->id);
        $this->assertEquals(1.5, (float) $resumed['attempt']->violation_score);
        $this->assertEquals(1, $resumed['attempt']->answered_questions);

        // Grade filter (Stringable fix)
        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/grades?status=passed')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Export CSV
        $exportRes = $this->actingAs($this->picA, 'sanctum')
            ->get("/api/demo-lembaga/subject-exams/{$exam->id}/export-grades");
        $exportRes->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $exportRes->headers->get('Content-Type'));

        // Reset session with retake_from_scratch should NOT throw SQL error and MUST delete answers
        $this->actingAs($this->picA, 'sanctum')
            ->postJson("/api/demo-lembaga/subject-exams/{$exam->id}/students/{$this->studentA->id}/reset-session", [
                'retake_from_scratch' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('question_answers', 0);

        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/exam-questions/search?q=2')
            ->assertStatus(200);

        $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/demo-lembaga/question-options/search?q=4')
            ->assertStatus(200);
    }
}
