<?php

declare(strict_types=1);

namespace Mulla\Enums;

/** Status of an invoice. */
enum InvoiceStatus: string
{
    case ISSUED = 'ISSUED';
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';

    /** The money moved successfully. */
    public function isSuccessful(): bool
    {
        return $this === self::PAID;
    }

    /** No further status change is expected. */
    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED], true);
    }
}
