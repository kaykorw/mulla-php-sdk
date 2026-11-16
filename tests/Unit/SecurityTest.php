<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Exceptions\ApiServerException;
use Mulla\Exceptions\ConnectionException;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\MullaClient;
use Mulla\MullaConfig;
use Mulla\Testing\FakeTransport;

final class SecurityTest extends TestCase
{
    private const CHARGE = ['provider' => 'MTN_RW', 'amount' => 100, 'phone_number' => '250788000000'];

    // --- Retries must never duplicate a write -------------------------------------------

    public function testWriteIsNotRetriedAfterServerError(): void
    {
        $client = $this->client();
        $this->fake->pushError(500, 'INTERNAL');

        $this->expectException(ApiServerException::class);
        try {
            $client->charges->initiate(self::CHARGE);
        } finally {
            self::assertCount(1, $this->fake->requests());
        }
    }

    public function testWriteIsNotRetriedAfterGatewayTimeout(): void
    {
        $client = $this->client();
        $this->fake->pushError(408, 'REQUEST_TIMEOUT')->pushJson(self::charge());

        try {
            $client->payouts->initiate(self::CHARGE + ['reason' => 'Supplier']);
            self::fail('Expected exception');
        } catch (\Mulla\Exceptions\ApiException) {
        }
        self::assertCount(1, $this->fake->requests());
    }

    public function testWriteIsRetriedWhenServerRejectedItUpFront(): void
    {
        $client = $this->client();
        $this->fake->pushError(503, 'SERVICE_UNAVAILABLE')->pushJson(self::charge());

        $client->charges->initiate(self::CHARGE);

        self::assertCount(2, $this->fake->requests());
    }

    public function testWriteIsNotRetriedWhenRequestMayHaveBeenSent(): void
    {
        $client = $this->client();
        $this->fake->pushConnectionError('Operation timed out', true)->pushJson(self::charge());

        try {
            $client->charges->initiate(self::CHARGE);
            self::fail('Expected exception');
        } catch (ConnectionException $e) {
            self::assertTrue($e->requestMayHaveBeenSent());
        }
        self::assertCount(1, $this->fake->requests());
    }

    public function testWriteIsRetriedWhenConnectionWasNeverMade(): void
    {
        $client = $this->client();
        $this->fake->pushConnectionError('Could not resolve host', false)->pushJson(self::charge());

        $client->charges->initiate(self::CHARGE);

        self::assertCount(2, $this->fake->requests());
    }

    public function testReadIsRetriedAfterServerErrorAndTimeout(): void
    {
        $client = $this->client();
        $this->fake->pushError(500, 'INTERNAL')->pushConnectionError('timed out', true)->pushJson(self::charge());

        $client->charges->retrieve('abc');

        self::assertCount(3, $this->fake->requests());
    }

    // --- Secrets stay out of logs and dumps ----------------------------------------------

    public function testCheckoutTokenIsRedactedFromLogs(): void
    {
        $logs = [];
        $client = $this->client(['logger' => static function (string $level, string $message, array $context) use (&$logs): void {
            $logs[] = $message . ' ' . json_encode($context);
        }]);
        $this->fake->pushJson(['id' => 'cs_1', 'status' => 'PENDING']);

        $client->checkout->retrieve('cs_1', 'tok_SECRET123');

        self::assertStringContainsString('tok_SECRET123', (string) $this->fake->lastRequest()?->url);
        self::assertNotEmpty($logs);
        foreach ($logs as $line) {
            self::assertStringNotContainsString('tok_SECRET123', $line);
            self::assertStringContainsString('token=***', $line);
        }
    }

    public function testDumpsNeverContainTheSecretKey(): void
    {
        $client = $this->client();
        $this->fake->pushJson(self::charge());
        $client->charges->retrieve('abc');

        $secret = 's3cr3tRandomPart';
        self::assertStringNotContainsString($secret, print_r($client, true));
        self::assertStringNotContainsString($secret, print_r($client->config(), true));
        self::assertStringNotContainsString($secret, print_r($client->charges, true));
        self::assertStringNotContainsString($secret, print_r($this->fake->lastRequest(), true));
    }

    // --- Base URL ------------------------------------------------------------------------

    /** @return iterable<string, array{string}> */
    public static function unsafeUrls(): iterable
    {
        yield 'plain http' => ['http://api.example.com/gateway'];
        yield 'credentials' => ['https://user:pass@api.example.com/gateway'];
        yield 'query string' => ['https://api.example.com/gateway?x=1'];
        yield 'fragment' => ['https://api.example.com/gateway#x'];
        yield 'ftp' => ['ftp://api.example.com/gateway'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'not a url' => ['mulla'];
    }

    /** @dataProvider unsafeUrls */
    public function testRejectsUnsafeBaseUrls(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        MullaConfig::create(self::KEY, ['baseUrl' => $url, 'transport' => new FakeTransport()]);
    }

    /** @return iterable<string, array{string}> */
    public static function localUrls(): iterable
    {
        yield 'localhost' => ['http://localhost:5605/gateway'];
        yield 'loopback' => ['http://127.0.0.1:5605/gateway'];
        yield 'ipv6 loopback' => ['http://[::1]:5605/gateway'];
        yield 'docker' => ['http://host.docker.internal:5605/gateway'];
        yield 'dev tld' => ['http://mulla.test/gateway'];
    }

    /** @dataProvider localUrls */
    public function testAllowsPlainHttpForLocalDevelopment(string $url): void
    {
        $config = MullaConfig::create(self::KEY, ['baseUrl' => $url, 'transport' => new FakeTransport()]);

        self::assertStringEndsWith('/gateway/v1', $config->baseUrl);
    }

    // --- Request input -------------------------------------------------------------------

    public function testRejectsHeaderInjection(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);
        try {
            $client->charges->retrieve('abc', ['headers' => ['x-note' => "ok\r\nx-api-key: stolen"]]);
        } finally {
            self::assertCount(0, $this->fake->requests());
        }
    }

    public function testRejectsInvalidHeaderName(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);
        $client->charges->retrieve('abc', ['headers' => ["x-bad\nname" => 'v']]);
    }

    public function testIdempotencyKeyMustBeAUuid(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('idempotency_key');
        $client->charges->initiate(self::CHARGE, ['idempotency_key' => 'order-1001']);
    }

    public function testRejectsDotSegmentIds(): void
    {
        $client = $this->client();

        foreach (['.', '..'] as $id) {
            try {
                $client->charges->retrieve($id);
                self::fail("Expected exception for id {$id}");
            } catch (InvalidArgumentException) {
            }
        }
        self::assertCount(0, $this->fake->requests());
    }

    public function testInvalidUtf8IsReportedAsAMullaError(): void
    {
        $client = $this->client();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UTF-8');
        $client->charges->initiate(self::CHARGE + ['reason' => "bad \xB1\x31"]);
    }

    // --- Invoice live events ----------------------------------------------------------------

    public function testInvoiceEventsUrl(): void
    {
        $client = $this->client();

        self::assertSame(self::BASE . '/invoice/inv%2F1/events', $client->invoices->eventsUrl('inv/1'));
        self::assertCount(0, $this->fake->requests());
    }

    public function testClientIsUsableWithDefaultTransport(): void
    {
        self::assertInstanceOf(MullaClient::class, new MullaClient(self::KEY));
    }
}
