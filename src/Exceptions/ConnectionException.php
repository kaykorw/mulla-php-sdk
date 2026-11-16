<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * The API could not be reached (DNS, TLS, timeout, connection reset). No HTTP response was received.
 */
class ConnectionException extends MullaException {}
