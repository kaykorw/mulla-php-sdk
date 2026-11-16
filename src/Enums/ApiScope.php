<?php

declare(strict_types=1);

namespace Mulla\Enums;

enum ApiScope: string
{
    case CHARGES = 'CHARGES';
    case PAYOUTS = 'PAYOUTS';
    case INVOICES = 'INVOICES';
    case RECHARGES = 'RECHARGES';
    case CUSTOMERS = 'CUSTOMERS';
    case WEBHOOKS = 'WEBHOOKS';
}
