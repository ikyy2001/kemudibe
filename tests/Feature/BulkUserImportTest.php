<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkUserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(InstitutionContext::class)->reset();

        Role::firstOrCreate(['name' => 'pic', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        app(InstitutionContext::class)->reset();
        parent::tearDown();
    }

    public function test_bulk_import_students_with_and_without_emails_succeeds(): void
    {
        $institution = Institution::create([
            'name' => 'Demo Lembaga',
            'slug' => 'demo-lembaga',
            'status' => 'active',
            'max_students' => 100,
        ]);

        $pic = User::withoutTenant()->create([
            'institution_id' => $institution->id,
            'name' => 'PIC Demo',
            'username' => 'pic.demo',
            'email' => 'pic@demo.sch.id',
            'password' => bcrypt('password123'),
            'gender' => 'male',
        ]);
        $pic->assignRole('pic');

        $response = $this->actingAs($pic, 'sanctum')
            ->postJson('/api/demo-lembaga/users/bulk-import', [
                'default_password' => '',
                'users' => [
                    [
                        'row_number' => 2,
                        'name' => 'Siswa Satu',
                        'username' => 'siswa1',
                        'email' => 'siswa1@demo.sch.id',
                        'gender' => 'L',
                        'role' => 'student',
                    ],
                    [
                        'row_number' => 3,
                        'name' => 'Siswa Dua',
                        'username' => 'siswa2',
                        'email' => '',
                        'gender' => 'P',
                        'role' => 'student',
                    ],
                    [
                        'row_number' => 4,
                        'name' => 'Siswa Duplikat Email',
                        'username' => 'siswa3',
                        'email' => 'siswa1@demo.sch.id',
                        'gender' => 'L',
                        'role' => 'student',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.imported_count', 2)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertDatabaseHas('users', [
            'institution_id' => $institution->id,
            'username' => 'siswa1',
            'email' => 'siswa1@demo.sch.id',
        ]);
        $this->assertDatabaseHas('users', [
            'institution_id' => $institution->id,
            'username' => 'siswa2',
            'email' => null,
        ]);
    }
}
