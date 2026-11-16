<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * Base class for every exception thrown by the SDK.
 * Catch this to handle any Mulla failure in one place.
 */
class MullaException extends \RuntimeException
{
}
