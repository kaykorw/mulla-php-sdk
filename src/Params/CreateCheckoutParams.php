<?php

declare(strict_types=1);

namespace Mulla\Params;

/**
 * Hosted checkout: Mulla's page collects the provider and phone number.
 *
 *     CreateCheckoutParams::make()
 *         ->amount(5000)
 *         ->successUrl('https://shop.test/thanks')
 *         ->cancelUrl('https://shop.test/cart');
 */
final class CreateCheckoutParams extends AbstractParams
{
    use ChargeFields;

    protected const REQUIRED = ['amount', 'success_url', 'cancel_url'];

    /** Where the customer lands after paying. Confirm the payment server-side. */
    public function successUrl(string $url): self
    {
        return $this->set('success_url', $url);
    }

    /** Where the customer lands if they cancel. */
    public function cancelUrl(string $url): self
    {
        return $this->set('cancel_url', $url);
    }
}
