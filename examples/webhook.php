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

$dispatcher = (new EventDispatcher())
    ->on(WebhookEventType::CHARGE_SUCCESS, function (Event $event): void {
        error_log('Paid: ' . $event->objectId() . ' ' . $event->reference());
    })
    ->on(WebhookEventType::CHARGE_FAILED, function (Event $event): void {
        error_log('Charge failed: ' . $event->objectId());
    })
    ->on(WebhookEventType::PAYOUT_SUCCESS, function (Event $event): void {
        error_log('Payout completed: ' . $event->objectId());
    });

$dispatcher->dispatch($event);

http_response_code(200);
echo 'ok';
