<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Http\Retry\RetryStrategyInterface;

/**
 * Re-sends failed requests according to a RetryStrategy. IdempotencyMiddleware runs first,
 * so every attempt carries the same idempotency key. Which writes are safe to repeat is
 * decided by the strategy (see ExponentialBackoff).
 */
final class RetryMiddleware implements MiddlewareInterface
{
    /** @var callable(float): void */
    private $sleeper;

    /**
     * @param callable(float): void $sleeper receives seconds to wait
     */
    public function __construct(
        private readonly RetryStrategyInterface $strategy,
        callable $sleeper,
    ) {
        $this->sleeper = $sleeper;
    }

    public function handle(Request $request, callable $next): Response
    {
        $attempt = 1;

        while (true) {
            $response = null;
            $error = null;

            try {
                $response = $next($request->withAttribute('attempt', $attempt));
            } catch (ConnectionException $e) {
                $error = $e;
            }

            if (!$this->strategy->shouldRetry($request, $attempt, $response, $error)) {
                if ($error !== null) {
                    throw $error;
                }

                /** @var Response $response */
                return $response;
            }

            ($this->sleeper)($this->strategy->delay($attempt, $response));
            $attempt++;
        }
    }
}
