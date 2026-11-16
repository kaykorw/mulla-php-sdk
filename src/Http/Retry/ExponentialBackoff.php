<?php

declare(strict_types=1);

namespace Mulla\Http\Retry;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Default strategy: exponential backoff with jitter, honouring Retry-After
 * (but giving up when the server asks to wait longer than $maxDelay).
 *
 * Reads (GET) are retried on network errors, 408, 429 and 5xx.
 *
 * Writes (POST/PATCH) are retried ONLY when Mulla cannot have processed them:
 * the connection was never made, or the server answered 429/503 (rejected up front).
 * The API deletes the idempotency key when a request fails and does not replay
 * responses, so repeating a write after a timeout or 500 could create a second
 * charge or payout.
 */
final class ExponentialBackoff implements RetryStrategyInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const RETRYABLE_STATUSES = [408, 429, 500, 502, 503, 504];
    /** Statuses meaning the request was rejected before being processed. */
    private const NOT_PROCESSED_STATUSES = [429, 503];

    public function __construct(
        private readonly int $maxRetries = 2,
        private readonly float $baseDelay = 0.5,
        private readonly float $maxDelay = 8.0,
        private readonly bool $jitter = true,
    ) {}

    public function shouldRetry(Request $request, int $attempt, ?Response $response, ?\Throwable $error): bool
    {
        if ($attempt > $this->maxRetries) {
            return false;
        }

        $safe = in_array($request->method, self::SAFE_METHODS, true);

        if ($response === null) {
            if (!$error instanceof ConnectionException) {
                return false;
            }

            return $safe || !$error->requestMayHaveBeenSent();
        }

        $statuses = $safe ? self::RETRYABLE_STATUSES : self::NOT_PROCESSED_STATUSES;
        if (!in_array($response->statusCode, $statuses, true)) {
            return false;
        }

        $retryAfter = $this->retryAfter($response);

        return $retryAfter === null || $retryAfter <= $this->maxDelay;
    }

    public function delay(int $attempt, ?Response $response): float
    {
        $retryAfter = $response !== null ? $this->retryAfter($response) : null;
        if ($retryAfter !== null) {
            return $retryAfter;
        }

        $delay = min($this->maxDelay, $this->baseDelay * (2 ** ($attempt - 1)));
        if ($this->jitter) {
            $delay *= 0.5 + (mt_rand() / mt_getrandmax()) * 0.5;
        }

        return $delay;
    }

    private function retryAfter(Response $response): ?float
    {
        $value = $response->header('retry-after');

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }
}
