<?php

declare(strict_types=1);

namespace Mulla\Webhooks\Verifier;

use Mulla\Exceptions\SignatureVerificationException;

/**
 * Strategy for one webhook signature scheme.
 */
interface SignatureVerifierInterface
{
    /**
     * Whether the request carries this scheme's headers.
     *
     * @param array<string, string> $headers lower-cased names
     */
    public function supports(array $headers): bool;

    /**
     * @param array<string, string> $headers lower-cased names
     *
     * @throws SignatureVerificationException
     */
    public function verify(string $payload, array $headers, string $secret): void;
}
