<?php

declare(strict_types=1);

namespace Mulla\Enums;

/** Status of a charge (collection). Starts PENDING; expires after 10 minutes without approval. */
enum ChargeStatus: string
{
    case PENDING = 'PENDING';
    case SUCCESSFUL = 'SUCCESSFUL';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';

    /** The money moved successfully. */
    public function isSuccessful(): bool
    {
        return $this === self::SUCCESSFUL;
    }

    /** No further status change is expected. */
    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESSFUL, self::FAILED, self::EXPIRED], true);
    }
}
