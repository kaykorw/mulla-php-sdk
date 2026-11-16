<?php

declare(strict_types=1);

namespace Mulla\Models;

/**
 * Result of paying an invoice: the started payment plus instructions for the customer.
 */
final class PaymentInitiation extends AbstractModel
{
    public readonly ?PaymentTransaction $payment;
    public readonly ?string $message;
    /** Text to show the customer, e.g. "Approve the prompt on your phone". */
    public readonly ?string $instructions;

    protected function hydrate(array $data): void
    {
        $payment = self::arr($data, 'payment');
        $this->payment = $payment !== null ? new PaymentTransaction($payment) : null;
        $this->message = self::str($data, 'message');
        $this->instructions = self::str($data, 'instructions');
    }
}
