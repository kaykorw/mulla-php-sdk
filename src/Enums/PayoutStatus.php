<?php

declare(strict_types=1);

namespace Mulla\Enums;

enum PayoutStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case SCHEDULED = 'SCHEDULED';
    case EXPIRED = 'EXPIRED';

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::REJECTED, self::CANCELLED, self::EXPIRED], true);
    }
}
