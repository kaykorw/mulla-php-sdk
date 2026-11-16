<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 400: the request was rejected (validation failed, minimum amount, bad parameters).
 */
class InvalidRequestException extends ApiException
{
}
