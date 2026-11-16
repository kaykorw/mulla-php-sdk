<?php

declare(strict_types=1);

namespace Mulla\Enums;

enum InvoiceStatus: string
{
    case ISSUED = 'ISSUED';
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';

    public function isSuccessful(): bool
    {
        return $this === self::PAID;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED], true);
    }
}
