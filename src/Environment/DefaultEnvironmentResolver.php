<?php

declare(strict_types=1);

namespace Mulla\Environment;

use Mulla\Exceptions\InvalidArgumentException;

final class DefaultEnvironmentResolver implements EnvironmentResolverInterface
{
    /** API version appended when a base URL does not end with one (e.g. ".../gateway"). */
    public const API_VERSION = 'v1';

    /**
     * Official gateway hosts per environment.
     *
     * @var array<string, string|null>
     */
    public const OFFICIAL_BASE_URLS = [
        'live' => 'https://core.mulla.app/gateway',
        'test' => 'https://mfs.kayko.dev/gateway',
    ];

    /**
     * @param array<string, string|null> $baseUrls override the official hosts (keys: live, test)
     */
    public function __construct(private readonly array $baseUrls = self::OFFICIAL_BASE_URLS) {}

    public function resolveBaseUrl(Environment $environment, ?string $configuredBaseUrl): string
    {
        $url = $configuredBaseUrl ?? $this->baseUrls[$environment->value] ?? null;

        if ($url === null) {
            throw new InvalidArgumentException(sprintf(
                'No Mulla API URL is configured for the %s environment. Pass the "base_url" option.',
                $environment->value,
            ));
        }

        self::assertSafeUrl($url);

        return self::withVersion($url);
    }

    /**
     * The secret key travels in every request, so the URL must use HTTPS.
     * Plain HTTP is allowed only for local development hosts.
     */
    private static function assertSafeUrl(string $url): void
    {
        $parts = filter_var($url, FILTER_VALIDATE_URL) !== false ? parse_url($url) : false;
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException("The Mulla base URL \"{$url}\" is not a valid URL.");
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The Mulla base URL must not contain credentials, a query string or a fragment.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim($parts['host'], '[]'));
        if ($scheme === 'https') {
            return;
        }
        if ($scheme === 'http' && self::isLocalHost($host)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The Mulla base URL must use https:// (got "%s://%s"). Plain http is only allowed for localhost.',
            $scheme,
            $host,
        ));
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }

    /**
     * Accepts the gateway URL as shown in the dashboard ("https://host/gateway")
     * or with a version ("https://host/gateway/v1") and always returns the versioned form.
     */
    public static function withVersion(string $url): string
    {
        $url = rtrim($url, '/');

        return preg_match('#/v\d+$#', $url) === 1 ? $url : $url . '/' . self::API_VERSION;
    }
}
