<?php

declare(strict_types=1);

namespace Mulla\Webhooks\Verifier;

use Mulla\Exceptions\SignatureVerificationException;

/**
 * Current Mulla scheme:
 *   x-webhook-signature: hex( HMAC-SHA512( rawBody, secret ) )
 */
final class V1SignatureVerifier implements SignatureVerifierInterface
{
    public const HEADER = 'x-webhook-signature';

    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha512', $payload, $secret);
    }

    public function supports(array $headers): bool
    {
        return isset($headers[self::HEADER]);
    }

    public function verify(string $payload, array $headers, string $secret): void
    {
        $given = strtolower(trim($headers[self::HEADER] ?? ''));
        if ($given === '' || !hash_equals(self::sign($payload, $secret), $given)) {
            throw new SignatureVerificationException(
                'Webhook signature does not match. Make sure you pass the raw request body '
                . '(not re-encoded JSON) and the secret of your current webhook endpoint.',
            );
        }
    }
}
