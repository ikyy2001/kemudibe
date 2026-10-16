<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiResponse;
use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NaraRouterClient
{
    /**
     * Send chat completion request to NaraRouter gateway.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @throws AiServiceException
     */
    public function chat(array $messages, array $options = []): AiResponse
    {
        $baseUrl = rtrim((string) config('ai.base_url', 'https://router.bynara.id/v1'), '/');
        $apiKey = (string) config('ai.api_key', '');
        $model = (string) ($options['model'] ?? config('ai.model', ''));

        // Validate configuration (misconfigured)
        if (trim($apiKey) === '' || trim($model) === '') {
            $this->logError(AiServiceException::CATEGORY_MISCONFIGURED, null, 0);
            throw new AiServiceException(
                'Konfigurasi AI belum lengkap: API key atau nama model belum diatur.',
                AiServiceException::CATEGORY_MISCONFIGURED
            );
        }

        // Clamp max_tokens to config limit
        $configMaxTokens = (int) config('ai.max_tokens', 300);
        $requestedMaxTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : $configMaxTokens;
        $maxTokens = min($requestedMaxTokens, $configMaxTokens);
        if ($maxTokens <= 0) {
            $maxTokens = $configMaxTokens;
        }

        $temperature = isset($options['temperature'])
            ? (float) $options['temperature']
            : (float) config('ai.temperature', 0.3);

        $timeout = (int) config('ai.timeout', 30);
        $url = "{$baseUrl}/chat/completions";

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'stream' => false,
        ];

        $startTime = hrtime(true);

        // Retry callback: retry max 1 time only for connection errors and 5xx (NOT 4xx, NOT 429)
        $retryWhen = function (Throwable $exception): bool {
            if ($exception instanceof ConnectionException) {
                return true;
            }
            if ($exception instanceof RequestException) {
                $status = $exception->response?->status();
                return $status !== null && $status >= 500;
            }
            return false;
        };

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout($timeout)
                ->retry(2, 100, $retryWhen, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            $latencyMs = $this->calculateLatency($startTime);
            $this->logError(AiServiceException::CATEGORY_TIMEOUT, null, $latencyMs);
            throw new AiServiceException(
                'Koneksi ke layanan AI mengalami batas waktu (timeout).',
                AiServiceException::CATEGORY_TIMEOUT,
                null,
                $latencyMs,
                $e
            );
        } catch (Throwable $e) {
            $latencyMs = $this->calculateLatency($startTime);
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timeout')
                || str_contains(strtolower($e->getMessage()), 'timed out');

            $category = $isTimeout
                ? AiServiceException::CATEGORY_TIMEOUT
                : AiServiceException::CATEGORY_UPSTREAM_ERROR;

            $message = $isTimeout
                ? 'Koneksi ke layanan AI mengalami batas waktu (timeout).'
                : 'Terjadi kesalahan saat menghubungi layanan AI.';

            $this->logError($category, null, $latencyMs);
            throw new AiServiceException($message, $category, null, $latencyMs, $e);
        }

        $latencyMs = $this->calculateLatency($startTime);

        // Handle upstream HTTP errors
        if ($response->failed()) {
            $status = $response->status();

            if ($status === 429) {
                $this->logError(AiServiceException::CATEGORY_UPSTREAM_RATE_LIMITED, $status, $latencyMs);
                throw new AiServiceException(
                    'Layanan AI sedang sibuk atau kuota upstream telah terlampaui (rate limit).',
                    AiServiceException::CATEGORY_UPSTREAM_RATE_LIMITED,
                    $status,
                    $latencyMs
                );
            }

            $this->logError(AiServiceException::CATEGORY_UPSTREAM_ERROR, $status, $latencyMs);
            throw new AiServiceException(
                "Layanan AI mengembalikan kesalahan HTTP {$status}.",
                AiServiceException::CATEGORY_UPSTREAM_ERROR,
                $status,
                $latencyMs
            );
        }

        // Parse response
        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_array($data) || !isset($data['choices'][0]['message']['content']) || !is_string($content)) {
            $this->logError(AiServiceException::CATEGORY_INVALID_RESPONSE, $response->status(), $latencyMs);
            throw new AiServiceException(
                'Format respons dari layanan AI tidak valid.',
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                $response->status(),
                $latencyMs
            );
        }

        $usage = $data['usage'] ?? null;
        $promptTokens = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null;
        $completionTokens = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null;
        $totalTokens = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null;
        $responseModel = isset($data['model']) && is_string($data['model']) ? $data['model'] : $model;

        return new AiResponse(
            content: $content,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            model: $responseModel,
            latencyMs: $latencyMs
        );
    }

    /**
     * Calculate elapsed latency in milliseconds.
     */
    protected function calculateLatency(int $startTimeHr): int
    {
        return (int) max(1, round((hrtime(true) - $startTimeHr) / 1e6));
    }

    /**
     * Safely log errors without leaking sensitive tokens or user messages.
     */
    protected function logError(string $category, ?int $statusCode, int $latencyMs): void
    {
        Log::error('AI Service Error', [
            'category' => $category,
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
        ]);
    }
}
