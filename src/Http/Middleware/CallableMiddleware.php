<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Wraps a callable fn(Request $request, callable $next): Response so it can be used as middleware.
 */
final class CallableMiddleware implements MiddlewareInterface
{
    /** @var callable(Request, callable(Request): Response): Response */
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = ($this->handler)($request, $next);
        if (!$response instanceof Response) {
            throw new InvalidArgumentException('A middleware callable must return the Response from $next($request).');
        }

        return $response;
    }
}
