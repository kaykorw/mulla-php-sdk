<?php

declare(strict_types=1);

namespace Mulla\Models;

/**
 * Base for every API object. Typed properties for autocompletion, plus:
 *  - $model->raw          the untouched API data (new fields appear here first)
 *  - $model['field']      read-only array access to raw fields
 *  - json_encode($model)  serialises the raw data
 *
 * @implements \ArrayAccess<string, mixed>
 */
abstract class AbstractModel implements \ArrayAccess, \JsonSerializable
{
    /** @var array<string, mixed> */
    public readonly array $raw;

    /**
     * @param array<string, mixed> $data
     */
    final public function __construct(array $data)
    {
        $this->raw = $data;
        $this->hydrate($data);
    }

    /**
     * Template Method hook: each model maps raw API fields to typed properties.
     *
     * @param array<string, mixed> $data
     */
    abstract protected function hydrate(array $data): void;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->raw;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->raw[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException(static::class . ' is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException(static::class . ' is read-only.');
    }

    /** @param array<string, mixed> $d */
    protected static function str(array $d, string $key): ?string
    {
        $v = $d[$key] ?? null;

        return is_scalar($v) ? (string) $v : null;
    }

    /** @param array<string, mixed> $d */
    protected static function bool(array $d, string $key): ?bool
    {
        $v = $d[$key] ?? null;

        return $v === null ? null : filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Money values. Integers when whole (RWF has no minor unit), floats otherwise.
     *
     * @param array<string, mixed> $d
     */
    protected static function money(array $d, string $key): int|float|null
    {
        $v = $d[$key] ?? null;
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return floor($f) === $f ? (int) $f : $f;
    }

    /** @param array<string, mixed> $d */
    protected static function date(array $d, string $key): ?\DateTimeImmutable
    {
        $v = $d[$key] ?? null;
        if (!is_string($v) || $v === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($v);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>|null
     */
    protected static function arr(array $d, string $key): ?array
    {
        $v = $d[$key] ?? null;

        return is_array($v) ? $v : null;
    }

    /**
     * Unknown enum values map to null rather than failing, so a new server status
     * never breaks old SDK versions (the original value stays in ->raw).
     *
     * @template T of \BackedEnum
     * @param array<string, mixed> $d
     * @param class-string<T>      $enum
     * @return T|null
     */
    protected static function enum(array $d, string $key, string $enum): ?\BackedEnum
    {
        $v = $d[$key] ?? null;

        return is_string($v) ? $enum::tryFrom($v) : null;
    }

    /**
     * Id of a relation that may be returned as a string id or an embedded object.
     *
     * @param array<string, mixed> $d
     */
    protected static function relationId(array $d, string $key): ?string
    {
        $v = $d[$key] ?? null;
        if (is_array($v)) {
            return isset($v['id']) && is_scalar($v['id']) ? (string) $v['id'] : null;
        }

        return is_scalar($v) ? (string) $v : null;
    }
}
