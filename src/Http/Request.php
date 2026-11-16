<?php

declare(strict_types=1);

namespace Mulla\Http;

use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Support\Redactor;

/**
 * Immutable outgoing HTTP request passed through the middleware pipeline.
 */
final class Request
{
    /**
     * @param array<string, string> $headers    Header name => value (names are lower-cased)
     * @param array<string, mixed>  $attributes Free-form data for middleware (e.g. attempt number)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly array $attributes = [],
    ) {
        foreach ($headers as $name => $value) {
            self::assertValidHeader((string) $name, $value);
        }
    }

    /** Hides secret header values when the request is dumped (var_dump, print_r, error pages). */
    public function __debugInfo(): array
    {
        $headers = $this->headers;
        if (isset($headers['x-api-key'])) {
            $headers['x-api-key'] = '****';
        }

        return [
            'method' => $this->method,
            'url' => Redactor::url($this->url),
            'headers' => $headers,
            'body' => $this->body,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * Rejects header names/values that could inject extra header lines (CR, LF, NUL).
     */
    private static function assertValidHeader(string $name, mixed $value): void
    {
        if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP header name "%s".', addcslashes($name, "\0..\37")));
        }
        if (!is_string($value) || preg_match('/[\r\n\0]/', $value) === 1) {
            throw new InvalidArgumentException(sprintf('Invalid value for HTTP header "%s": it must be a string without line breaks.', $name));
        }
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[strtolower($name)] = $value;

        return new self($this->method, $this->url, $headers, $this->body, $this->timeout, $this->connectTimeout, $this->attributes);
    }

    public function withoutHeader(string $name): self
    {
        $headers = $this->headers;
        unset($headers[strtolower($name)]);

        return new self($this->method, $this->url, $headers, $this->body, $this->timeout, $this->connectTimeout, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self($this->method, $this->url, $this->headers, $this->body, $this->timeout, $this->connectTimeout, $attributes);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }
}
