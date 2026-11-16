<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Http\Transport\TransportInterface;

/**
 * Runs a request through an ordered list of middleware, ending at the transport.
 * The first middleware in the list is the outermost one.
 */
final class Pipeline
{
    /** @var callable(Request): Response */
    private $handler;

    /**
     * @param list<MiddlewareInterface> $middleware
     */
    public function __construct(TransportInterface $transport, array $middleware = [])
    {
        $next = static fn(Request $request): Response => $transport->send($request);

        foreach (array_reverse($middleware) as $layer) {
            $next = static fn(Request $request): Response => $layer->handle($request, $next);
        }

        $this->handler = $next;
    }

    public function send(Request $request): Response
    {
        return ($this->handler)($request);
    }
}
