<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TenantAuthAndRoutingTest extends TestCase
{
    protected InstitutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(InstitutionContext::class);
        $this->context->reset();
        RateLimiter::clear('login:bimbel-a:pic.bimbela:127.0.0.1');
        RateLimiter::clear('login:bimbel-a:ratelimit_user:127.0.0.1');
        User::withoutTenant()->where('username', 'pic.bimbela')->update([
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->context->reset();
        parent::tearDown();
    }

    /**
     * 1. Test tenant login succeeds with valid institution_code, username, and password
     */
    public function test_tenant_login_success(): void
    {
        $response = $this->postJson('/api/login', [
            'institution_code' => 'bimbel-a',
            'username' => 'pic.bimbela',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'institution' => ['id', 'slug', 'name', 'status', 'is_read_only'],
                'user' => ['id', 'username', 'email', 'name', 'roles', 'institution'],
                'must_change_password',
            ]);

        $this->assertEquals('bimbel-a', $response->json('institution.slug'));
        $this->assertEquals('pic.bimbela', $response->json('user.username'));
    }

    /**
     * 2. Test tenant login fails generically when institution code is invalid
     */
    public function test_tenant_login_fails_with_invalid_institution_code(): void
    {
        $response = $this->postJson('/api/login', [
            'institution_code' => 'non-existent-institution-999',
            'username' => 'pic.bimbela',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    /**
     * 3. Test tenant login fails generically when password is wrong
     */
    public function test_tenant_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/login', [
            'institution_code' => 'bimbel-a',
            'username' => 'pic.bimbela',
            'password' => 'wrong-secret-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    /**
     * 4. Test Super Admin login succeeds
     */
    public function test_superadmin_login_success(): void
    {
        $response = $this->postJson('/api/superadmin/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'username', 'roles'],
            ]);

        $this->assertTrue(in_array('superadmin', $response->json('user.roles')));
    }

    /**
     * 5. Test rate limiting triggers after multiple failed attempts
     */
    public function test_login_rate_limiting(): void
    {
        $ip = '127.0.0.1';
        $throttleKey = "login:bimbel-a:ratelimit_user:{$ip}";
        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', [
                'institution_code' => 'bimbel-a',
                'username' => 'ratelimit_user',
                'password' => 'wrong',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'code' => 'TOO_MANY_ATTEMPTS',
            ]);

        RateLimiter::clear($throttleKey);
    }

    /**
     * 6. Test change password endpoint updates password and clears must_change_password
     */
    public function test_force_change_password(): void
    {
        $institutionA = Institution::where('slug', 'bimbel-a')->firstOrFail();

        $user = User::withoutTenant()->create([
            'institution_id' => $institutionA->id,
            'name' => 'Temp Test User',
            'username' => 'temp_pw_user',
            'email' => 'temp_pw@example.com',
            'password' => Hash::make('old_pass_123'),
            'photo' => 'default.png',
            'gender' => 'L',
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'old_pass_123',
                'password' => 'new_pass_456789',
                'password_confirmation' => 'new_pass_456789',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'must_change_password' => false,
            ]);

        $freshUser = User::withoutTenant()->find($user->id);
        $this->assertFalse((bool) $freshUser->must_change_password);
        $this->assertTrue(Hash::check('new_pass_456789', $freshUser->password));

        // Cleanup
        $freshUser->delete();
    }

    /**
     * 7. Test cross-tenant access returns 403 INSTITUTION_MISMATCH
     */
    public function test_cross_tenant_access_blocked_with_403(): void
    {
        $institutionA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        $userA = User::withoutTenant()
            ->where('institution_id', $institutionA->id)
            ->where('username', 'pic.bimbela')
            ->firstOrFail();

        // User A attempts to access Bimbel B route
        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/bimbel-b/class-rooms');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'INSTITUTION_MISMATCH',
            ]);
    }

    /**
     * 8. Test cross-tenant resource lookup returns 404
     */
    public function test_cross_tenant_resource_leak_returns_404(): void
    {
        $institutionA = Institution::where('slug', 'bimbel-a')->firstOrFail();
        $institutionB = Institution::where('slug', 'bimbel-b')->firstOrFail();

        $userA = User::withoutTenant()
            ->where('institution_id', $institutionA->id)
            ->where('username', 'pic.bimbela')
            ->firstOrFail();

        // Create a classroom explicitly in Bimbel B
        $classroomB = ClassRoom::withoutTenant()->create([
            'institution_id' => $institutionB->id,
            'name' => 'Kelas Rahasia Bimbel B ' . uniqid(),
            'grade' => '10',
            'code' => 'KB-TEST-' . uniqid(),
        ]);

        // User A requests Bimbel B's classroom through Bimbel A's URL endpoint
        $response = $this->actingAs($userA, 'sanctum')
            ->getJson("/api/bimbel-a/class-rooms/{$classroomB->id}");

        // Due to InstitutionScope, the query produces 0 rows for Bimbel B's ID under Bimbel A context -> 404
        $response->assertStatus(404);

        // Cleanup
        $classroomB->delete();
    }

    /**
     * 9. Test Super Admin can access any tenant route without 403
     */
    public function test_superadmin_can_access_any_tenant_route(): void
    {
        $superadmin = User::withoutTenant()
            ->whereNull('institution_id')
            ->where('username', 'superadmin')
            ->firstOrFail();

        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/bimbel-a/class-rooms');

        $response->assertStatus(200);
    }
}
