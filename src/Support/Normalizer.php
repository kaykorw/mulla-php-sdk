<?php

declare(strict_types=1);

namespace Mulla\Support;

use Mulla\Params\ParamsInterface;

/**
 * Converts user input (arrays, Params builders, enums, dates) into plain JSON-ready data.
 * Null values are dropped, because the API rejects unknown or empty fields.
 *
 * @internal
 */
final class Normalizer
{
    private function __construct()
    {
    }

    /**
     * @param ParamsInterface|array<string, mixed>|null $params
     * @return array<string, mixed>
     */
    public static function params(ParamsInterface|array|null $params): array
    {
        if ($params === null) {
            return [];
        }
        $data = $params instanceof ParamsInterface ? $params->toArray() : $params;

        /** @var array<string, mixed> */
        return self::value($data);
    }

    public static function value(mixed $value): mixed
    {
        if ($value instanceof ParamsInterface) {
            return self::value($value->toArray());
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if ($value instanceof \JsonSerializable) {
            return self::value($value->jsonSerialize());
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if ($item === null) {
                    continue;
                }
                $out[$key] = self::value($item);
            }

            return array_is_list($value) ? array_values($out) : $out;
        }

        return $value;
    }

    /**
     * Query values: booleans become "true"/"false" (the API expects strings).
     *
     * @param array<string, mixed> $query
     */
    public static function query(array $query): string
    {
        $clean = [];
        foreach (self::params($query) as $key => $value) {
            $clean[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return $clean === [] ? '' : '?' . http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }
}
