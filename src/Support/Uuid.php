<?php

declare(strict_types=1);

namespace Mulla\Support;

/**
 * @internal
 */
final class Uuid
{
    private function __construct() {}

    /** Random RFC 4122 version-4 UUID. */
    public static function v4(): string
    {
        return self::format(random_bytes(16));
    }

    /**
     * Deterministic UUID with version-4 layout, derived from a seed.
     * Used for a stable per-installation device id (the API validates it as UUIDv4).
     */
    public static function fromSeed(string $seed): string
    {
        return self::format(substr(hash('sha256', $seed, true), 0, 16));
    }

    public static function isValidV4(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private static function format(string $bytes): string
    {
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
