<?php

declare(strict_types=1);

namespace Mulla\Models;

final class Customer extends AbstractModel
{
    public readonly string $id;
    public readonly ?string $fullName;
    public readonly ?string $email;
    public readonly ?string $phone;
    public readonly ?string $address;
    public readonly ?string $companyName;
    public readonly ?string $countryOfOrigin;
    public readonly ?string $city;
    /** @var array<string, mixed>|null */
    public readonly ?array $metadata;
    public readonly ?bool $isActive;
    public readonly ?\DateTimeImmutable $createdAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->fullName = self::str($data, 'full_name');
        $this->email = self::str($data, 'email');
        $this->phone = self::str($data, 'phone');
        $this->address = self::str($data, 'address');
        // The API returns camelCase on list/create and snake_case on retrieve.
        $this->companyName = self::str($data, 'companyName') ?? self::str($data, 'company_name');
        $this->countryOfOrigin = self::str($data, 'countryOfOrigin') ?? self::str($data, 'country_of_origin');
        $this->city = self::str($data, 'city');
        $this->metadata = self::arr($data, 'metadata');
        $this->isActive = self::bool($data, 'is_active');
        $this->createdAt = self::date($data, 'created_at');
    }
}
