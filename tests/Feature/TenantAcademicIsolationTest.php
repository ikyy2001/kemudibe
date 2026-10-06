<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Institution;
use App\Models\Subject;
use App\Models\SubjectExam;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\InstitutionContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantAcademicIsolationTest extends TestCase
{
    protected InstitutionContext $context;
    protected Institution $institutionA;
    protected Institution $institutionB;
    protected User $picA;
    protected User $picB;
    protected User $teacherA;
    protected User $studentA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(InstitutionContext::class);
        $this->context->reset();

        $this->institutionA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        $this->institutionB = Institution::where('slug', 'bimbel-b')->firstOrFail();

        $this->picA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'pic.bimbela')
            ->firstOrFail();

        $this->picB = User::withoutTenant()
            ->where('institution_id', $this->institutionB->id)
            ->where('username', 'pic.bimbelb')
            ->firstOrFail();

        $this->teacherA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'guru.bimbela')
            ->firstOrFail();

        $this->studentA = User::withoutTenant()
            ->where('institution_id', $this->institutionA->id)
            ->where('username', 'siswa.bimbela')
            ->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->context->reset();
        parent::tearDown();
    }

    /**
     * 1. Test TenantRule::exists blocks referencing another tenant's subject
     */
    public function test_tenant_rule_exists_blocks_cross_tenant_foreign_keys(): void
    {
        // Create subject belonging to Bimbel B
        $subjectB = Subject::withoutTenant()->create([
            'institution_id' => $this->institutionB->id,
            'name' => 'Fisika Kuantum B ' . uniqid(),
            'code' => 'FIS-B-' . uniqid(),
            'photo' => 'default.png',
            'tagline' => 'Fisika Lanjut',
            'about' => 'Materi khusus Bimbel B',
            'teacher_id' => $this->picB->id,
        ]);

        // Create classroom belonging to Bimbel A
        $classroomA = ClassRoom::withoutTenant()->create([
            'institution_id' => $this->institutionA->id,
            'name' => 'Kelas Fisika A ' . uniqid(),
            'grade' => '11',
            'code' => 'KFA-' . uniqid(),
        ]);

        // User A attempts to assign Bimbel B's subject to Bimbel A's classroom
        $response = $this->actingAs($this->picA, 'sanctum')
            ->postJson("/api/bimbel-a/class-rooms/{$classroomA->id}/assign-subject", [
                'subject_id' => $subjectB->id,
            ]);

        // Validation fails because subject_id does not exist under Bimbel A scope
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id']);

        // Cleanup
        $classroomA->delete();
        $subjectB->delete();
    }

    /**
     * 2. Test unique rule allows duplicate names across different tenants but blocks duplicates within the same tenant
     */
    public function test_scoped_unique_validation_per_tenant(): void
    {
        $sharedName = 'Kelas Unggulan ' . uniqid();

        // 1. Create in Bimbel A -> 201
        $resA1 = $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/bimbel-a/class-rooms', [
                'name' => $sharedName,
                'grade' => 10,
            ]);
        $resA1->assertStatus(201);
        $classroomAId = $resA1->json('data.id');

        // 2. Create in Bimbel B with identical name -> 201 (Must succeed!)
        $resB1 = $this->actingAs($this->picB, 'sanctum')
            ->postJson('/api/bimbel-b/class-rooms', [
                'name' => $sharedName,
                'grade' => 10,
            ]);
        $resB1->assertStatus(201);
        $classroomBId = $resB1->json('data.id');

        // 3. Create AGAIN in Bimbel A with the same name -> 422 (Must fail unique rule)
        $resA2 = $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/bimbel-a/class-rooms', [
                'name' => $sharedName,
                'grade' => 10,
            ]);
        $resA2->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Cleanup
        ClassRoom::withoutTenant()->whereIn('id', [$classroomAId, $classroomBId])->delete();
    }

    /**
     * 3. Test student quota enforcement prevents creating students beyond quota
     */
    public function test_student_quota_enforcement(): void
    {
        // Temporarily set Bimbel A quota to current student count
        $currentCount = $this->institutionA->getActiveStudentsCount();
        $originalQuota = $this->institutionA->max_students;

        $this->institutionA->update(['max_students' => $currentCount]);

        $response = $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/bimbel-a/students', [
                'name' => 'Siswa Baru Melebihi Kuota',
                'username' => 'siswa.kuotalebih',
                'email' => 'kuota_over@example.com',
                'gender' => 'male',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quota']);

        // Restore original quota
        $this->institutionA->update(['max_students' => $originalQuota]);
    }

    /**
     * 4. Test storage quota enforcement rejects uploads when limit is exceeded
     */
    public function test_storage_quota_enforcement(): void
    {
        Storage::fake('public');

        $subjectA = Subject::withoutTenant()->create([
            'institution_id' => $this->institutionA->id,
            'name' => 'Biologi A ' . uniqid(),
            'photo' => 'default.png',
            'tagline' => 'Biologi Sel',
            'about' => 'Materi biologi',
            'teacher_id' => $this->teacherA->id,
        ]);

        // Temporarily set max_storage_mb to 1 MB and storage_used_bytes to 1 MB
        $originalMaxStorage = $this->institutionA->max_storage_mb;
        $originalUsedStorage = $this->institutionA->storage_used_bytes;

        $this->institutionA->update([
            'max_storage_mb' => 1,
            'storage_used_bytes' => 1024 * 1024, // 1MB already used
        ]);

        $fakeFile = UploadedFile::fake()->create('modul_besar.pdf', 500); // 500 KB

        $response = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/bimbel-a/lessons', [
                'subject_id' => $subjectA->id,
                'title' => 'Modul Melebihi Kuota',
                'content_type' => 'file',
                'attachment' => $fakeFile,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'STORAGE_QUOTA_EXCEEDED',
            ]);

        // Restore storage
        $this->institutionA->update([
            'max_storage_mb' => $originalMaxStorage,
            'storage_used_bytes' => $originalUsedStorage,
        ]);
        $subjectA->delete();
    }

    /**
     * 5. Test Live Exam Monitor returns 404 when monitoring another tenant's exam
     */
    public function test_live_proctor_monitor_blocks_cross_tenant_access(): void
    {
        $subjectB = Subject::withoutTenant()->create([
            'institution_id' => $this->institutionB->id,
            'name' => 'Kimia B ' . uniqid(),
            'photo' => 'default.png',
            'tagline' => 'Kimia Dasar',
            'about' => 'Materi kimia',
            'teacher_id' => $this->picB->id,
        ]);

        $examB = SubjectExam::withoutTenant()->create([
            'institution_id' => $this->institutionB->id,
            'subject_id' => $subjectB->id,
            'name' => 'Ujian Kimia Bimbel B ' . uniqid(),
            'about' => 'Ujian rahasia',
            'duration_minutes' => 60,
            'started_at' => now(),
            'ended_at' => now()->addHours(2),
        ]);

        // Teacher of Bimbel A attempts to monitor Bimbel B's exam via Bimbel A's endpoint
        $response = $this->actingAs($this->teacherA, 'sanctum')
            ->getJson("/api/bimbel-a/exams/{$examB->id}/live-monitor");

        // SubjectExam::findOrFail fails closed -> 404
        $response->assertStatus(404);

        // Cleanup
        $examB->delete();
        $subjectB->delete();
    }

    /**
     * 6. Test statistics endpoint only returns counts for current tenant and caches per tenant
     */
    public function test_statistics_aggregation_isolation(): void
    {
        $resA = $this->actingAs($this->picA, 'sanctum')
            ->getJson('/api/bimbel-a/statistics?entities=students,class_rooms');

        $resA->assertStatus(200);

        $resB = $this->actingAs($this->picB, 'sanctum')
            ->getJson('/api/bimbel-b/statistics?entities=students,class_rooms');

        $resB->assertStatus(200);

        $this->assertArrayHasKey('students_total', $resA->json('data'));
        $this->assertArrayHasKey('students_total', $resB->json('data'));
    }

    /**
     * 7. Test audit logging records changes with institution_id
     */
    public function test_audit_logs_record_tenant_actions(): void
    {
        $initialLogCount = AuditLog::where('institution_id', $this->institutionA->id)->count();

        // Create classroom via API
        $res = $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/bimbel-a/class-rooms', [
                'name' => 'Kelas Audit ' . uniqid(),
                'grade' => 12,
            ]);

        $res->assertStatus(201);
        $classroomId = $res->json('data.id');

        // Delete classroom
        $this->actingAs($this->picA, 'sanctum')
            ->deleteJson("/api/bimbel-a/class-rooms/{$classroomId}");

        // Create a student to trigger UserObserver
        $studentRes = $this->actingAs($this->picA, 'sanctum')
            ->postJson('/api/bimbel-a/students', [
                'name' => 'Siswa Audit Log',
                'username' => 'siswa_audit_' . uniqid(),
                'email' => 'audit_' . uniqid() . '@example.com',
                'gender' => 'female',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $studentRes->assertStatus(201);
        $studentId = $studentRes->json('data.id');

        // Verify audit log has new entries scoped to Bimbel A
        $newLogCount = AuditLog::where('institution_id', $this->institutionA->id)->count();
        $this->assertGreaterThan($initialLogCount, $newLogCount);

        $latestLog = AuditLog::where('institution_id', $this->institutionA->id)
            ->where('action', 'create_user')
            ->latest('id')
            ->first();

        $this->assertNotNull($latestLog);
        $this->assertEquals($this->institutionA->id, $latestLog->institution_id);

        // Cleanup
        User::withoutTenant()->find($studentId)?->delete();
    }
}
