<?php

declare(strict_types=1);

namespace Mulla;

use Mulla\Enums\Language;
use Mulla\Environment\DefaultEnvironmentResolver;
use Mulla\Environment\Environment;
use Mulla\Environment\EnvironmentResolverInterface;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\Middleware\CallableMiddleware;
use Mulla\Http\Middleware\MiddlewareInterface;
use Mulla\Http\Retry\ExponentialBackoff;
use Mulla\Http\Retry\RetryStrategyInterface;
use Mulla\Http\Transport\CurlTransport;
use Mulla\Http\Transport\TransportInterface;
use Mulla\Support\Uuid;

/**
 * Immutable client configuration, built from the options passed to MullaClient.
 *
 * The SDK never reads environment variables or .env files. Pass every value explicitly.
 * null, false and '' count as "not set", so getenv() of an unset variable falls back to the default.
 *
 * Options:
 *  - api_key               string   Required. Your secret key, sk_test_... or sk_live_...
 *  - environment           Environment|string  Expected environment. Fails if the key belongs to the other one.
 *  - base_url              string   Gateway URL. Defaults to the official server for the key's environment.
 *  - webhook_secret        string   Default secret for $mulla->webhooks().
 *  - webhook_url           string   Default URL for $mulla->webhookEndpoint->create().
 *  - timeout               float    Total request time limit in seconds (default 30).
 *  - connect_timeout       float    Connection time limit in seconds (default 10).
 *  - max_retries           int      Extra attempts for requests that are safe to retry (default 2).
 *  - lang                  Language|string  Language of API messages: en, fr, rw, sw.
 *  - device_id             string   UUID v4 sent as x-device-id. Defaults to a stable value derived from the key.
 *  - app_info              array{name: string, version?: string, url?: string}  Added to the User-Agent.
 *  - logger                callable|object  PSR-3 logger or fn(string $level, string $message, array $context).
 *  - middleware            list<MiddlewareInterface|callable>  Extra steps run on every request.
 *  - transport             TransportInterface  Replaces the HTTP layer (default: cURL).
 *  - retry_strategy        RetryStrategyInterface  Replaces the default backoff.
 *  - environment_resolver  EnvironmentResolverInterface  Decides the base URL for an environment.
 *  - sleep                 callable(float): void  How retries wait (tests pass a no-op).
 */
final class MullaConfig
{
    public const OPTIONS = [
        'api_key', 'environment', 'base_url', 'webhook_secret', 'webhook_url', 'timeout', 'connect_timeout',
        'max_retries', 'lang', 'device_id', 'app_info', 'logger', 'middleware', 'transport', 'retry_strategy',
        'environment_resolver', 'sleep',
    ];
    private const APP_TOKEN = '/^[A-Za-z0-9._~@+-]{1,64}$/';

    /** @var callable(float): void */
    public readonly mixed $sleeper;

