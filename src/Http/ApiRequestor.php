<?php

declare(strict_types=1);

namespace Mulla\Http;

use Mulla\Exceptions\ApiException;
use Mulla\Exceptions\ExceptionFactory;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\Middleware\AuthMiddleware;
use Mulla\Http\Middleware\ClientContextMiddleware;
use Mulla\Http\Middleware\IdempotencyMiddleware;
use Mulla\Http\Middleware\LoggingMiddleware;
use Mulla\Http\Middleware\Pipeline;
use Mulla\Http\Middleware\RetryMiddleware;
use Mulla\MullaConfig;
use Mulla\Support\Normalizer;

/**
 * Builds requests, runs them through the middleware pipeline and turns
 * error responses into exceptions. Shared by every service.
 *
 * Pipeline order (outermost first):
 *   ClientContext -> Auth -> Idempotency -> Retry -> [your middleware] -> Logging -> transport
 */
final class ApiRequestor
{
    private readonly Pipeline $pipeline;

    public function __construct(private readonly MullaConfig $config)
    {
        $middleware = [
            new ClientContextMiddleware($config->deviceId, $config->lang, $config->appInfo),
            new AuthMiddleware($config->apiKey),
            new IdempotencyMiddleware(),
            new RetryMiddleware($config->retryStrategy, $config->sleeper),
            ...$config->middleware,
        ];
        if ($config->logger !== null) {
            $middleware[] = new LoggingMiddleware($config->logger);
        }

        $this->pipeline = new Pipeline($config->transport, $middleware);
    }

    /**
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @return array<mixed>|null decoded JSON response
     *
     * @throws ApiException
     * @throws \Mulla\Exceptions\ConnectionException
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?RequestOptions $options = null): ?array
    {
        $options ??= new RequestOptions();

        $headers = [];
        foreach ($options->headers as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        if ($options->idempotencyKey !== null) {
            $headers[IdempotencyMiddleware::HEADER] = $options->idempotencyKey;
        }
        if ($options->lang !== null) {
            $headers['x-lang'] = $options->lang;
            $headers['accept-language'] = $options->lang;
        }

        try {
            $json = $body !== null ? json_encode((object) $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null;
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Could not encode the request parameters as JSON: ' . $e->getMessage() . '. Check that all strings are valid UTF-8.', 0, $e);
        }

        $request = new Request(
            method: strtoupper($method),
            url: $this->config->baseUrl . '/' . ltrim($path, '/') . Normalizer::query($query),
            headers: $headers,
            body: $json,
            timeout: $options->timeout ?? $this->config->timeout,
            connectTimeout: $this->config->connectTimeout,
        );

        $response = $this->pipeline->send($request);

        if (!$response->isSuccessful()) {
            throw ExceptionFactory::fromResponse($response);
        }

        return $response->json();
    }
}
