<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamViolation;
use App\Models\Institution;
use App\Models\QuestionAnswer;
use App\Models\SubjectExam;
use App\Models\User;
use App\Services\InstitutionContext;
use Tests\TestCase;

class ParallelInstitutionExamE2ETest extends TestCase
{
    protected InstitutionContext $context;
    protected Institution $institutionA;
    protected Institution $institutionB;
    protected User $studentA;
    protected User $studentB;
    protected User $teacherA;
    protected User $teacherB;
    protected SubjectExam $examA;
    protected SubjectExam $examB;
    protected ExamQuestion $questionA;
    protected ExamQuestion $questionB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(InstitutionContext::class);
        $this->context->reset();

        $this->institutionA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        $this->institutionB = Institution::where('slug', 'bimbel-b')->firstOrFail();

        $this->studentA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'siswa.bimbela')
            ->firstOrFail();

        $this->studentB = User::withoutTenant()
            ->where('institution_id', $this->institutionB->id)
            ->where('username', 'siswa.bimbelb')
            ->firstOrFail();

        $this->teacherA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'guru.bimbela')
            ->firstOrFail();

        $this->teacherB = User::withoutTenant()
            ->where('institution_id', $this->institutionB->id)
            ->where('username', 'guru.bimbelb')
            ->firstOrFail();

        $this->examA = SubjectExam::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->firstOrFail();

        $this->examB = SubjectExam::withoutTenant()
            ->where('institution_id', $this->institutionB->id)
            ->firstOrFail();

        $this->questionA = ExamQuestion::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('subject_exam_id', $this->examA->id)
            ->firstOrFail();

        $this->questionB = ExamQuestion::withoutTenant()
            ->where('institution_id', $this->institutionB->id)
            ->where('subject_exam_id', $this->examB->id)
            ->firstOrFail();

        // Ensure clean slate for attempts
        $this->cleanupAttempts();
    }

    protected function tearDown(): void
    {
        $this->cleanupAttempts();
        $this->context->reset();
        parent::tearDown();
    }

    private function cleanupAttempts(): void
    {
        $studentIds = [$this->studentA->id, $this->studentB->id];
        $attemptIds = ExamAttempt::withoutTenant()
            ->whereIn('student_id', $studentIds)
            ->pluck('id');

        if ($attemptIds->isNotEmpty()) {
            ExamViolation::withoutTenant()->whereIn('exam_attempt_id', $attemptIds)->delete();
            ExamAttempt::withoutTenant()->whereIn('id', $attemptIds)->delete();
        }
        QuestionAnswer::withoutTenant()->whereIn('student_id', $studentIds)->delete();
    }

    /**
     * 1. Test parallel students can start, answer, log violations, and complete exams concurrently.
     * All states, attempts, scores, and anti-cheat events must remain completely isolated.
     */
    public function test_parallel_students_can_start_and_take_exams_concurrently(): void
    {
        // === STEP 1: CONCURRENT START ===
        // Student A starts Exam A on Bimbel A
        $resStartA = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/start", [
                'token' => $this->examA->token,
            ]);
        $resStartA->assertStatus(200)
            ->assertJsonPath('success', true);
        $deviceTokenA = $resStartA->json('data.device_token');
        $this->assertNotEmpty($deviceTokenA);

        // Student B starts Exam B on Bimbel B concurrently
        $resStartB = $this->actingAs($this->studentB, 'sanctum')
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/start", [
                'token' => $this->examB->token,
            ]);
        $resStartB->assertStatus(200)
            ->assertJsonPath('success', true);
        $deviceTokenB = $resStartB->json('data.device_token');
        $this->assertNotEmpty($deviceTokenB);

        // Verify tokens are distinct
        $this->assertNotEquals($deviceTokenA, $deviceTokenB);

        // Verify both attempts exist with their respective institution_ids
        $attemptA = ExamAttempt::withoutTenant()
            ->where('student_id', $this->studentA->id)
            ->where('subject_exam_id', $this->examA->id)
            ->firstOrFail();
        $this->assertEquals($this->institutionA->id, $attemptA->institution_id);

        $attemptB = ExamAttempt::withoutTenant()
            ->where('student_id', $this->studentB->id)
            ->where('subject_exam_id', $this->examB->id)
            ->firstOrFail();
        $this->assertEquals($this->institutionB->id, $attemptB->institution_id);

        // === STEP 2: CONCURRENT ANSWER SUBMISSION ===
        // Student A submits answer to Question A
        $resAnswerA = $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenA])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/questions/{$this->questionA->id}/answer", [
                'answer_text' => '3',
            ]);
        $resAnswerA->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points_earned', 100);

        // Student B submits answer to Question B
        $resAnswerB = $this->actingAs($this->studentB, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenB])
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/questions/{$this->questionB->id}/answer", [
                'answer_text' => 'v = s / t',
            ]);
        $resAnswerB->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points_earned', 100);

        // Verify answers are strictly saved with tenant isolation
        $ansA = QuestionAnswer::withoutTenant()
            ->where('student_id', $this->studentA->id)
            ->where('exam_question_id', $this->questionA->id)
            ->firstOrFail();
        $this->assertEquals($this->institutionA->id, $ansA->institution_id);

        $ansB = QuestionAnswer::withoutTenant()
            ->where('student_id', $this->studentB->id)
            ->where('exam_question_id', $this->questionB->id)
            ->firstOrFail();
        $this->assertEquals($this->institutionB->id, $ansB->institution_id);

        // === STEP 3: CONCURRENT ANTI-CHEAT VIOLATION ISOLATION ===
        // Student A triggers a tab_switch violation
        $resViolA = $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenA])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/log-violation", [
                'violation_type' => 'tab_switch',
                'details' => 'Siswa membuka tab browser lain',
                'weight' => 1.0,
                'duration_seconds' => 4,
            ]);
        $resViolA->assertStatus(200);

        // Bimbel A attempt now has 1 violation; Bimbel B attempt MUST still have 0!
        $attemptA->refresh();
        $attemptB->refresh();
        $this->assertEquals(1, $attemptA->violation_count);
        $this->assertEquals(0, $attemptB->violation_count);

        // Student B triggers a fullscreen_exit violation
        $resViolB = $this->actingAs($this->studentB, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenB])
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/log-violation", [
                'violation_type' => 'fullscreen_exit',
                'details' => 'Siswa keluar dari layar penuh',
                'weight' => 1.5,
                'duration_seconds' => 8,
            ]);
        $resViolB->assertStatus(200);

        // Both attempts maintain their own independent violation counts
        $attemptA->refresh();
        $attemptB->refresh();
        $this->assertEquals(1, $attemptA->violation_count);
        $this->assertEquals(1, $attemptB->violation_count);

        // Verify violation records have corresponding institution_id
        $violRecordA = ExamViolation::withoutTenant()->where('exam_attempt_id', $attemptA->id)->firstOrFail();
        $this->assertEquals($this->institutionA->id, $violRecordA->institution_id);

        $violRecordB = ExamViolation::withoutTenant()->where('exam_attempt_id', $attemptB->id)->firstOrFail();
        $this->assertEquals($this->institutionB->id, $violRecordB->institution_id);

        // === STEP 4: CONCURRENT COMPLETION ===
        // Student A completes exam
        $resCompA = $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenA])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/complete");
        $resCompA->assertStatus(200)
            ->assertJsonPath('success', true);

        // Student B completes exam
        $resCompB = $this->actingAs($this->studentB, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenB])
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/complete");
        $resCompB->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify both attempts are completed with passing status
        $attemptA->refresh();
        $attemptB->refresh();
        $this->assertTrue($attemptA->is_completed);
        $this->assertTrue($attemptB->is_completed);
        $this->assertTrue($attemptA->has_passed);
        $this->assertTrue($attemptB->has_passed);
    }

    /**
     * 2. Test cross-tenant tampering and exam attacks are strictly blocked.
     */
    public function test_cross_tenant_exam_tampering_is_blocked(): void
    {
        // Attack 1: Student A attempts to access Bimbel B tenant endpoint
        $resAttack1 = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/start", [
                'token' => $this->examB->token,
            ]);
        $resAttack1->assertStatus(403)
            ->assertJson(['code' => 'INSTITUTION_MISMATCH']);

        // Attack 2: Student A tries to start Bimbel B's exam ID through Bimbel A's endpoint
        // Because of fail-closed tenant scoping, student has no access to examB -> 403 Forbidden
        $resAttack2 = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-a/student/exams/{$this->examB->id}/start", [
                'token' => $this->examB->token,
            ]);
        $resAttack2->assertStatus(403);

        // Attack 3: Student B attempts to start Bimbel A's exam ID through Bimbel B's endpoint -> 403 Forbidden
        $resAttack3 = $this->actingAs($this->studentB, 'sanctum')
            ->postJson("/api/bimbel-b/student/exams/{$this->examA->id}/start", [
                'token' => $this->examA->token,
            ]);
        $resAttack3->assertStatus(403);

        // Attack 4: Student B attempts to access Bimbel A route -> 403
        $resAttack4 = $this->actingAs($this->studentB, 'sanctum')
            ->getJson("/api/bimbel-a/student/exams");
        $resAttack4->assertStatus(403)
            ->assertJson(['code' => 'INSTITUTION_MISMATCH']);
    }

    /**
     * 3. Test proctor live monitoring isolation during active exams.
     * Proctor A can only see Student A and Proctor B can only see Student B.
     */
    public function test_proctor_monitor_isolation_during_parallel_exams(): void
    {
        // Start exam for Student A
        $resStartA = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/start", [
                'token' => $this->examA->token,
            ]);
        $deviceTokenA = $resStartA->json('data.device_token');

        // Start exam for Student B
        $resStartB = $this->actingAs($this->studentB, 'sanctum')
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/start", [
                'token' => $this->examB->token,
            ]);
        $deviceTokenB = $resStartB->json('data.device_token');

        // Teacher A monitors Exam A on Bimbel A
        $resMonA = $this->actingAs($this->teacherA, 'sanctum')
            ->getJson("/api/bimbel-a/exams/{$this->examA->id}/live-monitor");
        $resMonA->assertStatus(200);

        $studentsA = collect($resMonA->json('data.students') ?? []);
        $this->assertTrue($studentsA->contains('name', 'Ani Siswa A'));
        $this->assertFalse($studentsA->contains('name', 'Budi Siswa B'));

        // Teacher B monitors Exam B on Bimbel B
        $resMonB = $this->actingAs($this->teacherB, 'sanctum')
            ->getJson("/api/bimbel-b/exams/{$this->examB->id}/live-monitor");
        $resMonB->assertStatus(200);

        $studentsB = collect($resMonB->json('data.students') ?? []);
        $this->assertTrue($studentsB->contains('name', 'Budi Siswa B'));
        $this->assertFalse($studentsB->contains('name', 'Ani Siswa A'));

        // Teacher A tries to monitor Bimbel B's exam on Bimbel A's endpoint -> 404
        $resCross1 = $this->actingAs($this->teacherA, 'sanctum')
            ->getJson("/api/bimbel-a/exams/{$this->examB->id}/live-monitor");
        $resCross1->assertStatus(404);

        // Teacher A tries to monitor Bimbel B's exam on Bimbel B's endpoint -> 403
        $resCross2 = $this->actingAs($this->teacherA, 'sanctum')
            ->getJson("/api/bimbel-b/exams/{$this->examB->id}/live-monitor");
        $resCross2->assertStatus(403)
            ->assertJson(['code' => 'INSTITUTION_MISMATCH']);
    }

    /**
     * 4. Test leaderboard and achievements isolation post-exam.
     * Bimbel A leaderboard must only display Bimbel A students and vice versa.
     */
    public function test_leaderboard_and_grading_isolation_post_exam(): void
    {
        // 1. Complete exam for Student A with 100 points
        $resStartA = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/start", [
                'token' => $this->examA->token,
            ]);
        $deviceTokenA = $resStartA->json('data.device_token');

        $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenA])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/questions/{$this->questionA->id}/answer", [
                'answer_text' => '3',
            ]);

        $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenA])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/complete");

        // 2. Complete exam for Student B with 100 points
        $resStartB = $this->actingAs($this->studentB, 'sanctum')
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/start", [
                'token' => $this->examB->token,
            ]);
        $deviceTokenB = $resStartB->json('data.device_token');

        $this->actingAs($this->studentB, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenB])
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/questions/{$this->questionB->id}/answer", [
                'answer_text' => 'v = s / t',
            ]);

        $this->actingAs($this->studentB, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceTokenB])
            ->postJson("/api/bimbel-b/student/exams/{$this->examB->id}/complete");

        // 3. Query Bimbel A leaderboard
        $resLeadA = $this->actingAs($this->studentA, 'sanctum')
            ->getJson("/api/bimbel-a/achievements/leaderboard");
        $resLeadA->assertStatus(200);

        $leaderboardA = collect($resLeadA->json('data.leaderboard') ?? []);
        $this->assertTrue($leaderboardA->contains('name', 'Ani Siswa A'));
        $this->assertFalse($leaderboardA->contains('name', 'Budi Siswa B'));

        // 4. Query Bimbel B leaderboard
        $resLeadB = $this->actingAs($this->studentB, 'sanctum')
            ->getJson("/api/bimbel-b/achievements/leaderboard");
        $resLeadB->assertStatus(200);

        $leaderboardB = collect($resLeadB->json('data.leaderboard') ?? []);
        $this->assertTrue($leaderboardB->contains('name', 'Budi Siswa B'));
        $this->assertFalse($leaderboardB->contains('name', 'Ani Siswa A'));
    }

    /**
     * 5. Test concurrent device anti-cheat rejects mismatched session tokens across tenants.
     */
    public function test_concurrent_device_and_token_hijacking_rejection(): void
    {
        // Student A starts exam
        $resStartA = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/start", [
                'token' => $this->examA->token,
            ]);
        $resStartA->assertStatus(200);

        // Student A attempts to submit answer using a spoofed or different device token
        $resHijack = $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => 'invalid-or-stolen-token-uuid'])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/questions/{$this->questionA->id}/answer", [
                'answer_text' => '3',
            ]);

        $resHijack->assertStatus(409)
            ->assertJson([
                'code' => 'CONCURRENT_DEVICE_DETECTED',
            ]);
    }
}
