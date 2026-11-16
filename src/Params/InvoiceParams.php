<?php

declare(strict_types=1);

namespace Mulla\Params;

/**
 * Create an invoice. Use make() for create (customer + items required)
 * or forUpdate() for partial updates (nothing required).
 *
 *     InvoiceParams::make()
 *         ->customer($customerId)
 *         ->addItem('Consulting', unitPrice: 50000, quantity: 2)
 *         ->dueDate(new DateTimeImmutable('+7 days'));
 */
final class InvoiceParams extends AbstractParams
{
    protected const REQUIRED = ['customer', 'items'];

    private bool $partial = false;

    /** Builder for InvoiceService::update(), where every field is optional. */
    public static function forUpdate(): self
    {
        $params = new self();
        $params->partial = true;

        return $params;
    }

    public function customer(string $customerId): self
    {
        return $this->set('customer', ['id' => $customerId]);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function addItem(
        string $name,
        int|float $unitPrice,
        int $quantity = 1,
        ?string $description = null,
        int|float|null $taxRate = null,
        ?string $imageUrl = null,
        ?array $metadata = null,
    ): self {
        $items = is_array($this->data['items'] ?? null) ? $this->data['items'] : [];
        $items[] = [
            'name' => $name,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'description' => $description,
            'tax_rate' => $taxRate,
            'image_url' => $imageUrl,
            'metadata' => $metadata,
        ];

        return $this->set('items', $items);
    }

    public function invoiceNumber(string $number): self
    {
        return $this->set('invoice_number', $number);
    }

    public function dueDate(\DateTimeInterface|string $date): self
    {
        return $this->set('due_date', $date);
    }

    public function notes(string $notes): self
    {
        return $this->set('notes', $notes);
    }

    public function application(string $application): self
    {
        return $this->set('application', $application);
    }

    /** @param array<string, mixed> $details */
    public function billingDetails(array $details): self
    {
        return $this->set('billing_details', $details);
    }

    /** @param array<string, mixed> $details */
    public function shippingDetails(array $details): self
    {
        return $this->set('shipping_details', $details);
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        return $this->set('metadata', $metadata);
    }

    public function toArray(): array
    {
        return $this->partial ? $this->data : parent::toArray();
    }
}
