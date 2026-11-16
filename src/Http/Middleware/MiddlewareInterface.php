<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Chain of Responsibility handler. Each middleware may change the request,
 * call $next to continue the chain, and inspect or replace the response.
 *
 * Add your own (e.g. tracing) through the client's 'middleware' option.
 */
interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response;
}
