<?php

namespace Tests\Feature\Ai;

use App\Models\Institution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiRateLimitingFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        Config::set('ai.base_url', 'https://router.bynara.id/v1');
        Config::set('ai.api_key', 'test-api-key');
        Config::set('ai.model', 'openai/gpt-4o-mini');
        Config::set('ai.max_tokens', 300);
        Config::set('ai.max_input_chars', 300);
        Config::set('ai.history_limit', 3);
        Config::set('ai.contact_whatsapp_url', 'https://wa.me/628123456789');

        Config::set('ai.limits', [
            'guest'   => ['per_minute' => 5,  'per_day' => 20],
            'student' => ['per_minute' => 3,  'per_day' => 30],
            'teacher' => ['per_minute' => 10, 'per_day' => 100],
            'admin'   => ['per_minute' => 20, 'per_day' => null],
        ]);

        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Jawaban AI',
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 20,
                ],
            ], 200),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        Cache::flush();
        parent::tearDown();
    }

    protected function getTestUser(string $role = 'student'): User
    {
        $institution = Institution::first();
        if (!$institution) {
            $institution = Institution::create([
                'name' => 'Test Institution',
                'slug' => 'test-inst',
                'status' => 'active',
            ]);
        }

        $user = User::withoutTenant()->where('username', 'test_rate_' . $role)->first();
        if (!$user) {
            $user = User::withoutTenant()->create([
                'institution_id' => $institution->id,
                'name' => 'Test ' . ucfirst($role),
                'username' => 'test_rate_' . $role,
                'email' => 'test_rate_' . $role . '@example.com',
                'password' => bcrypt('password123'),
                'gender' => 'male',
            ]);
        }

        if (!$user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_guest_per_minute_rate_limit_and_reset(): void
    {
        $ip = '10.20.30.40';

        // 5 requests allowed
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/ai/public/chat', ['message' => 'Pertanyaan ke-' . $i]);
            $response->assertStatus(200);
        }

        // 6th request must be throttled with 429
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Pertanyaan ke-6']);

        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertJson([
                'success' => false,
                'code' => 'rate_limit_minute',
            ]);

        $this->assertStringContainsString('Terlalu cepat. Silakan tunggu', $response->json('message'));

        // Advance 61 seconds -> should reset
        Carbon::setTestNow(Carbon::now('Asia/Jakarta')->addSeconds(61));

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Pertanyaan setelah reset']);
        $response->assertStatus(200);
    }

    public function test_student_per_minute_rate_limit(): void
    {
        $student = $this->getTestUser('student');

        // 3 requests allowed for student
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($student, 'sanctum')
                ->postJson('/api/ai/chat', ['message' => 'Pertanyaan siswa ' . $i]);
            $response->assertStatus(200);
        }

        // 4th request must be throttled with 429
        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => 'Pertanyaan siswa ke-4']);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'code' => 'rate_limit_minute',
            ]);
    }

    public function test_different_guest_ips_do_not_block_each_other(): void
    {
        $ip1 = '192.168.1.101';
        $ip2 = '192.168.1.102';

        // IP 1 uses all 5 per-minute attempts
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip1])
                ->postJson('/api/ai/public/chat', ['message' => 'Pesan ' . $i])
                ->assertStatus(200);
        }

        // IP 1 is now blocked
        $this->withServerVariables(['REMOTE_ADDR' => $ip1])
            ->postJson('/api/ai/public/chat', ['message' => 'Pesan blokir'])
            ->assertStatus(429);

        // IP 2 must NOT be blocked
        $this->withServerVariables(['REMOTE_ADDR' => $ip2])
            ->postJson('/api/ai/public/chat', ['message' => 'Pesan dari IP berbeda'])
            ->assertStatus(200);
    }

    public function test_different_guest_devices_on_same_ip_do_not_block_each_other(): void
    {
        $sameIp = '192.168.1.200';
        $deviceA = 'device-laptop-1111-2222';
        $deviceB = 'device-mobile-3333-4444';

        // Device A uses all 5 per-minute attempts on same IP
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $sameIp])
                ->withHeaders(['X-Guest-Device-Id' => $deviceA])
                ->postJson('/api/ai/public/chat', ['message' => 'Pesan Dev A ' . $i])
                ->assertStatus(200);
        }

        // Device A is throttled on 6th request
        $this->withServerVariables(['REMOTE_ADDR' => $sameIp])
            ->withHeaders(['X-Guest-Device-Id' => $deviceA])
            ->postJson('/api/ai/public/chat', ['message' => 'Pesan Dev A blokir'])
            ->assertStatus(429);

        // Device B on the EXACT SAME IP must NOT be blocked
        $this->withServerVariables(['REMOTE_ADDR' => $sameIp])
            ->withHeaders(['X-Guest-Device-Id' => $deviceB])
            ->postJson('/api/ai/public/chat', ['message' => 'Pesan Dev B sukses'])
            ->assertStatus(200);
    }

    public function test_double_submit_inflight_lock_returns_409(): void
    {
        $ip = '10.50.60.70';
        $identifier = sha1($ip);

        // Acquire in-flight lock externally to simulate ongoing request
        $lock = Cache::lock("ai:inflight:guest:{$identifier}", 35);
        $this->assertTrue($lock->get());

        try {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/ai/public/chat', [
                    'message' => 'Request bersamaan kedua',
                ]);

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'code' => 'request_in_progress',
                    'message' => 'Pertanyaan sebelumnya masih diproses.',
                ]);
        } finally {
            $lock->release();
        }

        // Once lock is released, request succeeds
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', [
                'message' => 'Request setelah lock bebas',
            ]);
        $response->assertStatus(200);
    }

    public function test_daily_quota_exhaustion_returns_429(): void
    {
        // Set guest daily limit to 2 for this test
        Config::set('ai.limits.guest.per_day', 2);
        $ip = '10.70.80.90';

        // 2 requests allowed
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Tanya 1'])
            ->assertStatus(200);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Tanya 2'])
            ->assertStatus(200);

        // 3rd request hits daily quota
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Tanya 3']);

        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson([
                'success' => false,
                'code' => 'daily_quota',
                'remaining' => 0,
                'contact_url' => 'https://wa.me/628123456789',
            ]);

        $this->assertStringContainsString('mencapai batas tanya jawab harian untuk pengunjung', $response->json('message'));
    }

    public function test_upstream_failure_does_not_consume_daily_quota(): void
    {
        Config::set('ai.limits.guest.per_day', 1);
        $ip = '10.99.99.1';

        $mockClient = $this->mock(\App\Services\Ai\NaraRouterClient::class);
        $mockClient->shouldReceive('chat')
            ->once()
            ->andThrow(new \App\Exceptions\AiServiceException('Server Error', \App\Exceptions\AiServiceException::CATEGORY_UPSTREAM_ERROR, 500));

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Tanya saat error']);

        $response->assertStatus(503);

        // Daily quota should NOT have been consumed (still 1 remaining)
        $quotaResponse = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/ai/public/quota');

        $quotaResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'daily_remaining' => 1,
                ],
            ]);

        // Next request with working upstream succeeds because quota is still available
        $mockClient->shouldReceive('chat')
            ->once()
            ->andReturn(new \App\DTOs\Ai\AiResponse('Sukses', 10, 10, 20, 'openai/gpt-4o-mini', 50));

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Tanya sukses'])
            ->assertStatus(200);
    }

    public function test_admin_has_unlimited_daily_quota(): void
    {
        $admin = $this->getTestUser('superadmin');

        // Can perform requests without daily quota depletion
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ai/quota');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'tier' => 'admin',
                    'daily_limit' => null,
                    'daily_remaining' => null,
                ],
            ]);
    }

    public function test_quota_info_endpoints(): void
    {
        // Public guest quota endpoint
        $response = $this->getJson('/api/ai/public/quota');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tier',
                    'per_minute_limit',
                    'daily_limit',
                    'daily_remaining',
                    'daily_resets_at',
                ],
            ]);

        // Authenticated quota endpoint
        $student = $this->getTestUser('student');
        $response = $this->actingAs($student, 'sanctum')->getJson('/api/ai/quota');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tier' => 'student',
                    'per_minute_limit' => 3,
                    'daily_limit' => 30,
                ],
            ]);
    }
}
