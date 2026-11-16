<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Enums\PayoutStatus;
use Mulla\Enums\Provider;

/**
 * A payout sends money from your Mulla balance to a mobile money wallet.
 * The amount is reserved from your balance as soon as the payout is created.
 */
final class Payout extends AbstractModel
{
    public readonly string $id;
    public readonly ?Provider $provider;
    public readonly int|float|null $amount;
    public readonly int|float|null $feeAmount;
    public readonly int|float|null $netAmount;
    public readonly ?string $currency;
    public readonly ?string $phoneNumber;
    public readonly ?string $reason;
    public readonly ?PayoutStatus $status;
    public readonly ?string $failureCode;
    public readonly ?string $failureMessage;
    public readonly ?\DateTimeImmutable $approvedAt;
    public readonly ?\DateTimeImmutable $scheduledAt;
    public readonly ?\DateTimeImmutable $completedAt;
    public readonly ?\DateTimeImmutable $expiresAt;
    public readonly ?\DateTimeImmutable $createdAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->provider = self::enum($data, 'provider', Provider::class);
        $this->amount = self::money($data, 'amount');
        $this->feeAmount = self::money($data, 'fee_amount');
        $this->netAmount = self::money($data, 'net_amount');
        $this->currency = self::str($data, 'currency');
        $this->phoneNumber = self::str($data, 'phone_number');
        $this->reason = self::str($data, 'reason');
        $this->status = self::enum($data, 'status', PayoutStatus::class);
        $this->failureCode = self::str($data, 'failure_code');
        $this->failureMessage = self::str($data, 'failure_message');
        $this->approvedAt = self::date($data, 'approved_at');
        $this->scheduledAt = self::date($data, 'scheduled_at');
        $this->completedAt = self::date($data, 'completed_at');
        $this->expiresAt = self::date($data, 'expires_at');
        $this->createdAt = self::date($data, 'created_at');
    }

    public function isSuccessful(): bool
    {
        return $this->status === PayoutStatus::COMPLETED;
    }

    public function isFinal(): bool
    {
        return $this->status?->isFinal() ?? false;
    }
}
