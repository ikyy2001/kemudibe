<?php

namespace Tests\Unit\Services\Ai;

use App\Exceptions\AiQuotaExceededException;
use App\Services\Ai\AiDailyQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiDailyQuotaTest extends TestCase
{
    protected AiDailyQuota $quotaService;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

        Config::set('ai.limits', [
            'guest'   => ['per_minute' => 5,  'per_day' => 2],
            'student' => ['per_minute' => 3,  'per_day' => 3],
            'teacher' => ['per_minute' => 10, 'per_day' => 5],
            'admin'   => ['per_minute' => 20, 'per_day' => null],
        ]);
        Config::set('ai.contact_whatsapp_url', 'https://wa.me/628123456789');

        $this->quotaService = app(AiDailyQuota::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        Cache::flush();
        parent::tearDown();
    }

    public function test_remaining_returns_correct_quota(): void
    {
        $this->assertEquals(2, $this->quotaService->remaining('guest', 'guest_ip_1'));
        $this->quotaService->consume('guest', 'guest_ip_1');
        $this->assertEquals(1, $this->quotaService->remaining('guest', 'guest_ip_1'));
        $this->quotaService->consume('guest', 'guest_ip_1');
        $this->assertEquals(0, $this->quotaService->remaining('guest', 'guest_ip_1'));
    }

    public function test_check_throws_exception_when_guest_quota_exhausted(): void
    {
        $this->quotaService->consume('guest', 'guest_ip_ex');
        $this->quotaService->consume('guest', 'guest_ip_ex');

        try {
            $this->quotaService->check('guest', 'guest_ip_ex');
            $this->fail('Expected AiQuotaExceededException was not thrown.');
        } catch (AiQuotaExceededException $e) {
            $this->assertEquals('guest', $e->getTier());
            $this->assertGreaterThan(0, $e->getRetryAfter());
            $this->assertStringContainsString('mencapai batas tanya jawab harian untuk pengunjung', $e->getMessage());

            $jsonResponse = $e->render();
            $this->assertEquals(429, $jsonResponse->status());
            $data = $jsonResponse->getData(true);
            $this->assertEquals('daily_quota', $data['code']);
            $this->assertEquals('https://wa.me/628123456789', $data['contact_url']);
        }
    }

    public function test_check_throws_exception_when_student_quota_exhausted(): void
    {
        $this->quotaService->consume('student', 'user_10');
        $this->quotaService->consume('student', 'user_10');
        $this->quotaService->consume('student', 'user_10');

        try {
            $this->quotaService->check('student', 'user_10');
            $this->fail('Expected AiQuotaExceededException was not thrown.');
        } catch (AiQuotaExceededException $e) {
            $this->assertEquals('student', $e->getTier());
            $this->assertStringContainsString('Batas pertanyaan AI harian Anda telah tercapai (3/3)', $e->getMessage());
            $this->assertStringContainsString('Guru atau Admin Kelas', $e->getMessage());
        }
    }

    public function test_check_throws_exception_when_teacher_quota_exhausted(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->quotaService->consume('teacher', 'user_20');
        }

        try {
            $this->quotaService->check('teacher', 'user_20');
            $this->fail('Expected AiQuotaExceededException was not thrown.');
        } catch (AiQuotaExceededException $e) {
            $this->assertEquals('teacher', $e->getTier());
            $this->assertStringContainsString('Batas pertanyaan AI harian Anda telah tercapai (5/5)', $e->getMessage());
            $this->assertStringNotContainsString('Guru atau Admin Kelas', $e->getMessage());
        }
    }

    public function test_admin_has_unlimited_quota(): void
    {
        $this->assertNull($this->quotaService->remaining('admin', 'user_admin'));

        // Consume does nothing for unlimited tier
        $count = $this->quotaService->consume('admin', 'user_admin');
        $this->assertEquals(0, $count);

        // Check never throws
        $this->quotaService->check('admin', 'user_admin');
        $this->assertTrue(true);
    }

    public function test_quota_resets_on_next_day(): void
    {
        $this->quotaService->consume('student', 'user_reset');
        $this->quotaService->consume('student', 'user_reset');
        $this->quotaService->consume('student', 'user_reset');

        $this->assertEquals(0, $this->quotaService->remaining('student', 'user_reset'));

        // Advance to next day 00:01:00 Asia/Jakarta
        Carbon::setTestNow(Carbon::parse('2026-10-17 00:01:00', 'Asia/Jakarta'));

        $this->assertEquals(3, $this->quotaService->remaining('student', 'user_reset'));
        $this->quotaService->check('student', 'user_reset');
        $this->assertTrue(true);
    }
}
