<?php

declare(strict_types=1);

namespace Mulla\Webhooks;

use Mulla\Enums\WebhookEventType;

/**
 * Observer: register listeners per event type and dispatch verified events to them.
 *
 *     $dispatcher = (new EventDispatcher())
 *         ->on(WebhookEventType::CHARGE_SUCCESS, fn(Event $e) => markOrderPaid($e->metadata()['order_id']))
 *         ->on(WebhookEventType::CHARGE_FAILED, fn(Event $e) => markOrderFailed($e))
 *         ->onAny(fn(Event $e) => logger()->info($e->type));
 *
 *     $dispatcher->dispatch($event);
 */
final class EventDispatcher
{
    private const ANY = '*';

    /** @var array<string, list<callable(Event): mixed>> */
    private array $listeners = [];

    /** @param callable(Event): mixed $listener */
    public function on(WebhookEventType|string $type, callable $listener): self
    {
        $key = $type instanceof WebhookEventType ? $type->value : $type;
        $this->listeners[$key][] = $listener;

        return $this;
    }

    /** @param callable(Event): mixed $listener */
    public function onAny(callable $listener): self
    {
        return $this->on(self::ANY, $listener);
    }

    public function hasListeners(WebhookEventType|string $type): bool
    {
        $key = $type instanceof WebhookEventType ? $type->value : $type;

        return isset($this->listeners[$key]) || isset($this->listeners[self::ANY]);
    }

    /** @return int number of listeners called */
    public function dispatch(Event $event): int
    {
        $listeners = [...($this->listeners[$event->type] ?? []), ...($this->listeners[self::ANY] ?? [])];
        foreach ($listeners as $listener) {
            $listener($event);
        }

        return count($listeners);
    }
}
