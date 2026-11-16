<?php

declare(strict_types=1);

namespace Mulla\Webhooks\Verifier;

use Mulla\Exceptions\SignatureVerificationException;

/**
 * Timestamped scheme with replay protection:
 *   x-webhook-timestamp:    unix seconds
 *   x-webhook-signature-v2: hex( HMAC-SHA512( "{timestamp}.{rawBody}", secret ) )
 *
 * Rejects events older (or newer) than $toleranceSeconds.
 */
final class V2SignatureVerifier implements SignatureVerifierInterface
{
    public const SIGNATURE_HEADER = 'x-webhook-signature-v2';
    public const TIMESTAMP_HEADER = 'x-webhook-timestamp';

    /** @var callable(): int */
    private $clock;

    /**
     * @param (callable(): int)|null $clock returns the current unix time (for tests)
     */
    public function __construct(
        private readonly int $toleranceSeconds = 300,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha512', $timestamp . '.' . $payload, $secret);
    }

    public function supports(array $headers): bool
    {
        return isset($headers[self::SIGNATURE_HEADER]);
    }

    public function verify(string $payload, array $headers, string $secret): void
    {
        $timestamp = $headers[self::TIMESTAMP_HEADER] ?? '';
        if (!ctype_digit($timestamp)) {
            throw new SignatureVerificationException('Webhook timestamp header is missing or invalid.');
        }

        if ($this->toleranceSeconds > 0 && abs(($this->clock)() - (int) $timestamp) > $this->toleranceSeconds) {
            throw new SignatureVerificationException(sprintf(
                'Webhook timestamp is outside the %d second tolerance (possible replay, or the server clock is wrong).',
                $this->toleranceSeconds,
            ));
        }

        $given = strtolower(trim($headers[self::SIGNATURE_HEADER] ?? ''));
        if ($given === '' || !hash_equals(self::sign($payload, $secret, (int) $timestamp), $given)) {
            throw new SignatureVerificationException(
                'Webhook signature does not match. Make sure you pass the raw request body '
                . '(not re-encoded JSON) and the secret of your current webhook endpoint.',
            );
        }
    }
}
