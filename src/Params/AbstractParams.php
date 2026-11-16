<?php

declare(strict_types=1);

namespace Mulla\Params;

use Mulla\Exceptions\InvalidArgumentException;

/**
 * Fluent setters collect fields; toArray() checks required ones
 * so mistakes are reported before any request is sent.
 */
abstract class AbstractParams implements ParamsInterface
{
    /** Required API fields (snake_case). */
    protected const REQUIRED = [];

    /** @var array<string, mixed> */
    protected array $data = [];

    final public function __construct()
    {
    }

    public static function make(): static
    {
        return new static();
    }

    /** Set any field directly (escape hatch for fields the builder does not cover yet). */
    public function set(string $field, mixed $value): static
    {
        $this->data[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        $missing = array_filter(static::REQUIRED, fn (string $f): bool => !isset($this->data[$f]) || $this->data[$f] === '');
        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                '%s is missing required field(s): %s.',
                (new \ReflectionClass($this))->getShortName(),
                implode(', ', $missing),
            ));
        }

        return $this->data;
    }
}
