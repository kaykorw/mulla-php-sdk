<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 409: the request conflicts with current state (e.g. payment already in progress, webhook already exists).
 */
class ConflictException extends ApiException {}
