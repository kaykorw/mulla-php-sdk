<?php

declare(strict_types=1);

namespace Mulla\Params;

use Mulla\Enums\Currency;

/**
 * Fields shared by direct charges and checkout sessions.
 */
trait ChargeFields
{
    /** Amount in the currency's major unit (RWF). Minimum 10. */
    public function amount(int|float $amount): static
    {
        return $this->set('amount', $amount);
    }

    public function currency(Currency|string $currency): static
    {
        return $this->set('currency', $currency);
    }

    /** Short description shown to the customer, e.g. "Order #1001". */
    public function reason(string $reason): static
    {
        return $this->set('reason', $reason);
    }

    /** Name of the app/site that created the charge (max 255 chars). */
    public function application(string $application): static
    {
        return $this->set('application', $application);
    }

    public function notes(string $notes): static
    {
        return $this->set('notes', $notes);
    }

    /** @param array<string, mixed> $details */
    public function billingDetails(array $details): static
    {
        return $this->set('billing_details', $details);
    }

    /** @param array<string, mixed> $details */
    public function shippingDetails(array $details): static
    {
        return $this->set('shipping_details', $details);
    }

    /**
     * Your own data, returned on the charge (e.g. ['order_id' => 1001]).
     *
     * @param array<string, mixed> $metadata
     */
    public function metadata(array $metadata): static
    {
        return $this->set('metadata', $metadata);
    }

    /** Link the charge to an existing Mulla customer. */
    public function customer(string $customerId): static
    {
        return $this->set('customer', ['id' => $customerId]);
    }
}
