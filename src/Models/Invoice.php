<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Enums\InvoiceStatus;

/**
 * An invoice your customer can pay through its public link.
 */
final class Invoice extends AbstractModel
{
    public readonly string $id;
    public readonly ?string $invoiceNumber;
    public readonly ?InvoiceStatus $status;
    public readonly ?string $customer;
    public readonly ?string $customerName;
    public readonly ?string $customerEmail;
    public readonly ?string $customerPhone;
    public readonly int|float|null $subtotal;
    public readonly int|float|null $taxAmount;
    public readonly int|float|null $discountAmount;
    public readonly int|float|null $totalAmount;
    public readonly int|float|null $feeAmount;
    public readonly ?string $currency;
    public readonly ?string $notes;
    public readonly ?string $application;
    /** @var array<string, mixed>|null */
    public readonly ?array $metadata;
    /** @var list<InvoiceItem> */
    public readonly array $items;
    /** @var array<string, mixed>|null */
    public readonly ?array $merchant;
    public readonly ?\DateTimeImmutable $issueDate;
    public readonly ?\DateTimeImmutable $dueDate;
    public readonly ?\DateTimeImmutable $createdAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->invoiceNumber = self::str($data, 'invoice_number');
        $this->status = self::enum($data, 'status', InvoiceStatus::class);
        $this->customer = self::relationId($data, 'customer');
        $this->customerName = self::str($data, 'customer_name');
        $this->customerEmail = self::str($data, 'customer_email');
        $this->customerPhone = self::str($data, 'customer_phone');
        $this->subtotal = self::money($data, 'subtotal');
        $this->taxAmount = self::money($data, 'tax_amount');
        $this->discountAmount = self::money($data, 'discount_amount');
        $this->totalAmount = self::money($data, 'total_amount');
        $this->feeAmount = self::money($data, 'fee_amount');
        $this->currency = self::str($data, 'currency');
        $this->notes = self::str($data, 'notes');
        $this->application = self::str($data, 'application');
        $this->metadata = self::arr($data, 'metadata');
        $this->items = ModelFactory::many(InvoiceItem::class, self::arr($data, 'items'));
        $this->merchant = self::arr($data, 'merchant');
        $this->issueDate = self::date($data, 'issue_date');
        $this->dueDate = self::date($data, 'due_date');
        $this->createdAt = self::date($data, 'created_at');
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }
}
