<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsageLog;
use App\Models\Institution;
use App\Models\User;
use App\Services\Ai\AiDailyQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCacheAndLoggingFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        DB::table('ai_usage_logs')->truncate();
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        Config::set('ai.base_url', 'https://router.bynara.id/v1');
        Config::set('ai.api_key', 'test-api-key');
        Config::set('ai.model', 'openai/gpt-4o-mini');
        Config::set('ai.max_tokens', 300);
        Config::set('ai.max_input_chars', 300);
        Config::set('ai.cache_ttl', 86400);

        Config::set('ai.limits', [
            'guest'   => ['per_minute' => 10, 'per_day' => 20],
            'student' => ['per_minute' => 10, 'per_day' => 30],
            'teacher' => ['per_minute' => 10, 'per_day' => 100],
            'admin'   => ['per_minute' => 20, 'per_day' => null],
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

        $user = User::withoutTenant()->where('username', 'test_cache_' . $role)->first();
        if (!$user) {
            $user = User::withoutTenant()->create([
                'institution_id' => $institution->id,
                'name' => 'Test ' . ucfirst($role),
                'username' => 'test_cache_' . $role,
                'email' => 'test_cache_' . $role . '@example.com',
                'password' => bcrypt('password123'),
                'gender' => 'male',
            ]);
        }

        if (!$user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_cache_hit_does_not_call_upstream_and_does_not_consume_daily_quota(): void
    {
        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Kemudi adalah sistem CBT dan LMS.']],
                ],
                'usage' => ['total_tokens' => 25],
            ], 200),
        ]);

        $quotaService = app(AiDailyQuota::class);
        $ip = '10.10.10.10';
        $identifier = sha1($ip);

        // First call: Cache miss -> calls upstream AI
        $res1 = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'Apa itu Kemudi?']);

        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'reply' => 'Kemudi adalah sistem CBT dan LMS.',
                    'cached' => false,
                ],
            ]);

        Http::assertSentCount(1);
        $this->assertEquals(19, $quotaService->remaining('guest', $identifier));

        // Clear recorded HTTP requests
        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::response([], 500),
        ]);

        // Second call with identical question (different case and trailing punctuation): Cache hit!
        $res2 = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/ai/public/chat', ['message' => 'apa itu kemudi?!']);

        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'reply' => 'Kemudi adalah sistem CBT dan LMS.',
                    'cached' => true,
                ],
            ]);

        // Assert no upstream call was made during the second request
        Http::assertSentCount(0);

        // Daily quota was NOT consumed by the cached response (still 19)
        $this->assertEquals(19, $quotaService->remaining('guest', $identifier));
    }

    public function test_non_empty_history_is_never_cached(): void
    {
        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Jawaban dinamis berdasarkan percakapan']],
                ],
            ], 200),
        ]);

        $history = [
            ['role' => 'user', 'content' => 'Halo'],
            ['role' => 'assistant', 'content' => 'Hai'],
        ];

        // Call 1
        $res1 = $this->postJson('/api/ai/public/chat', [
            'message' => 'Lanjut pertanyaan',
            'history' => $history,
        ]);
        $res1->assertStatus(200)->assertJson(['data' => ['cached' => false]]);

        // Call 2 with identical question and history: must NOT be cached because history is present
        $res2 = $this->postJson('/api/ai/public/chat', [
            'message' => 'Lanjut pertanyaan',
            'history' => $history,
        ]);
        $res2->assertStatus(200)->assertJson(['data' => ['cached' => false]]);

        Http::assertSentCount(2);
    }

    public function test_different_roles_do_not_share_cache(): void
    {
        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'Panduan siswa']]]], 200)
                ->push(['choices' => [['message' => ['content' => 'Panduan guru']]]], 200),
        ]);

        $student = $this->getTestUser('student');
        $teacher = $this->getTestUser('teacher');

        $question = 'Bagaimana cara melihat nilai?';

        // Student asks
        $resStudent = $this->actingAs($student, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => $question]);

        $resStudent->assertStatus(200)
            ->assertJson(['data' => ['reply' => 'Panduan siswa', 'cached' => false]]);

        // Teacher asks same question: MUST NOT hit student's cache because prompts differ per role
        $resTeacher = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => $question]);

        $resTeacher->assertStatus(200)
            ->assertJson(['data' => ['reply' => 'Panduan guru', 'cached' => false]]);

        Http::assertSentCount(2);
    }

    public function test_usage_log_recorded_properly_without_raw_question_and_raw_ip(): void
    {
        Http::fake([
            'https://router.bynara.id/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Jawaban log']]],
                'usage' => ['total_tokens' => 40],
            ], 200),
        ]);

        $rawIp = '203.0.113.195';
        $rawMessage = 'Pertanyaan rahasia pengguna yang sensitif';

        $response = $this->withServerVariables(['REMOTE_ADDR' => $rawIp])
            ->postJson('/api/ai/public/chat', ['message' => $rawMessage]);

        $response->assertStatus(200);

        // Verify database entry in ai_usage_logs
        $log = AiUsageLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('guest', $log->tier);
        $this->assertEquals('landing', $log->scope);
        $this->assertFalse($log->cache_hit);
        $this->assertEquals('ok', $log->status);
        $this->assertEquals(40, $log->total_tokens);

        // Verify IP is hashed with APP_KEY
        $expectedIpHash = hash_hmac('sha256', $rawIp, (string) config('app.key'));
        $this->assertEquals($expectedIpHash, $log->ip_hash);

        // Verify Question is hashed
        $normalized = app(\App\Services\Ai\AiResponseCache::class)->normalize($rawMessage);
        $expectedQuestionHash = hash('sha256', $normalized);
        $this->assertEquals($expectedQuestionHash, $log->question_hash);

        // CRITICAL CHECK: Verify raw question and raw IP are NEVER stored in DB
        $this->assertDatabaseMissing('ai_usage_logs', [
            'ip_hash' => $rawIp,
        ]);
        $this->assertDatabaseMissing('ai_usage_logs', [
            'question_hash' => $rawMessage,
        ]);
    }

    public function test_admin_usage_endpoint_success_for_admin_and_forbidden_for_non_admin(): void
    {
        $admin = $this->getTestUser('superadmin');
        $student = $this->getTestUser('student');

        // Create dummy logs
        AiUsageLog::create([
            'user_id' => null,
            'tier' => 'guest',
            'scope' => 'landing',
            'cache_hit' => true,
            'status' => 'ok',
            'total_tokens' => 50,
            'latency_ms' => 15,
            'ip_hash' => 'dummy_ip_hash',
            'question_hash' => 'dummy_q_hash',
            'created_at' => now(),
        ]);

        // Student attempt -> 403 Forbidden
        $resForbidden = $this->actingAs($student, 'sanctum')
            ->getJson('/api/ai/admin/usage');

        $resForbidden->assertStatus(403);

        // Admin attempt -> 200 OK with analytics data
        $resOk = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ai/admin/usage');

        $resOk->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overview' => [
                        'days',
                        'total_requests',
                        'cache_hits',
                        'cache_hit_rate',
                        'total_tokens',
                        'avg_latency_ms',
                        'rate_limited_count',
                    ],
                    'daily',
                    'top_questions',
                ],
            ]);

        $this->assertEquals(1, $resOk->json('data.overview.total_requests'));
        $this->assertEquals(1, $resOk->json('data.overview.cache_hits'));
    }

    public function test_artisan_usage_report_command_runs_successfully(): void
    {
        AiUsageLog::create([
            'user_id' => null,
            'tier' => 'guest',
            'scope' => 'landing',
            'cache_hit' => false,
            'status' => 'ok',
            'total_tokens' => 100,
            'latency_ms' => 200,
            'ip_hash' => 'hash_test',
            'question_hash' => 'hash_q_test',
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('ai:usage-report', ['--days' => 7]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('LAPORAN PENGGUNAAN AI ASSISTANT', $output);
        $this->assertStringContainsString('Total Request', $output);
        $this->assertStringContainsString('Total Token', $output);
    }
}
