<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Environment\Environment;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\MullaClient;
use Mulla\Services\ChargeService;
use Mulla\Support\Uuid;

final class ClientConfigTest extends TestCase
{
    public function testDetectsEnvironmentFromKey(): void
    {
        $client = $this->client();
        self::assertSame(Environment::Test, $client->environment());
        self::assertFalse($client->isLive());
        self::assertSame(self::BASE, $client->baseUrl());
    }

    public function testRejectsPublicOrMalformedKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sk_live_');
        new MullaClient('pk_test_abc_def', ['baseUrl' => self::BASE]);
    }

    public function testRequiresABaseUrlUntilOfficialHostsAreKnown(): void
    {
        putenv('MULLA_BASE_URL');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MULLA_BASE_URL');
        new MullaClient(self::KEY);
    }

    public function testReadsBaseUrlFromEnvironmentVariable(): void
    {
        putenv('MULLA_BASE_URL=https://env.mulla.test/gateway/v1/');
        try {
            self::assertSame('https://env.mulla.test/gateway/v1', (new MullaClient(self::KEY))->baseUrl());
        } finally {
            putenv('MULLA_BASE_URL');
        }
    }

    public function testUnknownOptionsFailLoudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base_url');
        new MullaClient(self::KEY, ['base_url' => self::BASE]);
    }

    public function testRejectsUnsupportedLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MullaClient(self::KEY, ['baseUrl' => self::BASE, 'lang' => 'de']);
    }

    public function testServicesAreLazyAndReused(): void
    {
        $client = $this->client();
        self::assertInstanceOf(ChargeService::class, $client->charges);
        self::assertSame($client->charges, $client->charges);
    }

    public function testUnknownServiceExplainsAlternatives(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('charges');
        $this->client()->refunds;
    }

    public function testDebugInfoNeverLeaksTheSecret(): void
    {
        $dump = print_r($this->client(), true);
        self::assertStringNotContainsString('s3cr3tRandomPart', $dump);
        self::assertStringContainsString('sk_test_AbC123_****', $dump);
    }

    public function testDefaultDeviceIdIsStableUuidV4(): void
    {
        $a = $this->client()->config()->deviceId;
        $b = $this->client()->config()->deviceId;
        self::assertTrue(Uuid::isValidV4($a));
        self::assertSame($a, $b);
    }
}
