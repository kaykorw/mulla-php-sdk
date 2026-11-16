<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 403: the key lacks a scope, the IP is not allowed, the key environment does not match the server, or the merchant is not active.
 */
class PermissionException extends ApiException
{
}
