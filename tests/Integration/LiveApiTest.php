<?php

declare(strict_types=1);

namespace Mulla\Tests\Integration;

use Mulla\Enums\Provider;
use Mulla\Exceptions\NotFoundException;
use Mulla\MullaClient;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real Mulla server (local docker stack or sandbox). Skipped unless configured:
 *
 *   MULLA_SECRET_KEY=sk_test_... composer test:integration                       (sandbox, mfs.kayko.dev)
 *   MULLA_SECRET_KEY=sk_test_... MULLA_BASE_URL=http://localhost:5605/gateway composer test:integration   (local stack)
 *
 * Optional: MULLA_TEST_PHONE=2507... also initiates a real (sandbox) charge.
 */
final class LiveApiTest extends TestCase
{
    private MullaClient $mulla;

    protected function setUp(): void
    {
        $key = getenv('MULLA_SECRET_KEY');
        if (!is_string($key) || $key === '') {
            self::markTestSkipped('Set MULLA_SECRET_KEY to run integration tests (MULLA_BASE_URL is optional).');
        }

        $this->mulla = new MullaClient($key, ['maxRetries' => 1]);
    }

    public function testListsCharges(): void
    {
        $page = $this->mulla->charges->list(['limit' => 5]);

        self::assertGreaterThanOrEqual(0, $page->total);
        self::assertLessThanOrEqual(5, count($page));
    }

    public function testUnknownChargeRaisesNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->mulla->charges->retrieve('00000000-0000-4000-8000-000000000000');
    }

    public function testCreatesCheckoutSession(): void
    {
        $session = $this->mulla->charges->createCheckout([
            'amount' => 100,
            'reason' => 'mulla-php integration test',
            'success_url' => 'http://localhost/success',
            'cancel_url' => 'http://localhost/cancel',
            'metadata' => ['source' => 'mulla-php-tests'],
        ]);

        self::assertNotEmpty($session->checkoutUrl);
        self::assertNotEmpty($session->token);

        $public = $this->mulla->checkout->retrieve($session->id, (string) $session->token);
        self::assertSame($session->id, $public->id);
    }

    public function testInitiatesChargeWhenPhoneProvided(): void
    {
        $phone = getenv('MULLA_TEST_PHONE');
        if (!is_string($phone) || $phone === '') {
            self::markTestSkipped('Set MULLA_TEST_PHONE to initiate a real sandbox charge.');
        }

        $charge = $this->mulla->charges->initiate([
            'provider' => Provider::MTN_RW,
            'amount' => 100,
            'phone_number' => $phone,
            'reason' => 'mulla-php integration test',
        ]);

        self::assertTrue($charge->isPending());
        self::assertSame($charge->id, $this->mulla->charges->retrieve($charge->id)->id);
    }
}
