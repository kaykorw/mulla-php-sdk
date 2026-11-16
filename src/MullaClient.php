<?php

declare(strict_types=1);

namespace Mulla;

use Mulla\Environment\Environment;
use Mulla\Http\ApiRequestor;
use Mulla\Services\AbstractService;
use Mulla\Services\ChargeService;
use Mulla\Services\CheckoutService;
use Mulla\Services\CustomerService;
use Mulla\Services\InvoiceService;
use Mulla\Services\PayoutService;
use Mulla\Services\RechargeService;
use Mulla\Services\ReferenceDataService;
use Mulla\Services\ServiceFactory;
use Mulla\Services\WebhookEndpointService;
use Mulla\Webhooks\Webhook;

/**
 * Entry point to the Mulla API (Facade over all services).
 *
 * @property-read ChargeService          $charges         Collect money (direct push or hosted checkout)
 * @property-read CheckoutService        $checkout        Public checkout-session endpoints (token based)
 * @property-read PayoutService          $payouts         Send money to phones
 * @property-read RechargeService        $recharges       Top up your Mulla balance
 * @property-read InvoiceService         $invoices        Invoices and invoice payments
 * @property-read CustomerService        $customers       Customers
 * @property-read WebhookEndpointService $webhookEndpoint Your webhook configuration
 * @property-read ReferenceDataService   $referenceData   Banks, countries, currencies, scopes
 */
final class MullaClient
{
    private readonly MullaConfig $config;
    private readonly ServiceFactory $services;

    /**
     * @param string               $apiKey  your secret key (sk_test_... or sk_live_...)
     * @param array<string, mixed> $options see MullaConfig for every option
     */
    public function __construct(#[\SensitiveParameter] string $apiKey, array $options = [])
    {
        $this->config = MullaConfig::create($apiKey, $options);
        $this->services = new ServiceFactory(new ApiRequestor($this->config), $this->config);
    }

    public function __get(string $name): AbstractService
    {
        return $this->services->get($name);
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ServiceFactory::names(), true);
    }

    public function environment(): Environment
    {
        return $this->config->environment;
    }

    public function isLive(): bool
    {
        return $this->config->environment->isLive();
    }

    public function baseUrl(): string
    {
        return $this->config->baseUrl;
    }

    public function config(): MullaConfig
    {
        return $this->config;
    }

    public function webhooks(#[\SensitiveParameter] string $secret, int $toleranceSeconds = 300): Webhook
    {
        return new Webhook($secret, $toleranceSeconds);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => $this->config->maskedApiKey(),
            'environment' => $this->config->environment->value,
            'baseUrl' => $this->config->baseUrl,
        ];
    }
}
