<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class AiServiceException extends Exception
{
    public const CATEGORY_TIMEOUT = 'timeout';
    public const CATEGORY_UPSTREAM_RATE_LIMITED = 'upstream_rate_limited';
    public const CATEGORY_UPSTREAM_ERROR = 'upstream_error';
    public const CATEGORY_INVALID_RESPONSE = 'invalid_response';
    public const CATEGORY_MISCONFIGURED = 'misconfigured';

    public function __construct(
        string $message,
        protected string $category = self::CATEGORY_UPSTREAM_ERROR,
        protected ?int $statusCode = null,
        protected ?int $latencyMs = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }
}
