<?php

declare(strict_types=1);

namespace Mulla\Exceptions;

/**
 * The SDK was used incorrectly (bad configuration or arguments). Raised before any request is sent.
 */
class InvalidArgumentException extends MullaException {}
