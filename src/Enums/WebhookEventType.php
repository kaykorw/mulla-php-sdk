<?php

declare(strict_types=1);

namespace Mulla\Enums;

enum WebhookEventType: string
{
    case CHARGE_SUCCESS = 'charge.success';
    case CHARGE_FAILED = 'charge.failed';
    case CHARGE_EXPIRED = 'charge.expired';
    case PAYOUT_SUCCESS = 'payout.success';
    case PAYOUT_FAILED = 'payout.failed';
    case INVOICE_PAID = 'invoice.paid';
    case WEBHOOK_TEST = 'webhook.test';

    public function isCharge(): bool
    {
        return str_starts_with($this->value, 'charge.');
    }

    public function isPayout(): bool
    {
        return str_starts_with($this->value, 'payout.');
    }

    public function isInvoice(): bool
    {
        return str_starts_with($this->value, 'invoice.');
    }
}
