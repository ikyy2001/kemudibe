<?php

namespace Tests\Unit;

use App\DTOs\Ai\AiResponse;
use App\Http\Controllers\Api\AiChatController;
use App\Services\Ai\AiChatService;
use App\Services\Ai\AiDailyQuota;
use App\Services\Ai\AiResponseCache;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\NaraRouterClient;
use App\Support\Ai\Prompts;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class AiDeviceLimitAndPromptTest extends TestCase
{
    /**
     * Test that two devices on the same IP receive distinct guest identifiers.
     */
    public function test_guest_devices_on_same_ip_have_isolated_identifiers(): void
    {
        $controller = new class(
            Mockery::mock(AiChatService::class),
            Mockery::mock(AiDailyQuota::class),
            Mockery::mock(AiResponseCache::class),
            Mockery::mock(AiUsageLogger::class)
        ) extends AiChatController {
            public function callResolveGuestIdentifier(Request $request): string
            {
                return $this->resolveGuestIdentifier($request);
            }
        };

        $ip = '203.0.113.50';

        $reqDevA = Request::create('/api/ai/public/quota', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_X_GUEST_DEVICE_ID' => 'uuid-device-aaaa-1111',
        ]);

        $reqDevB = Request::create('/api/ai/public/quota', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_X_GUEST_DEVICE_ID' => 'uuid-device-bbbb-2222',
        ]);

        $reqNoDev = Request::create('/api/ai/public/quota', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        $idA = $controller->callResolveGuestIdentifier($reqDevA);
        $idB = $controller->callResolveGuestIdentifier($reqDevB);
        $idFallback = $controller->callResolveGuestIdentifier($reqNoDev);

        $this->assertNotEquals($idA, $idB, 'Different devices on the same IP must have different identifiers');
        $this->assertNotEquals($idA, $idFallback);
        $this->assertEquals(sha1($ip), $idFallback, 'Fallback must equal sha1(rawIp) for backward compatibility');

        // Same device repeated should produce exact same identifier
        $reqDevAAgain = Request::create('/api/ai/public/quota', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_X_GUEST_DEVICE_ID' => 'uuid-device-aaaa-1111',
        ]);
        $this->assertEquals($idA, $controller->callResolveGuestIdentifier($reqDevAAgain));
    }

    /**
     * Test that prompts version is bumped to v5 and includes typo & clarification instructions.
     */
    public function test_prompts_version_and_tolerance_content(): void
    {
        $this->assertEquals('v5', Prompts::PROMPT_VERSION);

        $landingPrompt = Prompts::landing();
        $this->assertStringContainsString('TOLERANSI TYPO', $landingPrompt);
        $this->assertStringContainsString('kmudi', $landingPrompt);
        $this->assertStringContainsString('PENANGANAN SAPAAN', $landingPrompt);
        $this->assertStringContainsString('JANGAN LANGSUNG MENOLAK', $landingPrompt);
    }

    /**
     * Test that AiChatService does not reject friendly clarification or typo handling.
     */
    public function test_ai_chat_service_guardrails(): void
    {
        $mockClient = Mockery::mock(NaraRouterClient::class);
        $chatService = new AiChatService($mockClient);

        // 1. Normal friendly clarification should NOT be overwritten by STANDARD_REJECTION
        $clarificationText = 'Pertanyaan Anda kurang spesifik, namun kami dapat bantu jelaskan mengenai fitur CBT Kemudi dan paket harga.';
        $mockClient->shouldReceive('chat')
            ->once()
            ->andReturn(new AiResponse($clarificationText, 10, 15, 25, 'openai/gpt-4o-mini', 120));

        $result = $chatService->chatLanding('gimana cara?');
        $this->assertEquals($clarificationText, $result['reply']);

        // 2. Malicious script tag MUST be caught and overwritten by STANDARD_REJECTION
        $mockClient->shouldReceive('chat')
            ->once()
            ->andReturn(new AiResponse('Silakan isi formulir: <form action="https://evil.com"><input name="pass"></form>', 10, 20, 30, 'openai/gpt-4o-mini', 150));

        $resultBad = $chatService->chatLanding('bikin form');
        $this->assertEquals(Prompts::STANDARD_REJECTION, $resultBad['reply']);
    }
}
