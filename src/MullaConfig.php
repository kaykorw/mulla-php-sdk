<?php

declare(strict_types=1);

namespace Mulla;

use Mulla\Environment\DefaultEnvironmentResolver;
use Mulla\Environment\Environment;
use Mulla\Environment\EnvironmentResolverInterface;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\Middleware\MiddlewareInterface;
use Mulla\Http\Retry\ExponentialBackoff;
use Mulla\Http\Retry\RetryStrategyInterface;
use Mulla\Http\Transport\CurlTransport;
use Mulla\Http\Transport\TransportInterface;
use Mulla\Support\Uuid;

/**
 * Immutable client configuration. Built from the options array passed to MullaClient.
 *
 * Options:
 *  - baseUrl         string   API root incl. "/gateway/v1". Defaults per environment / MULLA_BASE_URL.
 *  - timeout         float    Total request timeout in seconds (default 30).
 *  - connectTimeout  float    Connection timeout in seconds (default 10).
 *  - maxRetries      int      Retries for network errors, 429 and 5xx (default 2). 0 disables.
 *  - lang            string   Language for API messages: en, fr, rw, sw.
 *  - deviceId        string   UUIDv4 sent as x-device-id. Defaults to a stable id derived from the key.
 *  - appInfo         string   Your integration, appended to the User-Agent (e.g. "my-shop/1.2").
 *  - transport       TransportInterface  Custom HTTP adapter (default: cURL).
 *  - retryStrategy   RetryStrategyInterface  Custom retry policy (overrides maxRetries).
 *  - logger          callable|object  PSR-3 logger or fn(string $level, string $message, array $context).
 *  - middleware      list<MiddlewareInterface>  Extra middleware, run around each attempt.
 *  - environmentResolver EnvironmentResolverInterface  Custom base URL resolution.
 *  - sleeper         callable(float): void  How the SDK waits (tests can pass a no-op).
 */
final class MullaConfig
{
    private const ALLOWED_OPTIONS = [
        'baseUrl', 'timeout', 'connectTimeout', 'maxRetries', 'lang', 'deviceId', 'appInfo',
        'transport', 'retryStrategy', 'logger', 'middleware', 'environmentResolver', 'sleeper',
    ];
    private const LANGUAGES = ['en', 'fr', 'rw', 'sw'];

    /** @var callable(float): void */
    public readonly mixed $sleeper;

    /**
     * @param list<MiddlewareInterface> $middleware
     * @param callable(float): void     $sleeper
     */
    private function __construct(
        public readonly string $apiKey,
        public readonly Environment $environment,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly float $connectTimeout,
        public readonly ?string $lang,
        public readonly string $deviceId,
        public readonly ?string $appInfo,
        public readonly TransportInterface $transport,
        public readonly RetryStrategyInterface $retryStrategy,
        public readonly mixed $logger,
        public readonly array $middleware,
        callable $sleeper,
    ) {
        $this->sleeper = $sleeper;
    }

    /**
     * @param array<string, mixed> $options see class docblock
     */
    public static function create(string $apiKey, array $options = []): self
    {
        $unknown = array_diff(array_keys($options), self::ALLOWED_OPTIONS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown Mulla option(s): %s. Allowed: %s.',
                implode(', ', $unknown),
                implode(', ', self::ALLOWED_OPTIONS),
            ));
        }

        $apiKey = trim($apiKey);
        $environment = Environment::fromApiKey($apiKey);

        $resolver = $options['environmentResolver'] ?? new DefaultEnvironmentResolver();
        self::assertInstance($resolver, EnvironmentResolverInterface::class, 'environmentResolver');

        $lang = $options['lang'] ?? null;
        if ($lang !== null && !in_array($lang, self::LANGUAGES, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported lang "%s". Use one of: %s.', (string) $lang, implode(', ', self::LANGUAGES)));
        }

        $deviceId = $options['deviceId'] ?? Uuid::fromSeed('mulla-php:' . self::keyId($apiKey));
        if (!is_string($deviceId) || !Uuid::isValidV4($deviceId)) {
            throw new InvalidArgumentException('The "deviceId" option must be a UUID v4.');
        }

        $maxRetries = (int) ($options['maxRetries'] ?? 2);
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('The "maxRetries" option cannot be negative.');
        }

        $transport = $options['transport'] ?? new CurlTransport();
        self::assertInstance($transport, TransportInterface::class, 'transport');

        $retryStrategy = $options['retryStrategy'] ?? new ExponentialBackoff($maxRetries);
        self::assertInstance($retryStrategy, RetryStrategyInterface::class, 'retryStrategy');

        $middleware = $options['middleware'] ?? [];
        if (!is_array($middleware)) {
            throw new InvalidArgumentException('The "middleware" option must be a list of MiddlewareInterface.');
        }
        foreach ($middleware as $layer) {
            self::assertInstance($layer, MiddlewareInterface::class, 'middleware');
        }

        $logger = $options['logger'] ?? null;
        if ($logger !== null && !is_callable($logger) && !(is_object($logger) && method_exists($logger, 'log'))) {
            throw new InvalidArgumentException('The "logger" option must be a PSR-3 logger or a callable.');
        }

        $sleeper = $options['sleeper'] ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
        if (!is_callable($sleeper)) {
            throw new InvalidArgumentException('The "sleeper" option must be callable.');
        }

        return new self(
            apiKey: $apiKey,
            environment: $environment,
            baseUrl: $resolver->resolveBaseUrl($environment, isset($options['baseUrl']) ? (string) $options['baseUrl'] : null),
            timeout: (float) ($options['timeout'] ?? 30.0),
            connectTimeout: (float) ($options['connectTimeout'] ?? 10.0),
            lang: $lang,
            deviceId: $deviceId,
            appInfo: isset($options['appInfo']) ? (string) $options['appInfo'] : null,
            transport: $transport,
            retryStrategy: $retryStrategy,
            logger: $logger,
            middleware: array_values($middleware),
            sleeper: $sleeper,
        );
    }

    /** Key with the secret part masked, safe for logs: sk_test_AbC123_****. */
    public function maskedApiKey(): string
    {
        return 'sk_' . $this->environment->value . '_' . self::keyId($this->apiKey) . '_****';
    }

    private static function keyId(string $apiKey): string
    {
        $parts = explode('_', $apiKey);

        return $parts[2] ?? '';
    }

    /** @param class-string $class */
    private static function assertInstance(mixed $value, string $class, string $option): void
    {
        if (!$value instanceof $class) {
            throw new InvalidArgumentException(sprintf('The "%s" option must implement %s.', $option, $class));
        }
    }
}
