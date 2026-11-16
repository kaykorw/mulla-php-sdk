<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Support\Uuid;

/**
 * Gives every POST an idempotency key (UUIDv4) unless the caller supplied one.
 * Sits before RetryMiddleware, so every retry of a request reuses the same key.
 */
final class IdempotencyMiddleware implements MiddlewareInterface
{
    public const HEADER = 'x-idempotency-key';

    public function handle(Request $request, callable $next): Response
    {
        if ($request->method === 'POST' && !$request->hasHeader(self::HEADER)) {
            $request = $request->withHeader(self::HEADER, Uuid::v4());
        }

        return $next($request);
    }
}
