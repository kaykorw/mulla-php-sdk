<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Enums\Provider;
use Mulla\Exceptions\ApiServerException;
use Mulla\Exceptions\ConnectionException;
use Mulla\Exceptions\InvalidRequestException;
use Mulla\Exceptions\RateLimitException;
use Mulla\Http\Middleware\MiddlewareInterface;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Params\InitiateChargeParams;
use Mulla\Support\Uuid;

final class RequestPipelineTest extends TestCase
{
    public function testPostSendsAuthContextAndIdempotencyHeaders(): void
    {
        $client = $this->client(['lang' => 'fr', 'app_info' => ['name' => 'my-shop', 'version' => '1.0']]);
        $this->fake->pushJson(self::charge());

        $client->charges->initiate(['provider' => Provider::MTN_RW, 'amount' => 5000, 'phone_number' => '250788000000']);

        $r = $this->fake->lastRequest();
        self::assertNotNull($r);
        self::assertSame('POST', $r->method);
        self::assertSame(self::BASE . '/charges/initiate', $r->url);
        self::assertSame(self::KEY, $r->header('x-api-key'));
        self::assertStringStartsWith('mulla-php/', (string) $r->header('x-platform'));
        self::assertTrue(Uuid::isValidV4((string) $r->header('x-device-id')));
        self::assertTrue(Uuid::isValidV4((string) $r->header('x-idempotency-key')));
        self::assertSame('application/json', $r->header('content-type'));
        self::assertSame('fr', $r->header('x-lang'));
        self::assertStringEndsWith('my-shop/1.0', (string) $r->header('user-agent'));
    }

    public function testGetHasNoIdempotencyKeyAndEncodesQuery(): void
    {
        $client = $this->client();
        $this->fake->pushJson(['data' => [], 'total' => 0, 'page' => 1, 'limit' => 20, 'total_pages' => 0]);

        $client->customers->list(['is_active' => true, 'keyword' => 'jean paul']);

        $r = $this->fake->lastRequest();
        self::assertNotNull($r);
        self::assertNull($r->header('x-idempotency-key'));
        self::assertSame(self::BASE . '/customers?is_active=true&keyword=jean%20paul', $r->url);
    }

    public function testBodyNormalisesEnumsAndDropsNulls(): void
    {
        $client = $this->client();
        $this->fake->pushJson(self::charge());

        $client->charges->initiate(
            InitiateChargeParams::make()->provider(Provider::AIRTEL_RW)->phoneNumber('250733000000')->amount(1000)->metadata(['order_id' => 7, 'note' => null]),
        );

        self::assertSame(
            ['provider' => 'AIRTEL_RW', 'phone_number' => '250733000000', 'amount' => 1000, 'metadata' => ['order_id' => 7]],
            $this->fake->lastBody(),
        );
    }

    public function testBuilderReportsMissingFieldsBeforeSending(): void
    {
        $client = $this->client();
        try {
            $client->charges->initiate(InitiateChargeParams::make()->amount(1000));
            self::fail('Expected exception');
        } catch (\Mulla\Exceptions\InvalidArgumentException $e) {
            self::assertStringContainsString('provider, phone_number', $e->getMessage());
        }
        $this->fake->assertNothingSent();
    }

    public function testPerRequestIdempotencyKeyAndLanguage(): void
    {
        $client = $this->client();
        $this->fake->pushJson(self::charge());

        $client->charges->initiate(
            ['provider' => 'MTN_RW', 'amount' => 100, 'phone_number' => '250788000000'],
            ['idempotency_key' => 'b3f1f8a2-8c1e-4e7a-9d2c-1a2b3c4d5e6f', 'lang' => 'rw'],
        );

        self::assertSame('b3f1f8a2-8c1e-4e7a-9d2c-1a2b3c4d5e6f', $this->fake->lastRequest()?->header('x-idempotency-key'));
        self::assertSame('rw', $this->fake->lastRequest()?->header('x-lang'));
    }

