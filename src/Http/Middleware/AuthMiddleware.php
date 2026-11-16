<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Adds the secret API key header.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public const HEADER = 'x-api-key';

    public function __construct(#[\SensitiveParameter] private readonly string $apiKey) {}

    public function __debugInfo(): array
    {
        return ['apiKey' => '****'];
    }

    public function handle(Request $request, callable $next): Response
    {
        return $next($request->withHeader(self::HEADER, $this->apiKey));
    }
}
