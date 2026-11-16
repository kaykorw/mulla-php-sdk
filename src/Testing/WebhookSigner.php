<?php

declare(strict_types=1);

namespace Mulla\Testing;

use Mulla\Enums\WebhookEventType;
use Mulla\Webhooks\Verifier\V1SignatureVerifier;
use Mulla\Webhooks\Verifier\V2SignatureVerifier;

/**
 * Builds correctly signed webhook requests so you can test your webhook handler
 * without calling Mulla.
 *
 *     ['body' => $body, 'headers' => $headers] = WebhookSigner::event(
 *         WebhookEventType::CHARGE_SUCCESS,
 *         ['charge' => 'chg_1', 'reference' => 'CHG123', 'amount' => 5000, 'currency' => 'RWF', 'status' => 'SUCCESSFUL'],
 *         'whsec_test',
 *     );
 *     $this->postJson('/mulla/webhook', json_decode($body, true), $headers);
 */
final class WebhookSigner
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{body: string, headers: array<string, string>}
     */
    public static function event(WebhookEventType|string $type, array $data, string $secret, ?string $eventId = null, ?int $timestamp = null): array
    {
        $body = (string) json_encode([
            'event' => $type instanceof WebhookEventType ? $type->value : $type,
            'event_id' => $eventId ?? bin2hex(random_bytes(16)),
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES);

        return ['body' => $body, 'headers' => self::headers($body, $secret, $timestamp)];
    }

    /**
     * Signature headers for an arbitrary raw body (both v1 and v2 schemes).
     *
     * @return array<string, string>
     */
    public static function headers(string $body, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            'content-type' => 'application/json',
            V1SignatureVerifier::HEADER => V1SignatureVerifier::sign($body, $secret),
            V2SignatureVerifier::TIMESTAMP_HEADER => (string) $timestamp,
            V2SignatureVerifier::SIGNATURE_HEADER => V2SignatureVerifier::sign($body, $secret, $timestamp),
        ];
    }

    /**
     * Only the v1 header, exactly like today's Mulla server sends.
     *
     * @return array<string, string>
     */
    public static function v1Headers(string $body, string $secret): array
    {
        return [
            'content-type' => 'application/json',
            V1SignatureVerifier::HEADER => V1SignatureVerifier::sign($body, $secret),
        ];
    }
}
