<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Enums\PaymentStatus;
use Mulla\Enums\Provider;

/**
 * One provider payment attempt (e.g. a payment made against an invoice).
 */
final class PaymentTransaction extends AbstractModel
{
    public readonly string $id;
    public readonly ?string $transactionId;
    public readonly ?Provider $provider;
    public readonly int|float|null $amount;
    public readonly int|float|null $feeAmount;
    public readonly ?string $currency;
    public readonly ?string $customerPhone;
    public readonly ?PaymentStatus $status;
    public readonly ?string $failureReason;
    public readonly ?\DateTimeImmutable $createdAt;
    public readonly ?\DateTimeImmutable $completedAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->transactionId = self::str($data, 'transaction_id');
        $this->provider = self::enum($data, 'provider', Provider::class);
        $this->amount = self::money($data, 'amount');
        $this->feeAmount = self::money($data, 'fee_amount');
        $this->currency = self::str($data, 'currency');
        $this->customerPhone = self::str($data, 'customer_phone');
        $this->status = self::enum($data, 'status', PaymentStatus::class);
        $this->failureReason = self::str($data, 'failure_reason');
        $this->createdAt = self::date($data, 'created_at');
        $this->completedAt = self::date($data, 'completed_at');
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::SUCCESSFUL;
    }
}
