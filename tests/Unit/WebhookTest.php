<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Enums\WebhookEventType;
use Mulla\Exceptions\SignatureVerificationException;
use Mulla\Testing\WebhookSigner;
use Mulla\Webhooks\Event;
use Mulla\Webhooks\EventDispatcher;
use Mulla\Webhooks\Verifier\V1SignatureVerifier;
use Mulla\Webhooks\Verifier\V2SignatureVerifier;
use Mulla\Webhooks\Webhook;

final class WebhookTest extends TestCase
{
    /** @return array{secret: string, body: string, v1_signature: string, timestamp: int, v2_signature: string} */
    private static function vector(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/webhooks/charge_success.json'), true);
    }

    public function testVerifiesV1SignatureProducedByTheServer(): void
    {
        $v = self::vector();

        $event = Webhook::constructEvent($v['body'], ['X-Webhook-Signature' => $v['v1_signature']], $v['secret']);

        self::assertSame(WebhookEventType::CHARGE_SUCCESS, $event->eventType);
        self::assertSame('4f6c2a8e-1b3d-4e5f-9a7b-0c1d2e3f4a5b', $event->id);
        self::assertSame('9b2e5c1a-7d4f-4a3b-8c6e-1f2a3b4c5d6e', $event->objectId());
        self::assertSame('CHG20261116ABCD', $event->reference());
        self::assertSame(5000, $event->amount());
        self::assertSame('SUCCESSFUL', $event->status());
    }

    public function testAcceptsSignatureAsPlainString(): void
    {
        $v = self::vector();
        self::assertSame('charge.success', Webhook::constructEvent($v['body'], $v['v1_signature'], $v['secret'])->type);
    }

    public function testAcceptsFrameworkStyleArrayHeaders(): void
    {
        $v = self::vector();
        $event = Webhook::constructEvent($v['body'], ['x-webhook-signature' => [$v['v1_signature']]], $v['secret']);
        self::assertTrue($event->is(WebhookEventType::CHARGE_SUCCESS));
    }

    public function testRejectsTamperedBody(): void
    {
        $v = self::vector();
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent(str_replace('5000', '9000', $v['body']), $v['v1_signature'], $v['secret']);
    }

    public function testRejectsWrongSecret(): void
    {
        $v = self::vector();
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($v['body'], $v['v1_signature'], 'another-secret');
    }

    public function testRejectsMissingHeaders(): void
    {
        $v = self::vector();
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('x-webhook-signature');
        Webhook::constructEvent($v['body'], [], $v['secret']);
    }

    public function testVerifiesV2WithinTolerance(): void
    {
        $v = self::vector();
        $webhook = new Webhook($v['secret'], verifiers: [new V2SignatureVerifier(300, fn () => $v['timestamp'] + 100), new V1SignatureVerifier()]);

        $event = $webhook->parse($v['body'], [
            'x-webhook-timestamp' => (string) $v['timestamp'],
            'x-webhook-signature-v2' => $v['v2_signature'],
        ]);

        self::assertSame('charge.success', $event->type);
    }

    public function testRejectsReplayedV2Event(): void
    {
        $v = self::vector();
        $webhook = new Webhook($v['secret'], verifiers: [new V2SignatureVerifier(300, fn () => $v['timestamp'] + 301)]);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('tolerance');
        $webhook->parse($v['body'], ['x-webhook-timestamp' => (string) $v['timestamp'], 'x-webhook-signature-v2' => $v['v2_signature']]);
    }

    public function testInvalidV2CannotFallBackToV1(): void
    {
        $v = self::vector();
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($v['body'], [
            'x-webhook-signature' => $v['v1_signature'],
            'x-webhook-timestamp' => (string) time(),
            'x-webhook-signature-v2' => str_repeat('0', 128),
        ], $v['secret']);
    }

    public function testSignerProducesVerifiableRequests(): void
    {
        ['body' => $body, 'headers' => $headers] = WebhookSigner::event(WebhookEventType::PAYOUT_SUCCESS, ['payout' => 'po_1', 'metadata' => ['ref' => 'x']], 'whsec');

        $event = Webhook::constructEvent($body, $headers, 'whsec');

        self::assertSame('po_1', $event->objectId());
        self::assertSame(['ref' => 'x'], $event->metadata());
        self::assertSame('payout.success', Webhook::constructEvent($body, WebhookSigner::v1Headers($body, 'whsec'), 'whsec')->type);
    }

    public function testDispatcherCallsTypedAndWildcardListeners(): void
    {
        $calls = [];
        $dispatcher = (new EventDispatcher())
            ->on(WebhookEventType::CHARGE_SUCCESS, function (Event $e) use (&$calls): void {
                $calls[] = 'success:' . $e->reference();
            })
            ->on('charge.failed', function () use (&$calls): void {
                $calls[] = 'failed';
            })
            ->onAny(function (Event $e) use (&$calls): void {
                $calls[] = 'any:' . $e->type;
            });

        $count = $dispatcher->dispatch(new Event('e1', 'charge.success', ['reference' => 'CHG1']));

        self::assertSame(2, $count);
        self::assertSame(['success:CHG1', 'any:charge.success'], $calls);
        self::assertNull((new Event('e2', 'something.new', []))->eventType);
    }
}
