<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Exceptions\PermissionException;

final class ScopeErrorTest extends TestCase
{
    public function testScopeErrorNamesTheServiceScope(): void
    {
        $client = $this->client();
        $this->fake->pushError(403, 'API_KEY_SCOPE_DENIED', 'API key does not have the required scope');

        try {
            $client->payouts->list();
            self::fail('Expected exception');
        } catch (PermissionException $e) {
            self::assertStringEndsWith('Required scope: PAYOUTS.', $e->getMessage());
            self::assertSame('API_KEY_SCOPE_DENIED', $e->getErrorCode());
            self::assertInstanceOf(PermissionException::class, $e->getPrevious());
        }
    }

    public function testServerProvidedScopeWins(): void
    {
        $client = $this->client();
        $this->fake->pushError(403, 'API_KEY_SCOPE_DENIED', 'Denied', ['requiredScope' => 'CUSTOMERS']);

        $this->expectExceptionMessage('Required scope: CUSTOMERS.');
        $client->charges->list();
    }

    public function testWebhookEndpointNamesNoScope(): void
    {
        $client = $this->client();
        $this->fake->pushError(403, 'API_KEY_SCOPE_DENIED', 'API key does not have the required scope');

        try {
            $client->webhookEndpoint->retrieve();
            self::fail('Expected exception');
        } catch (PermissionException $e) {
            self::assertStringNotContainsString('Required scope', $e->getMessage());
        }
    }

    public function testOtherPermissionErrorsAreUntouched(): void
    {
        $client = $this->client();
        $this->fake->pushError(403, 'API_KEY_IP_DENIED', 'IP not allowed');

        try {
            $client->charges->list();
            self::fail('Expected exception');
        } catch (PermissionException $e) {
            self::assertStringNotContainsString('Required scope', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }
}
