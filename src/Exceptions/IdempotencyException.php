<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 409 IDEMPOTENCY_KEY_*: this idempotency key was already used, or the original request is still in progress. Fetch the resource instead of retrying.
 */
class IdempotencyException extends ConflictException {}
