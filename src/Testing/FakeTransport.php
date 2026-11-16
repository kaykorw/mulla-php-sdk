<?php

declare(strict_types=1);

namespace Mulla\Testing;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Http\Transport\TransportInterface;

/**
 * In-memory transport for testing code that uses the SDK. Queue responses,
 * run your code, then assert on the requests that were sent.
 */
final class FakeTransport implements TransportInterface
{
    /** @var list<Response|\Throwable|callable(Request): Response> */
    private array $queue = [];

    /** @var list<Request> */
    private array $sent = [];

    /**
     * @param array<mixed>          $body
     * @param array<string, string> $headers
     */
    public function pushJson(array $body, int $status = 200, array $headers = []): self
    {
        return $this->push(new Response($status, array_change_key_case($headers) + ['content-type' => 'application/json'], (string) json_encode($body)));
    }

    /**
     * Queue a Mulla-style error response.
     *
     * @param array<string, mixed> $extra extra body fields, e.g. ['retryAfter' => 60]
     */
    public function pushError(int $status, string $errorCode, string $message = 'Error', array $extra = []): self
    {
        return $this->pushJson(['statusCode' => $status, 'message' => $message, 'errorCode' => $errorCode] + $extra, $status);
    }

    public function pushConnectionError(string $message = 'Connection refused'): self
    {
        return $this->push(new ConnectionException($message));
    }

    /** @param Response|\Throwable|callable(Request): Response $response */
    public function push(Response|\Throwable|callable $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->sent[] = $request;

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException(sprintf(
                'FakeTransport has no queued response for %s %s. Queue one with pushJson().',
                $request->method,
                $request->url,
            ));
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }
        if ($next instanceof Response) {
            return $next;
        }

        return $next($request);
    }

    /** @return list<Request> */
    public function requests(): array
    {
        return $this->sent;
    }

    public function lastRequest(): ?Request
    {
        return $this->sent === [] ? null : $this->sent[count($this->sent) - 1];
    }

    /**
     * Decoded JSON body of the last request.
     *
     * @return array<mixed>|null
     */
    public function lastBody(): ?array
    {
        $body = $this->lastRequest()?->body;
        $decoded = $body !== null ? json_decode($body, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /** @param callable(Request): bool $matcher */
    public function assertSent(callable $matcher): void
    {
        foreach ($this->sent as $request) {
            if ($matcher($request)) {
                return;
            }
        }

        throw new \RuntimeException('Expected a matching request to be sent to Mulla, but none was. Sent: ' . $this->describe());
    }

    public function assertSentCount(int $count): void
    {
        if (count($this->sent) !== $count) {
            throw new \RuntimeException(sprintf('Expected %d request(s) to Mulla, got %d. Sent: %s', $count, count($this->sent), $this->describe()));
        }
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    private function describe(): string
    {
        return $this->sent === []
            ? '(none)'
            : implode(', ', array_map(static fn (Request $r): string => $r->method . ' ' . $r->url, $this->sent));
    }
}
