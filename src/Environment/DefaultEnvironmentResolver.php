<?php

declare(strict_types=1);

namespace Mulla\Environment;

use Mulla\Exceptions\InvalidArgumentException;

/**
 * Resolution order:
 *   1. the 'baseUrl' client option
 *   2. the MULLA_BASE_URL environment variable
 *   3. the official URL for the key's environment (live / test)
 */
final class DefaultEnvironmentResolver implements EnvironmentResolverInterface
{
    /**
     * Official API hosts per environment.
     * TODO: fill in once the public production and sandbox hostnames are confirmed.
     *
     * @var array<string, string|null>
     */
    public const OFFICIAL_BASE_URLS = [
        'live' => null,
        'test' => null,
    ];

    /**
     * @param array<string, string|null> $baseUrls override the official hosts (keys: live, test)
     */
    public function __construct(private readonly array $baseUrls = self::OFFICIAL_BASE_URLS)
    {
    }

    public function resolveBaseUrl(Environment $environment, ?string $configuredBaseUrl): string
    {
        $env = getenv('MULLA_BASE_URL');
        $url = $configuredBaseUrl
            ?? (is_string($env) && $env !== '' ? $env : null)
            ?? $this->baseUrls[$environment->value]
            ?? null;

        if ($url === null) {
            throw new InvalidArgumentException(sprintf(
                'No Mulla API URL is configured for the %s environment. Pass the "baseUrl" option '
                . '(e.g. "https://<host>/gateway/v1") or set the MULLA_BASE_URL environment variable.',
                $environment->value,
            ));
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("The Mulla base URL \"{$url}\" is not a valid URL.");
        }

        return rtrim($url, '/');
    }
}
