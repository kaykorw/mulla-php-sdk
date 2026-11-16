<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Enums\Provider;
use Mulla\Enums\RechargeStatus;

/**
 * A recharge tops up your own Mulla balance from a mobile money wallet
 * (for example, to fund payouts).
 */
final class Recharge extends AbstractModel
{
    public readonly string $id;
    public readonly ?string $reference;
    public readonly int|float|null $amount;
    public readonly int|float|null $feeAmount;
    public readonly int|float|null $netAmount;
    public readonly ?string $phoneNumber;
    public readonly ?Provider $provider;
    public readonly ?RechargeStatus $status;
    /** @var array<string, mixed>|null */
    public readonly ?array $metadata;
    public readonly ?string $failureCode;
    public readonly ?string $failureMessage;
    public readonly ?\DateTimeImmutable $createdAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->reference = self::str($data, 'reference');
        $this->amount = self::money($data, 'amount');
        $this->feeAmount = self::money($data, 'fee_amount');
        $this->netAmount = self::money($data, 'net_amount');
        $this->phoneNumber = self::str($data, 'phone_number');
        $this->provider = self::enum($data, 'provider', Provider::class);
        $this->status = self::enum($data, 'status', RechargeStatus::class);
        $this->metadata = self::arr($data, 'metadata');
        $this->failureCode = self::str($data, 'failure_code');
        $this->failureMessage = self::str($data, 'failure_message');
        $this->createdAt = self::date($data, 'created_at');
    }

    public function isSuccessful(): bool
    {
        return $this->status === RechargeStatus::SUCCESSFUL;
    }

    public function isFinal(): bool
    {
        return $this->status?->isFinal() ?? false;
    }
}