    /**
     * @param list<MiddlewareInterface> $middleware
     * @param callable(float): void     $sleeper
     */
    private function __construct(
        #[\SensitiveParameter]
        public readonly string $apiKey,
        public readonly Environment $environment,
        public readonly string $baseUrl,
        #[\SensitiveParameter]
        private readonly ?string $webhookSecret,
        public readonly ?string $webhookUrl,
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
     * @param array<string, mixed>|string $options the options above, or just the secret key
     */
    public static function create(#[\SensitiveParameter] array|string $options): self
    {
        if (is_string($options)) {
            $options = ['api_key' => $options];
        }

        $unknown = array_diff(array_keys($options), self::OPTIONS);
        if ($unknown !== []) {
            $hints = array_map(static function (int|string $key): string {
                $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $key));

                return in_array($snake, self::OPTIONS, true) && $snake !== $key ? "{$key} (did you mean \"{$snake}\"?)" : (string) $key;
            }, array_values($unknown));

            throw new InvalidArgumentException(sprintf(
                'Unknown Mulla option(s): %s. Allowed: %s.',
                implode(', ', $hints),
                implode(', ', self::OPTIONS),
            ));
        }

        $apiKey = self::optionalString($options, 'api_key');
        if ($apiKey === null) {
            throw new InvalidArgumentException(
                'Missing "api_key". Pass your secret key: new MullaClient([\'api_key\' => $secretKey]). '
                . 'The SDK does not read environment variables itself.',
            );
        }
        $environment = Environment::fromApiKey($apiKey);
        self::assertExpectedEnvironment($options['environment'] ?? null, $environment);

        $resolver = $options['environment_resolver'] ?? new DefaultEnvironmentResolver();
        self::assertInstance($resolver, EnvironmentResolverInterface::class, 'environment_resolver');

        $webhookUrl = self::optionalString($options, 'webhook_url');
        if ($webhookUrl !== null) {
            self::assertWebhookUrl($webhookUrl);
        }

        $lang = $options['lang'] ?? null;
        if ($lang instanceof Language) {
            $lang = $lang->value;
        }
        if ($lang !== null && (!is_string($lang) || Language::tryFrom($lang) === null)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported "lang" %s. Use one of: %s.',
                json_encode($lang),
                implode(', ', array_map(static fn(Language $l): string => $l->value, Language::cases())),
            ));
        }

        $deviceId = $options['device_id'] ?? Uuid::fromSeed('mulla-php:' . self::keyId($apiKey));
        if (!is_string($deviceId) || !Uuid::isValidV4($deviceId)) {
            throw new InvalidArgumentException('The "device_id" option must be a UUID v4 (use Uuid::v4() to create one).');
        }

        $timeout = self::positiveNumber($options, 'timeout', 30.0);
        $connectTimeout = self::positiveNumber($options, 'connect_timeout', 10.0);

        $transport = $options['transport'] ?? new CurlTransport();
        self::assertInstance($transport, TransportInterface::class, 'transport');

        if (isset($options['retry_strategy'], $options['max_retries'])) {
            throw new InvalidArgumentException(
                'Pass either "max_retries" or "retry_strategy", not both: set the limit on your strategy instead.',
            );
        }
        if (isset($options['retry_strategy'])) {
            $retryStrategy = $options['retry_strategy'];
            self::assertInstance($retryStrategy, RetryStrategyInterface::class, 'retry_strategy');
        } else {
            $maxRetries = $options['max_retries'] ?? 2;
            if (!is_int($maxRetries) || $maxRetries < 0) {
                throw new InvalidArgumentException('The "max_retries" option must be a whole number, 0 or more.');
            }
            $retryStrategy = new ExponentialBackoff($maxRetries);
        }

        $middleware = $options['middleware'] ?? [];
        if (!is_array($middleware)) {
            throw new InvalidArgumentException('The "middleware" option must be a list.');
        }
        $layers = [];
        foreach (array_values($middleware) as $index => $layer) {
            if ($layer instanceof MiddlewareInterface) {
                $layers[] = $layer;
            } elseif (is_callable($layer)) {
                $layers[] = new CallableMiddleware($layer);
            } else {
                throw new InvalidArgumentException(sprintf(
                    'The "middleware[%d]" option must implement %s or be a callable fn(Request $request, callable $next): Response.',
                    $index,
                    MiddlewareInterface::class,
                ));
            }
        }

        $logger = $options['logger'] ?? null;
        if ($logger !== null && !is_callable($logger) && !(is_object($logger) && method_exists($logger, 'log'))) {
            throw new InvalidArgumentException(
                'The "logger" option must be a PSR-3 logger or a callable fn(string $level, string $message, array $context).',
            );
        }

        $sleeper = $options['sleep'] ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
        if (!is_callable($sleeper)) {
            throw new InvalidArgumentException('The "sleep" option must be a callable fn(float $seconds): void.');
        }

        return new self(
            apiKey: $apiKey,
            environment: $environment,
            baseUrl: $resolver->resolveBaseUrl($environment, self::optionalString($options, 'base_url')),
            webhookSecret: self::optionalString($options, 'webhook_secret'),
            webhookUrl: $webhookUrl,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            lang: $lang,
            deviceId: $deviceId,
            appInfo: isset($options['app_info']) ? self::formatAppInfo($options['app_info']) : null,
            transport: $transport,
            retryStrategy: $retryStrategy,
            logger: $logger,
            middleware: $layers,
            sleeper: $sleeper,
        );
    }

    public function webhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    /** Keeps secrets out of var_dump/print_r output and framework error pages. */
    public function __debugInfo(): array
    {
        $info = get_object_vars($this);
        $info['apiKey'] = $this->maskedApiKey();
        $info['webhookSecret'] = $this->webhookSecret === null ? null : '****';

        return $info;
    }

    public function maskedApiKey(): string
    {
        return 'sk_' . $this->environment->value . '_' . self::keyId($this->apiKey) . '_****';
    }

    private static function keyId(#[\SensitiveParameter] string $apiKey): string
    {
        $parts = explode('_', $apiKey);

        return $parts[2] ?? '';
    }

    /** @param array<string, mixed> $options */
    private static function optionalString(#[\SensitiveParameter] array $options, string $option): ?string
    {
        $value = $options[$option] ?? null;
        if ($value === null || $value === false) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('The "%s" option must be a string.', $option));
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $options */
    private static function positiveNumber(array $options, string $option, float $default): float
    {
        $value = $options[$option] ?? $default;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value <= 0) {
            throw new InvalidArgumentException(sprintf('The "%s" option must be a positive number of seconds.', $option));
        }

        return (float) $value;
    }

    private static function assertExpectedEnvironment(mixed $expected, Environment $actual): void
    {
        if ($expected === null) {
            return;
        }
        $expected = is_string($expected) ? Environment::tryFrom($expected) : $expected;
        if (!$expected instanceof Environment) {
            throw new InvalidArgumentException('The "environment" option must be Environment::TEST or Environment::PRODUCTION.');
        }
        if ($expected !== $actual) {
            throw new InvalidArgumentException(sprintf(
                'The "environment" option is %s, but "api_key" is a %s key. Use a key from the matching environment.',
                $expected === Environment::Live ? 'Environment::PRODUCTION' : 'Environment::TEST',
                $actual === Environment::Live ? 'live (sk_live_)' : 'test (sk_test_)',
            ));
        }
    }

    private static function assertWebhookUrl(string $url): void
    {
        $parts = filter_var($url, FILTER_VALIDATE_URL) !== false ? parse_url($url) : false;
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException("The \"webhook_url\" option \"{$url}\" is not a valid URL.");
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The "webhook_url" option must not contain credentials or a fragment.');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && !($scheme === 'http' && $local)) {
            throw new InvalidArgumentException('The "webhook_url" option must use https:// (plain http only for localhost).');
        }
    }

    /** Builds a header-safe User-Agent token, so app_info can never inject headers. */
    private static function formatAppInfo(mixed $info): string
    {
        if (!is_array($info) || array_is_list($info) && $info !== []) {
            throw new InvalidArgumentException('The "app_info" option must be an array like [\'name\' => \'my-shop\', \'version\' => \'1.4.0\'].');
        }
        $unknown = array_diff(array_keys($info), ['name', 'version', 'url']);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown "app_info" field(s): %s. Allowed: name, version, url.', implode(', ', $unknown)));
        }
        $name = $info['name'] ?? null;
        if (!is_string($name) || preg_match(self::APP_TOKEN, $name) !== 1) {
            throw new InvalidArgumentException('The "app_info.name" field is required: 1-64 letters, digits or . _ ~ @ + - (no spaces).');
        }
        $version = $info['version'] ?? null;
        if ($version !== null && (!is_string($version) || preg_match(self::APP_TOKEN, $version) !== 1)) {
            throw new InvalidArgumentException('The "app_info.version" field must be 1-64 letters, digits or . _ ~ @ + -.');
        }

        $agent = $version !== null ? "{$name}/{$version}" : $name;
        $url = $info['url'] ?? null;
        if ($url !== null) {
            $scheme = is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false ? parse_url($url, PHP_URL_SCHEME) : null;
            if (!in_array(is_string($scheme) ? strtolower($scheme) : null, ['http', 'https'], true) || preg_match('/\s/', (string) $url) === 1) {
                throw new InvalidArgumentException('The "app_info.url" field must be an http(s) URL.');
            }
            $agent .= " (+{$url})";
        }

        return $agent;
    }

    /** @param class-string $class */
    private static function assertInstance(mixed $value, string $class, string $option): void
    {
        if (!$value instanceof $class) {
            throw new InvalidArgumentException(sprintf('The "%s" option must implement %s.', $option, $class));
        }
    }
}
