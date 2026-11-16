<?php

/**
 * Webhook endpoint (plain PHP). Point your Mulla webhook URL here.
 * Always verify the signature with the RAW body before trusting the event.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mulla\Enums\WebhookEventType;
use Mulla\Exceptions\SignatureVerificationException;
use Mulla\Webhooks\Event;
use Mulla\Webhooks\EventDispatcher;
use Mulla\Webhooks\Webhook;

$payload = (string) file_get_contents('php://input');

try {
    $event = Webhook::constructEvent($payload, Webhook::headersFromGlobals(), (string) getenv('MULLA_WEBHOOK_SECRET'));
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

// Mulla retries deliveries: ignore events you already processed.
// if (alreadyProcessed($event->id)) { http_response_code(200); exit; }

$dispatcher = (new EventDispatcher())
    ->on(WebhookEventType::CHARGE_SUCCESS, function (Event $event): void {
        // markOrderPaid(chargeId: $event->objectId(), reference: $event->reference());
    })
    ->on(WebhookEventType::CHARGE_FAILED, function (Event $event): void {
        // markOrderFailed($event->objectId());
    })
    ->on(WebhookEventType::PAYOUT_SUCCESS, function (Event $event): void {
        // markPayoutCompleted($event->objectId());
    });

$dispatcher->dispatch($event);

http_response_code(200);
echo 'ok';
