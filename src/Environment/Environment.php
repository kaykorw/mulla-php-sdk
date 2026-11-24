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

    public const TEST = self::Test;
    public const PRODUCTION = self::Live;

    public static function fromApiKey(#[\SensitiveParameter] string $apiKey): self
    {
        if (preg_match('/^sk_(live|test)_[A-Za-z0-9]+_/', $apiKey, $m) !== 1) {
            throw new InvalidArgumentException(
                'Invalid Mulla secret key. Expected the format "sk_test_<id>_<secret>" or "sk_live_<id>_<secret>".',
            );
        }

        return self::from($m[1]);
    }

    public function isLive(): bool
    {
        return $this === self::Live;
    }
}
