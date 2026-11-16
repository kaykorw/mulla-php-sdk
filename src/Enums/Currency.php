<?php

declare(strict_types=1);

namespace Mulla\Enums;

/** Currency codes known to Mulla. Payments currently settle in RWF. */
enum Currency: string
{
    case RWF = 'RWF';
    case USD = 'USD';
    case UGX = 'UGX';
    case KES = 'KES';
    case TZS = 'TZS';
    case BIF = 'BIF';
}
