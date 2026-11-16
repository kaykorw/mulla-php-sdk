<?php

declare(strict_types=1);

namespace Mulla\Webhooks;

use Mulla\Enums\WebhookEventType;

/**
 * A verified webhook event.
 *
 *     $event->type          "charge.success"
 *     $event->eventType     WebhookEventType::CHARGE_SUCCESS (null for unknown future events)
 *     $event->id            unique event id: use it to ignore duplicate deliveries
 *     $event->data          the raw payload data
 *     $event->objectId()    id of the charge / payout / invoice concerned
 */
final class Event implements \JsonSerializable
{
    public readonly ?WebhookEventType $eventType;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
    ) {
        $this->eventType = WebhookEventType::tryFrom($type);
    }

    /**
     * @param array<string, mixed> $payload decoded {event, event_id, data}
     */
    public static function fromPayload(array $payload): self
    {
        $data = $payload['data'] ?? [];

        return new self(
            id: is_scalar($payload['event_id'] ?? null) ? (string) $payload['event_id'] : '',
            type: is_scalar($payload['event'] ?? null) ? (string) $payload['event'] : '',
            data: is_array($data) ? $data : [],
        );
    }

    public function is(WebhookEventType|string $type): bool
    {
        return $this->type === ($type instanceof WebhookEventType ? $type->value : $type);
    }

    public function isTest(): bool
    {
        return $this->eventType === WebhookEventType::WEBHOOK_TEST;
    }

    /** Id of the charge, payout or invoice the event is about. */
    public function objectId(): ?string
    {
        foreach (['charge', 'payout', 'invoice'] as $key) {
            $value = $this->data[$key] ?? null;
            if (is_scalar($value)) {
                return (string) $value;
            }
            if (is_array($value) && isset($value['id']) && is_scalar($value['id'])) {
                return (string) $value['id'];
            }
        }

        return null;
    }

    public function reference(): ?string
    {
        return $this->string('reference');
    }

    public function transactionId(): ?string
    {
        return $this->string('transaction_id');
    }

    public function status(): ?string
    {
        return $this->string('status');
    }

    public function currency(): ?string
    {
        return $this->string('currency');
    }

    public function amount(): int|float|null
    {
        $value = $this->data['amount'] ?? null;
        if (!is_numeric($value)) {
            return null;
        }
        $f = (float) $value;

        return floor($f) === $f ? (int) $f : $f;
    }

    /**
     * Your metadata from the original charge/payout, when the server includes it.
     *
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        $value = $this->data['metadata'] ?? null;

        return is_array($value) ? $value : null;
    }

    /** @return array{event: string, event_id: string, data: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return ['event' => $this->type, 'event_id' => $this->id, 'data' => $this->data];
    }

    private function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
