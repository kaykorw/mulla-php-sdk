<?php

declare(strict_types=1);

namespace Mulla\Http\Retry;

use Mulla\Http\Response;

/**
 * Default strategy: retry network errors, 429 and 5xx with exponential backoff + jitter.
 * Honours Retry-After, but gives up when the server asks to wait longer than $maxDelay.
 */
final class ExponentialBackoff implements RetryStrategyInterface
{
    private const RETRYABLE_STATUSES = [408, 429, 500, 502, 503, 504];

    public function __construct(
        private readonly int $maxRetries = 2,
        private readonly float $baseDelay = 0.5,
        private readonly float $maxDelay = 8.0,
        private readonly bool $jitter = true,
    ) {
    }

    public function shouldRetry(int $attempt, ?Response $response, ?\Throwable $error): bool
    {
        if ($attempt > $this->maxRetries) {
            return false;
        }
        if ($response === null) {
            return $error !== null;
        }
        if (!in_array($response->statusCode, self::RETRYABLE_STATUSES, true)) {
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
