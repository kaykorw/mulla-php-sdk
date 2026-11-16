<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * The Mulla API answered with an error status (4xx/5xx).
 */
class ApiException extends MullaException
{
    /**
     * @param array<string, mixed>|null $body    Decoded error body, if it was JSON
     * @param array<string, string>     $headers Response headers (lower-cased names)
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?string $errorCode = null,
        private readonly ?array $body = null,
        private readonly array $headers = [],
        private readonly ?string $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /** HTTP status code, e.g. 400. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Machine-readable Mulla error code, e.g. "INSUFFICIENT_BALANCE". */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed>|null */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }
}
