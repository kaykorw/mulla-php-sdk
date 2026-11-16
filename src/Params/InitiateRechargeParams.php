<?php

declare(strict_types=1);

namespace Mulla\Params;

use Mulla\Enums\Provider;

/**
 * Top up your Mulla balance from a mobile money wallet.
 */
final class InitiateRechargeParams extends AbstractParams
{
    protected const REQUIRED = ['provider', 'amount', 'phone_number'];

    public function provider(Provider|string $provider): self
    {
        return $this->set('provider', $provider);
    }

    public function amount(int|float $amount): self
    {
        return $this->set('amount', $amount);
    }

    public function phoneNumber(string $phoneNumber): self
    {
        return $this->set('phone_number', $phoneNumber);
    }

    public function reason(string $reason): self
    {
        return $this->set('reason', $reason);
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        return $this->set('metadata', $metadata);
    }
}
