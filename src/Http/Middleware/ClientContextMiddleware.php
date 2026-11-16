<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Version;

/**
 * Adds the client context headers Mulla requires on write requests
 * (x-platform, x-device-id, User-Agent) plus Accept/Content-Type and language.
 * Per-request headers already set (e.g. a per-call language) are left untouched.
 */
final class ClientContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $deviceId,
        private readonly ?string $lang = null,
        private readonly ?string $appInfo = null,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $headers = [
            'accept' => 'application/json',
            'user-agent' => $this->userAgent(),
            'x-platform' => Version::PLATFORM . '/' . Version::SDK,
            'x-device-id' => $this->deviceId,
        ];
        if ($request->body !== null) {
            $headers['content-type'] = 'application/json';
        }
        if ($this->lang !== null) {
            $headers['x-lang'] = $this->lang;
            $headers['accept-language'] = $this->lang;
        }

        foreach ($headers as $name => $value) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeader($name, $value);
            }
        }

        return $next($request);
    }

    private function userAgent(): string
    {
        $agent = sprintf('%s/%s PHP/%s', Version::PLATFORM, Version::SDK, PHP_VERSION);

        return $this->appInfo !== null ? $agent . ' ' . $this->appInfo : $agent;
    }
}
