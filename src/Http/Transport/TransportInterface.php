<?php

declare(strict_types=1);

namespace Mulla\Http\Transport;

use Mulla\Exceptions\ConnectionException;
use Mulla\Http\Request;
use Mulla\Http\Response;

/**
 * Adapter target: anything that can send an HTTP request.
 *
 * Implement this to plug Mulla into your own HTTP stack
 * (e.g. WordPress `wp_remote_request`). Error statuses must be returned
 * as a Response, not thrown; only network failures throw.
 */
interface TransportInterface
{
    /**
     * @throws ConnectionException when no HTTP response could be obtained
     */
    public function send(Request $request): Response;
}
