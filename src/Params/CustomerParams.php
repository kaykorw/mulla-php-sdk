<?php

declare(strict_types=1);

namespace Mulla\Params;

/**
 * Create or update a customer. full_name is required in both cases.
 */
final class CustomerParams extends AbstractParams
{
    protected const REQUIRED = ['full_name'];

    public function fullName(string $name): self
    {
        return $this->set('full_name', $name);
    }

    public function email(string $email): self
    {
        return $this->set('email', $email);
    }

    public function phone(string $phone): self
    {
        return $this->set('phone', $phone);
    }

    public function address(string $address): self
    {
        return $this->set('address', $address);
    }

    public function companyName(string $companyName): self
    {
        return $this->set('company_name', $companyName);
    }

    public function countryOfOrigin(string $country): self
    {
        return $this->set('country_of_origin', $country);
    }

    public function city(string $city): self
    {
        return $this->set('city', $city);
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        return $this->set('metadata', $metadata);
    }
}
