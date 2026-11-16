<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Enums\ChargeStatus;
use Mulla\Enums\Provider;

/**
 * A charge collects money from a customer's mobile money wallet.
 * It is created PENDING; the customer approves on their phone; it becomes
 * SUCCESSFUL, FAILED, or EXPIRED (after 10 minutes).
 */
final class Charge extends AbstractModel
{
    public readonly string $id;
    /** Human-friendly reference, e.g. "CHG..." */
    public readonly ?string $reference;
    public readonly int|float|null $amount;
    public readonly int|float|null $feeAmount;
    public readonly int|float|null $netAmount;
    public readonly int|float|null $additionalFee;
    public readonly ?string $currency;
    public readonly ?string $phoneNumber;
    public readonly ?Provider $provider;
    public readonly ?ChargeStatus $status;
    public readonly ?string $reason;
    public readonly ?string $application;
    public readonly ?string $notes;
    /** @var array<string, mixed>|null */
    public readonly ?array $billingDetails;
    /** @var array<string, mixed>|null */
    public readonly ?array $shippingDetails;
    /** @var array<string, mixed>|null */
    public readonly ?array $metadata;
    public readonly ?string $failureCode;
    public readonly ?string $failureMessage;
    public readonly ?\DateTimeImmutable $expiresAt;
    public readonly ?\DateTimeImmutable $createdAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->reference = self::str($data, 'reference');
        $this->amount = self::money($data, 'amount');
        $this->feeAmount = self::money($data, 'fee_amount');
        $this->netAmount = self::money($data, 'net_amount');
        $this->additionalFee = self::money($data, 'additional_fee');
        $this->currency = self::str($data, 'currency');
        $this->phoneNumber = self::str($data, 'phone_number');
        $this->provider = self::enum($data, 'provider', Provider::class);
        $this->status = self::enum($data, 'status', ChargeStatus::class);
        $this->reason = self::str($data, 'reason');
        $this->application = self::str($data, 'application');
        $this->notes = self::str($data, 'notes');
        $this->billingDetails = self::arr($data, 'billing_details');
        $this->shippingDetails = self::arr($data, 'shipping_details');
        $this->metadata = self::arr($data, 'metadata');
        $this->failureCode = self::str($data, 'failure_code');
        $this->failureMessage = self::str($data, 'failure_message');
        $this->expiresAt = self::date($data, 'expires_at');
        $this->createdAt = self::date($data, 'created_at');
    }

    public function isPending(): bool
    {
        return $this->status === ChargeStatus::PENDING;
    }

    public function isSuccessful(): bool
    {
        return $this->status === ChargeStatus::SUCCESSFUL;
    }

    public function isFinal(): bool
    {
        return $this->status?->isFinal() ?? false;
    }
}
