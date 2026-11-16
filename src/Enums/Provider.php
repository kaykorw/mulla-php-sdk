<?php

declare(strict_types=1);

namespace Mulla\Enums;

/** Mobile money providers supported by Mulla. */
enum Provider: string
{
    case MTN_RW = 'MTN_RW';
    case AIRTEL_RW = 'AIRTEL_RW';

    public function label(): string
    {
        return match ($this) {
            self::MTN_RW => 'MTN Mobile Money',
            self::AIRTEL_RW => 'Airtel Money',
        };
    }
}
