<?php

declare(strict_types=1);

namespace Mulla\Params;

/**
 * Anything that can be sent as a request body. Every service method accepts
 * either a ParamsInterface builder or a plain array with the API's snake_case keys.
 */
interface ParamsInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
