<?php

declare(strict_types=1);

namespace Mulla\Params;

use Mulla\Enums\Provider;

/**
 * Direct charge: sends a payment prompt to the customer's phone.
 *
 *     InitiateChargeParams::make()
 *         ->provider(Provider::MTN_RW)
 *         ->phoneNumber('250788000000')
 *         ->amount(5000)
 *         ->reason('Order #1001')
 *         ->metadata(['order_id' => 1001]);
 */
final class InitiateChargeParams extends AbstractParams
{
    use ChargeFields;

    protected const REQUIRED = ['provider', 'amount', 'phone_number'];

    public function provider(Provider|string $provider): self
    {
        return $this->set('provider', $provider);
    }

    /** Customer's mobile money number, e.g. "250788000000". */
    public function phoneNumber(string $phoneNumber): self
    {
        return $this->set('phone_number', $phoneNumber);
    }
}
