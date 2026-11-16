<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Exceptions\MullaException;

/**
 * Factory: hydrates decoded API JSON into typed model objects.
 */
final class ModelFactory
{
    private function __construct()
    {
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>   $class
     * @param array<mixed>|null $data
     * @return T
     */
    public static function make(string $class, ?array $data): AbstractModel
    {
        if ($data === null) {
            throw new MullaException("Mulla returned an empty response where a {$class} was expected.");
        }

        /** @var array<string, mixed> $data */
        return new $class($data);
    }

    /**
     * @template T of AbstractModel
     * @param class-string<T>     $class
     * @param array<mixed>|null   $items
     * @return list<T>
     */
    public static function many(string $class, ?array $items): array
    {
        $out = [];
        foreach ($items ?? [] as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[] = new $class($item);
            }
        }

        return $out;
    }
}
