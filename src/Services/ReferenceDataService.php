<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Http\RequestOptions;
use Mulla\Models\Collection;
use Mulla\Models\MullaObject;

/**
 * Read-only reference data: banks, bank branches, countries, currencies, API scopes.
 */
final class ReferenceDataService extends AbstractService
{
    /**
     * @param array{country: string, keyword?: string, name?: string, swift?: string, page?: int, limit?: int} $filters country is required
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<MullaObject>
     */
    public function banks(array $filters, RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(MullaObject::class, 'banks', $filters, $options);
    }

    /**
     * @param array{code?: string, swift?: string, keyword?: string, page?: int, limit?: int} $filters code or swift is required
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<MullaObject>
     */
    public function bankBranches(array $filters, RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(MullaObject::class, 'banks/branches', $filters, $options);
    }

    /**
     * @param array{keyword?: string, iso?: string, currency?: string, is_supported?: bool, page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<MullaObject>
     */
    public function countries(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(MullaObject::class, 'countries', $filters, $options);
    }

    /**
     * @param array{keyword?: string, code?: string, is_supported?: bool, page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<MullaObject>
     */
    public function currencies(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(MullaObject::class, 'currencies', $filters, $options);
    }

    /**
     * @param array{keyword?: string, page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<MullaObject>
     */
    public function scopes(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(MullaObject::class, 'scopes', $filters, $options);
    }
}
