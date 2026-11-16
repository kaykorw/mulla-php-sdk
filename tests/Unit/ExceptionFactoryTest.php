<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Exceptions\ApiException;
use Mulla\Exceptions\ApiServerException;
use Mulla\Exceptions\AuthenticationException;
use Mulla\Exceptions\ConflictException;
use Mulla\Exceptions\ExceptionFactory;
use Mulla\Exceptions\IdempotencyException;
use Mulla\Exceptions\InvalidRequestException;
use Mulla\Exceptions\NotFoundException;
use Mulla\Exceptions\PermissionException;
use Mulla\Exceptions\RateLimitException;
use Mulla\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExceptionFactoryTest extends TestCase
{
    /** @return iterable<string, array{int, string, class-string}> */
    public static function cases(): iterable
    {
        yield 'validation' => [400, 'VALIDATION_FAILED', InvalidRequestException::class];
        yield 'bad key' => [401, 'API_KEY_INVALID', AuthenticationException::class];
        yield 'scope' => [403, 'API_KEY_SCOPE_DENIED', PermissionException::class];
        yield 'not found' => [404, 'CHARGE_NOT_FOUND', NotFoundException::class];
        yield 'idempotency' => [409, 'IDEMPOTENCY_KEY_CONFLICT', IdempotencyException::class];
        yield 'conflict' => [409, 'CHARGE_PAYMENT_IN_PROGRESS', ConflictException::class];
        yield 'rate limit' => [429, 'RATE_LIMIT_EXCEEDED', RateLimitException::class];
        yield 'server' => [502, 'BAD_GATEWAY', ApiServerException::class];
        yield 'other' => [422, 'SOMETHING', ApiException::class];
    }

    /** @param class-string $class */
    #[DataProvider('cases')]
    public function testMapsStatusAndCodeToException(int $status, string $code, string $class): void
    {
        $e = ExceptionFactory::fromResponse(new Response($status, [], (string) json_encode([
            'statusCode' => $status, 'message' => 'msg', 'errorCode' => $code, 'timestamp' => '2026-11-16T10:00:00Z',
        ])));

        self::assertSame($class, $e::class);
        self::assertSame($status, $e->getStatusCode());
        self::assertSame($code, $e->getErrorCode());
    }

    public function testAddsActionableHint(): void
    {
        $e = ExceptionFactory::fromResponse(new Response(403, [], '{"statusCode":403,"message":"Key environment mismatch","errorCode":"API_KEY_ENVIRONMENT_MISMATCH"}'));
        self::assertStringContainsString('[API_KEY_ENVIRONMENT_MISMATCH] Key environment mismatch', $e->getMessage());
        self::assertStringContainsString('sk_test_', $e->getMessage());
    }

    public function testHandlesNestFallbackShapeWithoutErrorCode(): void
    {
        $e = ExceptionFactory::fromResponse(new Response(404, [], '{"statusCode":404,"timestamp":"x","path":"/gateway/v1/x","message":["a","b"]}'));
        self::assertInstanceOf(NotFoundException::class, $e);
        self::assertNull($e->getErrorCode());
        self::assertSame('a; b', $e->getMessage());
    }

    public function testHandlesNonJsonBody(): void
    {
        $e = ExceptionFactory::fromResponse(new Response(503, [], '<html>Bad gateway</html>'));
        self::assertInstanceOf(ApiServerException::class, $e);
        self::assertSame('Mulla API request failed with HTTP 503', $e->getMessage());
        self::assertSame('<html>Bad gateway</html>', $e->getRawBody());
    }
}
