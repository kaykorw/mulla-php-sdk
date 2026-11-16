<?php

declare(strict_types=1);

namespace Mulla\Models;

/**
 * Generic object for simple reference data (banks, branches, countries, currencies, scopes)
 * and short responses such as {"message": "..."}. Read fields as properties: $bank->name.
 */
final class MullaObject extends AbstractModel
{
    protected function hydrate(array $data): void
    {
    }

    public function __get(string $name): mixed
    {
        return $this->raw[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->raw[$name]);
    }
}
