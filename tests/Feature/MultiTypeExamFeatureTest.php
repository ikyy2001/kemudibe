<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Institution;
use App\Models\QuestionAnswer;
use App\Models\SubjectExam;
use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiTypeExamFeatureTest extends TestCase
{
    protected InstitutionContext $context;
    protected Institution $institutionA;
    protected User $teacherA;
    protected User $studentA;
    protected SubjectExam $examA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(InstitutionContext::class);
        $this->context->reset();

        $this->institutionA = Institution::where('slug', 'bimbel-a')->firstOrFail();

        $this->teacherA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'guru.bimbela')
            ->firstOrFail();

        $this->studentA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'siswa.bimbela')
            ->firstOrFail();

        $this->examA = SubjectExam::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->firstOrFail();

        $this->examA->update([
            'started_at' => now()->subHours(2),
            'ended_at' => now()->addHours(48),
        ]);

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
        $attemptIds = ExamAttempt::withoutTenant()->where('student_id', $this->studentA->id)->pluck('id');
        if ($attemptIds->isNotEmpty()) {
            \App\Models\ExamViolation::withoutTenant()->whereIn('exam_attempt_id', $attemptIds)->delete();
            ExamAttempt::withoutTenant()->whereIn('id', $attemptIds)->delete();
        }
        QuestionAnswer::withoutTenant()->where('student_id', $this->studentA->id)->delete();
    }

    public function test_can_create_new_question_types_via_api(): void
    {
        // Create an isolated test exam with 0 points
        $customExam = SubjectExam::withoutTenant()->create([
            'institution_id' => $this->institutionA->id,
            'subject_id' => $this->examA->subject_id,
            'name' => 'Ujian Tipe Campuran API',
            'about' => 'Ujian untuk pengujian fitur',
            'started_at' => now()->subHour(),
            'ended_at' => now()->addHours(24),
            'duration' => 60,
            'token' => 'CAMPUR1',
            'total_points' => 0,
        ]);

        // 1. Create Multiple Answer Question
        $resMA = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/bimbel-a/exam-questions', [
                'subject_exam_id' => $customExam->id,
                'name' => '<p>Manakah pernyataan yang benar mengenai fotosintesis?</p>',
                'type' => 'multiple_answer',
                'category' => 'Biologi Terapan',
                'shuffle_options' => true,
                'points' => 10,
                'timer' => 120,
                'options' => [
                    ['name' => 'Menghasilkan oksigen', 'is_correct' => true],
                    ['name' => 'Terjadi di kloroplas', 'is_correct' => true],
                    ['name' => 'Menghasilkan karbondioksida', 'is_correct' => false],
                ],
            ]);

        $resMA->assertStatus(201)
            ->assertJsonPath('data.type', 'multiple_answer')
            ->assertJsonPath('data.category', 'Biologi Terapan');

        $qId = $resMA->json('data.id');
        $this->assertNotNull($qId);

        // 2. Create True/False Table Question
        $resTF = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/bimbel-a/exam-questions', [
                'subject_exam_id' => $customExam->id,
                'name' => '<p>Tentukan Benar atau Salah untuk tiap pernyataan berikut:</p>',
                'type' => 'true_false_table',
                'category' => 'Logika',
                'shuffle_options' => false,
                'points' => 10,
                'timer' => 180,
                'options' => [
                    ['name' => 'Bumi mengelilingi matahari', 'is_correct' => true],
                    ['name' => 'Bulan memancarkan cahayanya sendiri', 'is_correct' => false],
                    ['name' => 'Air membeku pada 0 derajat Celcius', 'is_correct' => true],
                ],
            ]);

        $resTF->assertStatus(201)
            ->assertJsonPath('data.type', 'true_false_table')
            ->assertJsonPath('data.shuffle_options', false);

        // Clean up created questions and exam
        ExamQuestion::withoutTenant()->where('subject_exam_id', $customExam->id)->delete();
        $customExam->delete();
    }

    public function test_student_submitting_multi_type_answers_gets_partial_scores(): void
    {
        // Create a test question with multiple_answer (6 points, 3 correct out of 4)
        $qComplex = ExamQuestion::withoutTenant()->create([
            'institution_id' => $this->institutionA->id,
            'subject_exam_id' => $this->examA->id,
            'name' => '<p>Pilih semua bilangan prima di bawah ini:</p>',
            'type' => 'multiple_answer',
            'points' => 6,
            'timer' => 120,
            'shuffle_options' => false,
        ]);

        $opt1 = $qComplex->questionOptions()->create(['institution_id' => $this->institutionA->id, 'name' => '2', 'is_correct' => true, 'sort_order' => 0]);
        $opt2 = $qComplex->questionOptions()->create(['institution_id' => $this->institutionA->id, 'name' => '3', 'is_correct' => true, 'sort_order' => 1]);
        $opt3 = $qComplex->questionOptions()->create(['institution_id' => $this->institutionA->id, 'name' => '4', 'is_correct' => false, 'sort_order' => 2]);
        $opt4 = $qComplex->questionOptions()->create(['institution_id' => $this->institutionA->id, 'name' => '5', 'is_correct' => true, 'sort_order' => 3]);

        // Start exam
        $resStart = $this->actingAs($this->studentA, 'sanctum')
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/start", [
                'token' => $this->examA->token,
            ]);
        $resStart->assertStatus(200);
        $deviceToken = $resStart->json('data.device_token');

        // Student submits 2 correct options [2, 3] and 0 wrong options:
        // Score = (2 / 3) * 6 = 4.0 points
        $resAnswer = $this->actingAs($this->studentA, 'sanctum')
            ->withHeaders(['X-Device-Token' => $deviceToken])
            ->postJson("/api/bimbel-a/student/exams/{$this->examA->id}/questions/{$qComplex->id}/answer", [
                'answer_data' => ['selected' => [$opt1->id, $opt2->id]],
            ]);

        $resAnswer->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer.result_status', 'partial');

        $this->assertEquals(4.0, (float)$resAnswer->json('data.points_earned'));

        // Verify stored in DB
        $saved = QuestionAnswer::withoutTenant()
            ->where('exam_question_id', $qComplex->id)
            ->where('student_id', $this->studentA->id)
            ->firstOrFail();

        $this->assertEquals(4.0, (float)$saved->points_earned);
        $this->assertEquals('partial', $saved->result_status);
        $this->assertEquals([$opt1->id, $opt2->id], $saved->answer_data['selected']);

        // Clean up
        $saved->delete();
        $qComplex->questionOptions()->delete();
        $qComplex->delete();
    }

    public function test_question_image_upload_and_quota_enforcement(): void
    {
        Storage::fake('public');

        // Upload normal image
        $file = UploadedFile::fake()->image('diagram_matematika.png', 600, 400);

        $resUpload = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/bimbel-a/question-images', [
                'file' => $file,
            ]);

        $resUpload->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['location', 'url', 'size']);

        $location = $resUpload->json('location');
        $this->assertNotEmpty($location);

        // Test quota limit
        $originalStorage = $this->institutionA->max_storage_mb;
        $this->institutionA->update([
            'max_storage_mb' => 1,
            'storage_used_bytes' => 1024 * 1024 + 100, // already exceeded
        ]);

        $resQuota = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/bimbel-a/question-images', [
                'file' => UploadedFile::fake()->image('exceed.png', 100, 100),
            ]);

        $resQuota->assertStatus(422);

        // Restore
        $this->institutionA->update([
            'max_storage_mb' => $originalStorage,
            'storage_used_bytes' => 0,
        ]);
    }
}
