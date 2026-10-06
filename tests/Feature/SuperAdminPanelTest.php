<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminPanelTest extends TestCase
{
    protected InstitutionContext $context;
    protected User $superadmin;
    protected User $regularPic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(InstitutionContext::class);
        $this->context->reset();

        $this->superadmin = User::withoutTenant()
            ->where('username', 'superadmin')
            ->firstOrFail();

        $this->regularPic = User::withoutTenant()
            ->where('username', 'pic.bimbela')
            ->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->context->reset();
        parent::tearDown();
    }

    /**
     * 1. Overview metrics endpoint
     */
    public function test_superadmin_can_view_platform_overview(): void
    {
        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson('/api/superadmin/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'institutions' => ['total', 'active', 'suspended', 'expired'],
                    'users' => ['students', 'teachers', 'pics', 'total'],
                    'academic' => ['classrooms', 'subjects', 'exams', 'questions', 'exam_attempts'],
                    'storage' => ['used_bytes', 'used_mb', 'total_capacity_mb', 'percentage'],
                    'recent_institutions',
                    'recent_audit_logs',
                ]
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('data.institutions.total'));
    }

    /**
     * 2. Non-superadmin cannot access superadmin routes (403)
     */
    public function test_non_superadmin_cannot_access_superadmin_routes(): void
    {
        $response = $this->actingAs($this->regularPic, 'sanctum')
            ->getJson('/api/superadmin/overview');

        $response->assertStatus(403);

        $response2 = $this->actingAs($this->regularPic, 'sanctum')
            ->getJson('/api/superadmin/institutions');

        $response2->assertStatus(403);
    }

    /**
     * 3. Unauthenticated cannot access superadmin routes (401)
     */
    public function test_unauthenticated_cannot_access_superadmin_routes(): void
    {
        $response = $this->getJson('/api/superadmin/overview');
        $response->assertStatus(401);
    }

    /**
     * 4. Superadmin can list institutions
     */
    public function test_superadmin_can_list_and_filter_institutions(): void
    {
        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson('/api/superadmin/institutions?search=Bimbel');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'status', 'max_students',
                        'active_students', 'max_storage_mb', 'storage_used_mb',
                        'is_read_only', 'pic'
                    ]
                ],
                'current_page',
                'total'
            ]);
    }

    /**
     * 5. Superadmin can create institution with initial PIC
     */
    public function test_superadmin_can_create_institution_with_pic(): void
    {
        $slug = 'test-inst-' . time();
        $payload = [
            'name' => 'Lembaga Uji Coba Baru',
            'slug' => $slug,
            'contact_name' => 'Bapak Kepala',
            'contact_phone' => '08123456789',
            'contact_email' => 'kepala@ujicoba.com',
            'max_students' => 150,
            'max_storage_mb' => 2048,
            'status' => 'active',
            'pic_name' => 'Penanggung Jawab Uji Coba',
            'pic_username' => 'pic.' . substr($slug, -8),
            'pic_email' => 'pic@ujicoba.com',
            'pic_password' => 'secret12345',
        ];

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->postJson('/api/superadmin/institutions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.institution.name', 'Lembaga Uji Coba Baru')
            ->assertJsonPath('data.pic.username', 'pic.' . substr($slug, -8));

        $institutionId = $response->json('data.institution.id');
        $this->assertDatabaseHas('institutions', ['id' => $institutionId, 'slug' => $slug]);
        $this->assertDatabaseHas('users', ['institution_id' => $institutionId, 'username' => 'pic.' . substr($slug, -8)]);

        // Verify audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'institution_id' => $institutionId,
            'action' => 'create_institution',
        ]);
    }

    /**
     * 6. Creation fails if slug is reserved
     */
    public function test_cannot_create_institution_with_reserved_slug(): void
    {
        $payload = [
            'name' => 'Lembaga Dashboard',
            'slug' => 'dashboard', // Reserved!
            'pic_name' => 'PIC Dashboard',
            'pic_username' => 'pic.dashboard',
            'pic_password' => 'secret12345',
        ];

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->postJson('/api/superadmin/institutions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * 7. Show single institution detail
     */
    public function test_superadmin_can_show_institution_detail(): void
    {
        $inst = Institution::where('slug', 'bimbel-a')->firstOrFail();

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson("/api/superadmin/institutions/{$inst->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'bimbel-a')
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'status', 'max_students', 'active_students',
                    'active_teachers', 'classrooms_count', 'subjects_count', 'exams_count',
                    'pics', 'recent_logs',
                ]
            ]);
    }

    /**
     * 8. Update institution and quotas
     */
    public function test_superadmin_can_update_institution_and_quotas(): void
    {
        $testInst = Institution::create([
            'name' => 'Lembaga Update Test',
            'slug' => 'test-upd-' . time(),
            'status' => 'active',
            'max_students' => 100,
            'max_storage_mb' => 1024,
        ]);

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->putJson("/api/superadmin/institutions/{$testInst->id}", [
                'name' => 'Lembaga Update Test (Updated)',
                'max_students' => 300,
                'max_storage_mb' => 4096,
                'status' => 'active',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.max_students', 300)
            ->assertJsonPath('data.max_storage_mb', 4096);

        $testInst->refresh();
        $this->assertEquals(300, $testInst->max_students);
        $this->assertEquals(4096, $testInst->max_storage_mb);
    }

    /**
     * 9. Reset PIC password
     */
    public function test_superadmin_can_reset_pic_password(): void
    {
        $testInst = Institution::create([
            'name' => 'Lembaga Reset Password Test',
            'slug' => 'test-reset-' . time(),
            'status' => 'active',
            'max_students' => 50,
            'max_storage_mb' => 500,
        ]);
        $testPic = User::create([
            'institution_id' => $testInst->id,
            'name' => 'PIC Reset Test',
            'username' => 'pic.reset.' . time(),
            'password' => Hash::make('initialpassword'),
            'gender' => 'male',
        ]);
        $testPic->assignRole('pic');

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->postJson("/api/superadmin/institutions/{$testInst->id}/reset-pic-password", [
                'password' => 'newpassword123',
                'must_change_password' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.plain_password', 'newpassword123')
            ->assertJsonPath('data.must_change_password', true);

        $testPic->refresh();
        $this->assertTrue(Hash::check('newpassword123', $testPic->password));
        $this->assertTrue($testPic->must_change_password);
    }

    /**
     * 10. Soft delete institution
     */
    public function test_superadmin_can_delete_institution(): void
    {
        $inst = Institution::create([
            'name' => 'Lembaga To Delete',
            'slug' => 'to-delete-' . time(),
            'status' => 'active',
            'max_students' => 10,
            'max_storage_mb' => 100,
        ]);

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->deleteJson("/api/superadmin/institutions/{$inst->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('institutions', ['id' => $inst->id]);
    }

    /**
     * 11. View platform audit logs
     */
    public function test_superadmin_can_view_audit_logs(): void
    {
        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson('/api/superadmin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'action', 'entity_type', 'created_at',
                        'institution', 'user',
                    ]
                ],
                'current_page',
                'total'
            ]);
    }
}
