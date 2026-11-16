<?php

declare(strict_types=1);

namespace Mulla\Models;

final class InvoiceItem extends AbstractModel
{
    public readonly ?string $id;
    public readonly ?string $name;
    public readonly ?string $description;
    public readonly ?string $imageUrl;
    public readonly ?int $quantity;
    public readonly int|float|null $unitPrice;
    public readonly int|float|null $amount;
    public readonly int|float|null $taxRate;
    public readonly int|float|null $taxAmount;

    protected function hydrate(array $data): void
    {
        $this->id = self::str($data, 'id');
        $this->name = self::str($data, 'name');
        $this->description = self::str($data, 'description');
        $this->imageUrl = self::str($data, 'image_url');
        $this->quantity = isset($data['quantity']) && is_numeric($data['quantity']) ? (int) $data['quantity'] : null;
        $this->unitPrice = self::money($data, 'unit_price');
        $this->amount = self::money($data, 'amount');
        $this->taxRate = self::money($data, 'tax_rate');
        $this->taxAmount = self::money($data, 'tax_amount');
    }
}
