<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\Enums\ChargeStatus;
use Mulla\Enums\InvoiceStatus;
use Mulla\Enums\Provider;
use Mulla\Models\Charge;
use Mulla\Params\CreateCheckoutParams;
use Mulla\Params\InvoiceParams;

final class ServicesTest extends TestCase
{
    public function testChargeIsHydratedIntoTypedModel(): void
    {
        $client = $this->client();
        $this->fake->pushJson(self::charge(['status' => 'SUCCESSFUL', 'brand_new_field' => 'x']));

        $charge = $client->charges->retrieve('9b2e5c1a-7d4f-4a3b-8c6e-1f2a3b4c5d6e');

        self::assertSame(5000, $charge->amount);
        self::assertSame(Provider::MTN_RW, $charge->provider);
        self::assertSame(ChargeStatus::SUCCESSFUL, $charge->status);
        self::assertTrue($charge->isSuccessful());
        self::assertTrue($charge->isFinal());
        self::assertSame(['order_id' => 1001], $charge->metadata);
        self::assertSame('2026-11-16', $charge->createdAt?->format('Y-m-d'));
        self::assertSame('x', $charge['brand_new_field']);
        self::assertSame('x', json_decode((string) json_encode($charge), true)['brand_new_field']);
    }

    public function testUnknownStatusDoesNotBreakOldSdkVersions(): void
    {
        $client = $this->client();
        $this->fake->pushJson(self::charge(['status' => 'REFUNDED']));

        $charge = $client->charges->retrieve('abc');

        self::assertNull($charge->status);
        self::assertFalse($charge->isFinal());
        self::assertSame('REFUNDED', $charge['status']);
    }

    public function testListAutoPaginatesAcrossPages(): void
    {
        $client = $this->client();
        $this->fake
            ->pushJson(['data' => [self::charge(['id' => 'a']), self::charge(['id' => 'b'])], 'total' => 3, 'page' => 1, 'limit' => 2, 'total_pages' => 2])
            ->pushJson(['data' => [self::charge(['id' => 'c'])], 'total' => 3, 'page' => 2, 'limit' => 2, 'total_pages' => 2]);

        $page = $client->charges->list(['status' => ChargeStatus::PENDING, 'limit' => 2]);
        self::assertCount(2, $page);
        self::assertTrue($page->hasMore());

        $ids = array_map(static fn(Charge $c) => $c->id, iterator_to_array($page, false));

        self::assertSame(['a', 'b', 'c'], $ids);
        self::assertStringContainsString('page=2', (string) $this->fake->lastRequest()?->url);
        self::assertStringContainsString('status=PENDING', (string) $this->fake->lastRequest()?->url);
    }

    public function testWaitForFinalStatusPollsUntilDone(): void
    {
        $client = $this->client();
        $this->fake->pushJson(self::charge())->pushJson(self::charge())->pushJson(self::charge(['status' => 'FAILED', 'failure_code' => 'PAYER_DECLINED']));

        $charge = $client->charges->waitForFinalStatus('abc', timeoutSeconds: 60, intervalSeconds: 3);

        self::assertSame(ChargeStatus::FAILED, $charge->status);
        self::assertSame('PAYER_DECLINED', $charge->failureCode);
        self::assertSame([3.0, 3.0], $this->sleeps);
    }

    public function testCheckoutCreationAndPublicPay(): void
    {
        $client = $this->client();
        $this->fake->pushJson([
            'id' => 'cs_1', 'charge' => 'chg_1', 'reference' => 'CHG1', 'amount' => '5000', 'status' => 'PENDING',
            'checkout_url' => 'https://pay.mulla.test/checkout.html?id=cs_1&token=tok', 'token' => 'tok',
            'success_url' => 'https://shop.test/ok', 'cancel_url' => 'https://shop.test/cancel',
            'merchant' => ['name' => 'Shop'],
        ]);

        $session = $client->charges->createCheckout(
            CreateCheckoutParams::make()->amount(5000)->successUrl('https://shop.test/ok')->cancelUrl('https://shop.test/cancel'),
        );
        self::assertSame('tok', $session->token);
        self::assertSame(5000, $session->amount);
        self::assertSame('chg_1', $session->charge);

        $this->fake->pushJson(['id' => 'cs_1', 'status' => 'PENDING', 'provider' => 'MTN_RW']);
        $client->checkout->pay('cs_1', 'tok', Provider::MTN_RW, '250788000000');

        self::assertSame(self::BASE . '/checkout/cs_1/pay?token=tok', $this->fake->lastRequest()?->url);
        self::assertSame(['provider' => 'MTN_RW', 'phone_number' => '250788000000'], $this->fake->lastBody());
        self::assertSame(self::BASE . '/checkout/cs_1/events?token=a%2Bb', $client->checkout->eventsUrl('cs_1', 'a+b'));
    }

    public function testInvoiceBuilderAndStatusUpdate(): void
    {
        $client = $this->client();
        $this->fake->pushJson(['id' => 'inv_1', 'status' => 'ISSUED', 'items' => [['name' => 'Consulting', 'unit_price' => 50000, 'quantity' => 2]]]);

        $invoice = $client->invoices->create(
            InvoiceParams::make()->customer('cus_1')->addItem('Consulting', unitPrice: 50000, quantity: 2),
        );
        self::assertSame(['customer' => ['id' => 'cus_1'], 'items' => [['name' => 'Consulting', 'unit_price' => 50000, 'quantity' => 2]]], $this->fake->lastBody());
        self::assertSame(2, $invoice->items[0]->quantity);

        $this->fake->pushJson(['id' => 'inv_1', 'status' => 'CANCELLED']);
        $updated = $client->invoices->updateStatus('inv_1', InvoiceStatus::CANCELLED);
        self::assertSame('PATCH', $this->fake->lastRequest()?->method);
        self::assertSame(InvoiceStatus::CANCELLED, $updated->status);
    }

    public function testCustomerHandlesBothFieldCasings(): void
    {
        $client = $this->client();
        $this->fake->pushJson(['id' => 'c1', 'full_name' => 'Aline', 'companyName' => 'Kayko', 'is_active' => true]);
        self::assertSame('Kayko', $client->customers->create(['full_name' => 'Aline'])->companyName);

        $this->fake->pushJson(['id' => 'c1', 'full_name' => 'Aline', 'company_name' => 'Kayko', 'is_active' => 'true']);
        $customer = $client->customers->retrieve('c1');
        self::assertSame('Kayko', $customer->companyName);
        self::assertTrue($customer->isActive);
    }

    public function testWebhookEndpointServicePaths(): void
    {
        $client = $this->client();
        $this->fake->pushJson(['ok' => true, 'status_code' => 200, 'response_body' => 'ok', 'error' => null]);

        $result = $client->webhookEndpoint->test();

        self::assertTrue($result->ok);
        self::assertSame(self::BASE . '/webhooks/test', $this->fake->lastRequest()?->url);
        self::assertSame('{}', $this->fake->lastRequest()?->body);
    }

    public function testPlainArrayListResponses(): void
    {
        $client = $this->client();
        $this->fake->pushJson([['code' => 'RWF', 'name' => 'Rwandan Franc']]);

        $currencies = $client->referenceData->currencies();

        self::assertSame('RWF', $currencies->first()?->code);
        self::assertFalse($currencies->hasMore());
    }
}
