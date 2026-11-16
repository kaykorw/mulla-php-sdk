<?php

declare(strict_types=1);

namespace Mulla\Support;

/**
 * Removes secrets from text that may end up in logs or error messages.
 *
 * @internal
 */
final class Redactor
{
    private const SECRET_QUERY_PARAMS = ['token'];

    private function __construct() {}

    public static function url(string $url): string
    {
        $pattern = '/([?&](?:' . implode('|', self::SECRET_QUERY_PARAMS) . ')=)[^&#]*/i';

        return (string) preg_replace($pattern, '$1***', $url);
    }
}
