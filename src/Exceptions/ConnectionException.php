<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * The API could not be reached (DNS, TLS, timeout, connection reset). No HTTP response was received.
 *
 * Check requestMayHaveBeenSent(): when true (e.g. the response timed out), Mulla may have
 * processed the request. Verify the outcome before sending a write (charge, payout) again.
 */
class ConnectionException extends MullaException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly bool $requestMayHaveBeenSent = true,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** False only when the connection was never established, so Mulla cannot have seen the request. */
    public function requestMayHaveBeenSent(): bool
    {
        return $this->requestMayHaveBeenSent;
    }
}
