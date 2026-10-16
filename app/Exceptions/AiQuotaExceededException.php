<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiQuotaExceededException extends Exception
{
    public function __construct(
        protected string $tier,
        protected string|int $identifier,
        protected ?int $limit,
        protected int $used,
        protected int $retryAfter
    ) {
        $message = self::buildMessage($tier, $limit, $used);
        parent::__construct($message, 429);
    }

    public static function buildMessage(string $tier, ?int $limit, int $used): string
    {
        if ($tier === 'guest') {
            return 'Anda telah mencapai batas tanya jawab harian untuk pengunjung. Silakan hubungi tim sales kami via WhatsApp untuk demo langsung.';
        }

        if ($tier === 'student') {
            return "Batas pertanyaan AI harian Anda telah tercapai ({$used}/{$limit}). Silakan tanyakan kendala langsung ke Guru atau Admin Kelas.";
        }

        // teacher or others
        return "Batas pertanyaan AI harian Anda telah tercapai ({$used}/{$limit}).";
    }

    public function getTier(): string
    {
        return $this->tier;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function render(?Request $request = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'code' => 'daily_quota',
            'remaining' => 0,
            'message' => $this->getMessage(),
        ];

        if ($this->tier === 'guest') {
            $payload['contact_url'] = (string) config('ai.contact_whatsapp_url', '');
        }

        return response()->json($payload, 429, [
            'Retry-After' => (string) $this->retryAfter,
        ]);
    }
}
