<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\StudentExamService;
use App\Models\ExamAttempt;
use App\Repositories\StudentExamRepository;
use App\Repositories\SubjectExamRepository;
use App\Repositories\ExamAttemptRepository;
use App\Repositories\QuestionAnswerRepository;
use App\Repositories\ExamQuestionRepository;
use Illuminate\Support\Facades\Cache;

class SupervisorPinSecurityTest extends TestCase
{
    protected StudentExamService $service;
    protected $studentExamRepoMock;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->studentExamRepoMock = $this->createMock(StudentExamRepository::class);

        $this->service = new StudentExamService(
            $this->studentExamRepoMock,
            $this->createMock(SubjectExamRepository::class),
            $this->createMock(ExamAttemptRepository::class),
            $this->createMock(QuestionAnswerRepository::class),
            $this->createMock(ExamQuestionRepository::class)
        );
    }

    public function test_get_supervisor_pin_generates_valid_6_digit_pin_and_seconds_remaining(): void
    {
        $examId = 42;
        $pinData = $this->service->getSupervisorPin($examId);

        $this->assertArrayHasKey('pin', $pinData);
        $this->assertArrayHasKey('seconds_remaining', $pinData);
        $this->assertEquals(6, strlen($pinData['pin']));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pinData['pin']);
        $this->assertGreaterThanOrEqual(1, $pinData['seconds_remaining']);
        $this->assertLessThanOrEqual(60, $pinData['seconds_remaining']);
    }

    public function test_pin_is_deterministic_for_same_window_and_exam(): void
    {
        $examId = 99;
        $pin1 = $this->service->getSupervisorPin($examId);
        $pin2 = $this->service->getSupervisorPin($examId);

        $this->assertEquals($pin1['pin'], $pin2['pin']);
    }

    public function test_different_exam_ids_produce_different_pins(): void
    {
        $pinA = $this->service->getSupervisorPin(101);
        $pinB = $this->service->getSupervisorPin(102);

        $this->assertNotEquals($pinA['pin'], $pinB['pin']);
    }

    public function test_invalid_short_pin_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PIN verifikasi harus berupa 6 digit angka yang valid.');

        $this->service->verifySupervisorPin(1, 10, '12');
    }

    public function test_wrong_pin_fails_and_increments_rate_limiter(): void
    {
        $studentId = 5;
        $examId = 77;
        $wrongPin = '000000';

        try {
            $this->service->verifySupervisorPin($studentId, $examId, $wrongPin);
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('PIN verifikasi salah atau telah kedaluwarsa', $e->getMessage());
        }

        $attempts = Cache::get("exam_pin_failed_{$studentId}_{$examId}");
        $this->assertEquals(1, $attempts);
    }

    public function test_rate_limiter_locks_out_after_five_failed_attempts(): void
    {
        $studentId = 7;
        $examId = 88;
        $rateLimitKey = "exam_pin_failed_{$studentId}_{$examId}";
        Cache::put($rateLimitKey, 5, 60);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Terlalu banyak percobaan PIN salah. Fitur input PIN terkunci sementara.');

        $this->service->verifySupervisorPin($studentId, $examId, '123456');
    }

    public function test_previous_window_pin_is_accepted_for_grace_window(): void
    {
        $examId = 55;
        $secret = config('app.key') . '_supervisor_pin_' . $examId;
        $prevWindow = (int)floor(time() / 60) - 1;
        $hash = hash_hmac('sha256', (string)$prevWindow, $secret);
        $prevPin = str_pad((string)(hexdec(substr($hash, 0, 8)) % 900000 + 100000), 6, '0', STR_PAD_LEFT);

        // Mock repo returning null attempt to verify PIN was accepted and attempt search occurred
        $this->studentExamRepoMock->expects($this->once())
            ->method('getStudentAttempt')
            ->with(1, $examId)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sesi ujian siswa tidak ditemukan.');

        $this->service->verifySupervisorPin(1, $examId, $prevPin);
    }

    public function test_stale_window_pin_older_than_one_window_is_rejected(): void
    {
        $examId = 55;
        $secret = config('app.key') . '_supervisor_pin_' . $examId;
        // Two windows ago (-2 = older than 2 minutes)
        $staleWindow = (int)floor(time() / 60) - 2;
        $hash = hash_hmac('sha256', (string)$staleWindow, $secret);
        $stalePin = str_pad((string)(hexdec(substr($hash, 0, 8)) % 900000 + 100000), 6, '0', STR_PAD_LEFT);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PIN verifikasi salah atau telah kedaluwarsa');

        $this->service->verifySupervisorPin(1, $examId, $stalePin);
    }

    public function test_exam_token_cannot_be_used_as_supervisor_pin(): void
    {
        $examId = 55;
        $tokenLikeString = 'MTK-UAS-26';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PIN verifikasi salah atau telah kedaluwarsa');

        $this->service->verifySupervisorPin(1, $examId, $tokenLikeString);
    }
}
