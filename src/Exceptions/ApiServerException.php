<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 5xx: Mulla had an internal error. Safe to retry with the same idempotency key.
 */
class ApiServerException extends ApiException {}
