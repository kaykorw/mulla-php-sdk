<?php

declare(strict_types=1);

namespace Mulla\Http\Retry;

use Mulla\Http\Response;

/**
 * Strategy: decides whether a failed attempt is retried and how long to wait.
 */
interface RetryStrategyInterface
{
    /**
     * @param int            $attempt  1 for the first attempt
     * @param Response|null  $response null when the request failed at the network level
     * @param \Throwable|null $error   the connection error, if any
     */
    public function shouldRetry(int $attempt, ?Response $response, ?\Throwable $error): bool;

    /** Seconds to wait before the next attempt. */
    public function delay(int $attempt, ?Response $response): float;
}
