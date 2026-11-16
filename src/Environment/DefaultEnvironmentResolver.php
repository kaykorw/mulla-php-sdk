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
        $env = getenv('MULLA_BASE_URL');
        $url = $configuredBaseUrl
            ?? (is_string($env) && $env !== '' ? $env : null)
            ?? $this->baseUrls[$environment->value]
            ?? null;

        if ($url === null) {
            throw new InvalidArgumentException(sprintf(
                'No Mulla API URL is configured for the %s environment. Pass the "baseUrl" option',
                $environment->value,
            ));
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("The Mulla base URL \"{$url}\" is not a valid URL.");
        }

        return self::withVersion($url);
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
