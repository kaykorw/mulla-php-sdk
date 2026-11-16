<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * 404: the resource (charge, payout, invoice, customer...) does not exist for this merchant.
 */
class NotFoundException extends ApiException
{
}
