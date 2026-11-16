<?php

declare(strict_types=1);

namespace Mulla\Http;

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
