<?php

declare(strict_types=1);

namespace Mulla\Enums;

/** Status of a single provider payment attempt (e.g. one invoice payment). */
enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case SUCCESSFUL = 'SUCCESSFUL';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';

    /** The money moved successfully. */
    public function isSuccessful(): bool
    {
        return $this === self::SUCCESSFUL;
    }

    /** No further status change is expected. */
    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESSFUL, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }
}
