<?php

declare(strict_types=1);

namespace Mulla\Http;

use Mulla\Exceptions\InvalidArgumentException;

/**
 * Per-request overrides. Every service method accepts these as the last argument,
 * either as an instance or as an array:
 *
 *     $mulla->charges->initiate($params, ['idempotency_key' => $orderUuid, 'lang' => 'fr']);
 */
final class RequestOptions
{
    /**
     * @param array<string, string> $headers extra headers for this request only
     */
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $lang = null,
        public readonly ?float $timeout = null,
        public readonly array $headers = [],
    ) {}

    /**
     * @param RequestOptions|array{idempotency_key?: string, lang?: string, timeout?: float, headers?: array<string, string>}|null $options
     */
    public static function from(self|array|null $options): self
    {
        if ($options instanceof self) {
            return $options;
        }
        if ($options === null || $options === []) {
            return new self();
        }

        $unknown = array_diff(array_keys($options), ['idempotency_key', 'lang', 'timeout', 'headers']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown request option(s): ' . implode(', ', $unknown) . '.');
        }

        return new self(
            idempotencyKey: $options['idempotency_key'] ?? null,
            lang: $options['lang'] ?? null,
            timeout: isset($options['timeout']) ? (float) $options['timeout'] : null,
            headers: $options['headers'] ?? [],
        );
    }
}
