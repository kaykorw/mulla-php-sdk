<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Http\RequestOptions;
use Mulla\Models\Collection;
use Mulla\Models\Customer;
use Mulla\Models\MullaObject;
use Mulla\Params\CustomerParams;

/**
 * Manage your customers. Requires the CUSTOMERS scope.
 */
final class CustomerService extends AbstractService
{
    protected const SCOPE = \Mulla\Enums\ApiScope::CUSTOMERS;

    /**
     * @param CustomerParams|array{full_name: string, email?: string, phone?: string, address?: string, company_name?: string, country_of_origin?: string, city?: string, metadata?: array<string, mixed>} $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function create(CustomerParams|array $params, RequestOptions|array|null $options = null): Customer
    {
        return $this->createModel(Customer::class, 'customers', $params, $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(string $id, RequestOptions|array|null $options = null): Customer
    {
        return $this->retrieveModel(Customer::class, 'customers/' . self::id($id, 'customer id'), [], $options);
    }

    /**
     * @param array{keyword?: string, is_active?: bool, sortField?: string, sortDirection?: 'ASC'|'DESC', page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<Customer>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(Customer::class, 'customers', $filters, $options);
    }

    /**
     * Note: the API requires full_name on update too.
     *
     * @param CustomerParams|array<string, mixed> $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function update(string $id, CustomerParams|array $params, RequestOptions|array|null $options = null): Customer
    {
        return $this->updateModel(Customer::class, 'customers/' . self::id($id, 'customer id'), $params, $options);
    }

    /**
     * Blocks the customer from paying. Returns {message}.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function blacklist(string $id, RequestOptions|array|null $options = null): MullaObject
    {
        return $this->updateModel(MullaObject::class, 'customers/' . self::id($id, 'customer id') . '/blacklist', [], $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function whitelist(string $id, RequestOptions|array|null $options = null): MullaObject
    {
        return $this->updateModel(MullaObject::class, 'customers/' . self::id($id, 'customer id') . '/whitelist', [], $options);
    }
}
