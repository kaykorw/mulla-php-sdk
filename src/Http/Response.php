<?php

declare(strict_types=1);

namespace Mulla\Http;

/**
 * Immutable HTTP response returned by a transport.
 */
final class Response
{
    /** @var array<string, mixed>|null|false false = not decoded yet */
    private array|null|false $decoded = false;

    /**
     * @param array<string, string> $headers Header name (lower-cased) => value
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Decoded JSON body, or null when the body is empty or not a JSON object/array.
     *
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        if ($this->decoded === false) {
            $data = $this->body === '' ? null : json_decode($this->body, true);
            $this->decoded = is_array($data) ? $data : null;
        }

        return $this->decoded;
    }
}
