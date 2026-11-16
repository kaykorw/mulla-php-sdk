<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 429: too many requests. Wait getRetryAfter() seconds before trying again.
 */
class RateLimitException extends ApiException
{
    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    public function __construct(
        string $message,
        int $statusCode,
        ?string $errorCode = null,
        ?array $body = null,
        array $headers = [],
        ?string $rawBody = null,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode, $body, $headers, $rawBody);
    }

    /** Seconds to wait before retrying, when the server said so. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
