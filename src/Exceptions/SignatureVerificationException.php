<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * A webhook signature was missing, invalid, or outside the allowed timestamp tolerance.
 */
class SignatureVerificationException extends MullaException
{
}
