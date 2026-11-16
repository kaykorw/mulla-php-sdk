<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 401: missing, invalid, expired or revoked API key (or invalid checkout token).
 */
class AuthenticationException extends ApiException
{
}