    public function testRetriesUnprocessedWritesReusingTheSameIdempotencyKey(): void
    {
        $client = $this->client();
        $this->fake->pushError(503, 'SERVICE_UNAVAILABLE')->pushConnectionError()->pushJson(self::charge());

        $charge = $client->charges->initiate(['provider' => 'MTN_RW', 'amount' => 100, 'phone_number' => '250788000000']);

        self::assertSame('CHG20261116ABCD', $charge->reference);
        $keys = array_map(static fn(Request $r) => $r->header('x-idempotency-key'), $this->fake->requests());
        self::assertCount(3, $keys);
        self::assertCount(1, array_unique($keys));
        self::assertCount(2, $this->sleeps);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $client = $this->client(['max_retries' => 1]);
        $this->fake->pushError(500, 'INTERNAL')->pushError(500, 'INTERNAL');

        $this->expectException(ApiServerException::class);
        try {
            $client->charges->retrieve('abc');
        } finally {
            $this->fake->assertSentCount(2);
        }
    }

    public function testConnectionErrorSurfacesAfterRetries(): void
    {
        $client = $this->client(['max_retries' => 0]);
        $this->fake->pushConnectionError('Could not resolve host');

        $this->expectException(ConnectionException::class);
        $client->charges->retrieve('abc');
    }

    public function testDoesNotRetryClientErrors(): void
    {
        $client = $this->client();
        $this->fake->pushError(400, 'VALIDATION_FAILED', 'amount must not be less than 10');

        try {
            $client->charges->initiate(['provider' => 'MTN_RW', 'amount' => 1, 'phone_number' => '250788000000']);
            self::fail('Expected exception');
        } catch (InvalidRequestException $e) {
            self::assertSame('VALIDATION_FAILED', $e->getErrorCode());
            self::assertStringContainsString('amount must not be less than 10', $e->getMessage());
        }
        $this->fake->assertSentCount(1);
    }

    public function testLongRateLimitIsNotRetriedAndExposesRetryAfter(): void
    {
        $client = $this->client();
        $this->fake->pushJson(['statusCode' => 429, 'errorCode' => 'RATE_LIMIT_EXCEEDED', 'message' => 'Too many', 'retryAfter' => 540], 429, ['Retry-After' => '540']);

        try {
            $client->charges->list();
            self::fail('Expected exception');
        } catch (RateLimitException $e) {
            self::assertSame(540, $e->getRetryAfter());
        }
        $this->fake->assertSentCount(1);
    }

    public function testCustomMiddlewareRunsForEachAttempt(): void
    {
        $seen = [];
        $middleware = new class ($seen) implements MiddlewareInterface {
            public function __construct(private array &$seen) {}

            public function handle(Request $request, callable $next): Response
            {
                $this->seen[] = $request->attribute('attempt');

                return $next($request->withHeader('x-trace-id', 'trace-1'));
            }
        };
        $client = $this->client(['middleware' => [$middleware]]);
        $this->fake->pushError(502, 'BAD_GATEWAY')->pushJson(self::charge());

        $client->charges->retrieve('abc');

        self::assertSame([1, 2], $seen);
        self::assertSame('trace-1', $this->fake->lastRequest()?->header('x-trace-id'));
    }

    public function testLoggerNeverReceivesTheApiKey(): void
    {
        $logs = [];
        $client = $this->client(['logger' => function (string $level, string $message, array $context) use (&$logs): void {
            $logs[] = [$level, $message, $context];
        }]);
        $this->fake->pushJson(self::charge());

        $client->charges->retrieve('abc');

        self::assertCount(1, $logs);
        self::assertSame('debug', $logs[0][0]);
        self::assertSame(200, $logs[0][2]['status']);
        self::assertStringNotContainsString(self::KEY, json_encode($logs, JSON_THROW_ON_ERROR));
    }
}
