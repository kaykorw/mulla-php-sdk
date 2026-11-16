<?php

declare(strict_types=1);

namespace Mulla\Enums;

enum ChargeStatus: string
{
    case PENDING = 'PENDING';
    case SUCCESSFUL = 'SUCCESSFUL';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';

    public function isSuccessful(): bool
    {
        return $this === self::SUCCESSFUL;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESSFUL, self::FAILED, self::EXPIRED], true);
    }
}
