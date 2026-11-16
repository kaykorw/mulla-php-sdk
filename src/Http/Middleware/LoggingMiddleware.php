<?php

declare(strict_types=1);

namespace Mulla\Http\Middleware;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Support\Redactor;

/**
 * Logs every attempt (method, URL, status, duration). Never logs the API key, bodies,
 * or secret query values such as checkout tokens.
 *
 * Accepts a PSR-3 logger (any object with a log($level, $message, $context) method)
 * or a callable fn(string $level, string $message, array $context).
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    /** @var callable(string, string, array<string, mixed>): void */
    private $log;

    public function __construct(callable|object $logger)
    {
        $this->log = is_callable($logger)
            ? $logger
            : static function (string $level, string $message, array $context) use ($logger): void {
                /** @phpstan-ignore-next-line duck-typed PSR-3 */
                $logger->log($level, $message, $context);
            };
    }

    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $url = Redactor::url($request->url);
        $context = [
            'method' => $request->method,
            'url' => $url,
            'attempt' => $request->attribute('attempt', 1),
            'idempotency_key' => $request->header(IdempotencyMiddleware::HEADER),
        ];

        try {
            $response = $next($request);
        } catch (ConnectionException $e) {
            ($this->log)('warning', 'Mulla request failed: ' . $e->getMessage(), $context + [
                'duration_ms' => $this->elapsed($start),
            ]);

            throw $e;
        }

        $context += [
            'status' => $response->statusCode,
            'duration_ms' => $this->elapsed($start),
        ];
        $level = $response->isSuccessful() ? 'debug' : ($response->statusCode >= 500 ? 'error' : 'warning');
        ($this->log)($level, sprintf('Mulla %s %s -> %d', $request->method, $url, $response->statusCode), $context);

        return $response;
    }

    private function elapsed(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
