<?php

declare(strict_types=1);

namespace Mulla\Webhooks;

use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Exceptions\SignatureVerificationException;
use Mulla\Webhooks\Verifier\SignatureVerifierInterface;
use Mulla\Webhooks\Verifier\V1SignatureVerifier;
use Mulla\Webhooks\Verifier\V2SignatureVerifier;

/**
 * Verifies incoming webhook requests and turns them into Events.
 *
 * Quick use (plain PHP):
 *
 *     $event = Webhook::constructEvent(file_get_contents('php://input'), Webhook::headersFromGlobals(), $secret);
 *
 * Verifiers are tried in order; the first one whose headers are present must pass.
 * The timestamped v2 scheme always wins over v1 when both are sent, so an attacker
 * cannot strip v2 headers to bypass replay protection once your server sends them.
 */
final class Webhook
{
    /** @var list<SignatureVerifierInterface> */
    private readonly array $verifiers;

    /**
     * @param list<SignatureVerifierInterface>|null $verifiers defaults to [v2 (timestamped), v1]
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
        int $toleranceSeconds = 300,
        ?array $verifiers = null,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('Webhook secret is empty. Use the secret shown when you created or rotated your webhook endpoint.');
        }
        $this->verifiers = $verifiers ?? [new V2SignatureVerifier($toleranceSeconds), new V1SignatureVerifier()];
    }

    /**
     * @param array<string, string|list<string>>|string $headers request headers, or the x-webhook-signature value
     *
     * @throws SignatureVerificationException
     */
    public static function constructEvent(string $payload, array|string $headers, #[\SensitiveParameter] string $secret, int $toleranceSeconds = 300): Event
    {
        return (new self($secret, $toleranceSeconds))->parse($payload, $headers);
    }

    /**
     * @param array<string, string|list<string>>|string $headers
     *
     * @throws SignatureVerificationException
     */
    public function parse(string $payload, array|string $headers): Event
    {
        $normalized = is_string($headers)
            ? [V1SignatureVerifier::HEADER => $headers]
            : self::normalizeHeaders($headers);

        $this->verify($payload, $normalized);

        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || !isset($decoded['event'])) {
            throw new SignatureVerificationException('Webhook body is not a valid Mulla event.');
        }

        /** @var array<string, mixed> $decoded */
        return Event::fromPayload($decoded);
    }

    /**
     * Reads the webhook headers from $_SERVER (plain PHP without a framework).
     *
     * @return array<string, string>
     */
    public static function headersFromGlobals(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_') && is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        return $headers;
    }

    /** @param array<string, string> $headers */
    private function verify(string $payload, array $headers): void
    {
        foreach ($this->verifiers as $verifier) {
            if ($verifier->supports($headers)) {
                $verifier->verify($payload, $headers, $this->secret);

                return;
            }
        }

        throw new SignatureVerificationException(
            'No Mulla signature header found (expected "x-webhook-signature"). Is this request really from Mulla?',
        );
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $out[strtolower((string) $name)] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }

        return $out;
    }
}
