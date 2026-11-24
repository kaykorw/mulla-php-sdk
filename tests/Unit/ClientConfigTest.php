<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Enums\Language;
use Mulla\Environment\Environment;
use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\Http\Retry\ExponentialBackoff;
use Mulla\MullaClient;
use Mulla\Services\ChargeService;
use Mulla\Support\Uuid;
use Mulla\Webhooks\Webhook;

final class ClientConfigTest extends TestCase
{
    private const LIVE_KEY = 'sk_live_AbC123_s3cr3tRandomPart';

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
        new MullaClient(['api_key' => 'pk_test_abc_def', 'base_url' => self::BASE]);
    }

    public function testAcceptsTheKeyAloneAsAShorthand(): void
    {
        self::assertTrue((new MullaClient(self::LIVE_KEY))->isLive());
    }

    public function testNeverReadsEnvironmentVariables(): void
    {
        putenv('MULLA_SECRET_KEY=' . self::LIVE_KEY);
        putenv('MULLA_BASE_URL=https://env.mulla.test/gateway/');
        try {
            self::assertSame('https://mfs.kayko.dev/gateway/v1', (new MullaClient(['api_key' => self::KEY]))->baseUrl());
            $this->expectExceptionMessage('Missing "api_key"');
            new MullaClient(['api_key' => null]);
        } finally {
            putenv('MULLA_SECRET_KEY');
            putenv('MULLA_BASE_URL');
        }
    }

    public function testExplainsAMissingKey(): void
    {
        foreach ([null, false, '', '   '] as $value) {
            try {
                new MullaClient(['api_key' => $value]);
                self::fail('Expected an exception');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('api_key', $e->getMessage());
            }
        }
    }

    public function testRejectsTheOldTwoArgumentCall(): void
    {
        $this->expectExceptionMessage('single options array');
        new MullaClient(self::KEY, ['base_url' => self::BASE]);
    }

    public function testUnsetOptionalValuesUseDefaults(): void
    {
        $client = new MullaClient(['api_key' => self::KEY, 'base_url' => false, 'webhook_secret' => '', 'webhook_url' => null]);
        self::assertSame('https://mfs.kayko.dev/gateway/v1', $client->baseUrl());
        self::assertNull($client->config()->webhookUrl);
        self::assertNull($client->config()->webhookSecret());
    }

    public function testLiveKeysDefaultToTheProductionGateway(): void
    {
        $client = new MullaClient(['api_key' => self::LIVE_KEY]);
        self::assertTrue($client->isLive());
        self::assertSame('https://core.mulla.app/gateway/v1', $client->baseUrl());
    }

    public function testChecksTheEnvironmentOptionAgainstTheKey(): void
    {
        self::assertSame(Environment::Test, (new MullaClient(['api_key' => self::KEY, 'environment' => Environment::TEST]))->environment());
        self::assertTrue((new MullaClient(['api_key' => self::LIVE_KEY, 'environment' => Environment::PRODUCTION]))->isLive());
        self::assertTrue((new MullaClient(['api_key' => self::LIVE_KEY, 'environment' => 'live']))->isLive());

        $cases = [
            [self::LIVE_KEY, Environment::TEST, 'Environment::TEST, but "api_key" is a live'],
            [self::KEY, Environment::PRODUCTION, 'Environment::PRODUCTION, but "api_key" is a test'],
            [self::KEY, 'staging', '"environment" option must be'],
        ];
        foreach ($cases as [$key, $environment, $message]) {
            try {
                new MullaClient(['api_key' => $key, 'environment' => $environment]);
                self::fail('Expected an exception');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testMissingHostForAnEnvironmentIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('live environment');
        new MullaClient([
            'api_key' => self::LIVE_KEY,
            'environment_resolver' => new \Mulla\Environment\DefaultEnvironmentResolver(['live' => null, 'test' => null]),
        ]);
    }

    public function testAcceptsGatewayUrlWithOrWithoutVersion(): void
    {
        $url = static fn(string $base): string => (new MullaClient(['api_key' => self::LIVE_KEY, 'base_url' => $base]))->baseUrl();
        self::assertSame('https://api.example.com/gateway/v1', $url('https://api.example.com/gateway'));
        self::assertSame('https://api.example.com/gateway/v1', $url('https://api.example.com/gateway/'));
        self::assertSame('http://localhost:5605/gateway/v2', $url('http://localhost:5605/gateway/v2'));
    }

    public function testUnknownOptionsFailLoudlyWithASnakeCaseHint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('baseUrl (did you mean "base_url"?)');
        new MullaClient(['api_key' => self::KEY, 'baseUrl' => self::BASE]);
    }

    public function testLanguageAcceptsTheEnumOrItsValue(): void
    {
        self::assertSame('rw', (new MullaClient(['api_key' => self::KEY, 'lang' => Language::RW]))->config()->lang);
        self::assertSame('fr', (new MullaClient(['api_key' => self::KEY, 'lang' => 'fr']))->config()->lang);
        $this->expectException(InvalidArgumentException::class);
        new MullaClient(['api_key' => self::KEY, 'lang' => 'de']);
    }

    public function testRejectsInvalidNumbers(): void
    {
        $cases = [
            ['max_retries', -1],
            ['max_retries', '2'],
            ['timeout', 0],
            ['connect_timeout', INF],
        ];
        foreach ($cases as [$option, $value]) {
            try {
                new MullaClient(['api_key' => self::KEY, $option => $value]);
                self::fail("Expected {$option} to be rejected");
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($option, $e->getMessage());
            }
        }
    }

    public function testRequiresAUuidV4DeviceId(): void
    {
        $id = Uuid::fromSeed('pos-terminal-7');
        self::assertSame($id, (new MullaClient(['api_key' => self::KEY, 'device_id' => $id]))->config()->deviceId);
        $this->expectExceptionMessage('device_id');
        new MullaClient(['api_key' => self::KEY, 'device_id' => 'my-device-id']);
    }

    public function testValidatesWebhookUrl(): void
    {
        $client = new MullaClient(['api_key' => self::KEY, 'webhook_url' => 'https://shop.example/hook']);
        self::assertSame('https://shop.example/hook', $client->config()->webhookUrl);
        foreach (['http://shop.example/hook', 'https://u:p@shop.example/hook', 'nope'] as $url) {
            try {
                new MullaClient(['api_key' => self::KEY, 'webhook_url' => $url]);
                self::fail("Expected {$url} to be rejected");
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('webhook_url', $e->getMessage());
            }
        }
    }

    public function testBuildsAHeaderSafeAppInfo(): void
    {
        $agent = static fn(mixed $info): ?string => (new MullaClient(['api_key' => self::KEY, 'app_info' => $info]))->config()->appInfo;
        self::assertSame('my-shop/1.4.0 (+https://shop.example)', $agent(['name' => 'my-shop', 'version' => '1.4.0', 'url' => 'https://shop.example']));
        $bad = [
            [],
            'my-shop/1.0',
            ['name' => "a\r\nx-evil: 1"],
            ['name' => 'ok', 'version' => 'a b'],
            ['name' => 'ok', 'url' => 'javascript:1'],
            ['name' => 'ok', 'extra' => 1],
        ];
        foreach ($bad as $info) {
            try {
                $agent($info);
                self::fail('Expected app_info to be rejected: ' . json_encode($info));
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('app_info', $e->getMessage());
            }
        }
    }

    public function testChecksCallbackOptionsWhenTheClientIsCreated(): void
    {
        $cases = [
            [['transport' => new \stdClass()], '"transport" option must implement'],
            [['middleware' => 'nope'], '"middleware" option must be a list'],
            [['middleware' => [static fn($r, $next) => $next($r), 'nope']], 'middleware[1]'],
            [['logger' => new \stdClass()], '"logger" option must be a PSR-3 logger'],
            [['sleep' => 1], '"sleep" option must be a callable'],
            [['retry_strategy' => new \stdClass()], '"retry_strategy" option must implement'],
            [['max_retries' => 1, 'retry_strategy' => new ExponentialBackoff(3)], 'either "max_retries" or "retry_strategy"'],
        ];
        foreach ($cases as [$options, $message]) {
            try {
                new MullaClient($options + ['api_key' => self::KEY]);
                self::fail('Expected an exception for ' . $message);
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testCallableMiddlewareRuns(): void
    {
        $client = $this->client(['middleware' => [
            static fn(Request $request, callable $next): Response => $next($request->withHeader('x-trace-id', 'trace-1')),
        ]]);
        $this->fake->pushJson(self::charge());
        $client->charges->retrieve('abc');
        self::assertSame('trace-1', $this->fake->lastRequest()?->header('x-trace-id'));
    }

    public function testAThrowingLoggerNeverBreaksARequest(): void
    {
        $client = $this->client(['logger' => static function (): void {
            throw new \RuntimeException('logger down');
        }]);
        $this->fake->pushJson(self::charge());
        self::assertSame('CHG20261116ABCD', $client->charges->retrieve('abc')->reference);
    }

    public function testUsesWebhookSecretAndUrlAsDefaults(): void
    {
        $client = $this->client(['webhook_secret' => 'whsec_test', 'webhook_url' => 'https://shop.example/hook']);
        self::assertInstanceOf(Webhook::class, $client->webhooks());
        $this->fake->pushJson(['id' => 'wh_1', 'url' => 'https://shop.example/hook']);
        $client->webhookEndpoint->create();
        self::assertSame(['url' => 'https://shop.example/hook', 'is_active' => true], $this->fake->lastBody());

        $this->expectExceptionMessage('webhook_secret');
        $this->client()->webhooks();
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

    public function testDebugInfoNeverLeaksTheSecrets(): void
    {
        $client = $this->client(['webhook_secret' => 'whsec_TopSecretValue']);
        foreach ([print_r($client, true), print_r($client->config(), true)] as $dump) {
            self::assertStringNotContainsString('s3cr3tRandomPart', $dump);
            self::assertStringNotContainsString('whsec_TopSecretValue', $dump);
        }
        $dump = print_r($client, true);
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
