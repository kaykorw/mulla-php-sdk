<?php

declare(strict_types=1);

namespace Mulla\Environment;

use Mulla\Exceptions\InvalidArgumentException;

/**
 * Mulla runs sandbox (test) and production (live) as separate servers.
 * The environment is encoded in the secret key prefix: sk_test_... / sk_live_...
 */
enum Environment: string
{
    case Live = 'live';
    case Test = 'test';

    public static function fromApiKey(string $apiKey): self
    {
        if (preg_match('/^sk_(live|test)_[A-Za-z0-9]+_/', $apiKey, $m) !== 1) {
            throw new InvalidArgumentException(
                'Invalid Mulla secret key. Expected a key starting with "sk_live_" or "sk_test_".'
            );
        }

        return self::from($m[1]);
    }

    public function isLive(): bool
    {
        return $this === self::Live;
    }
}
