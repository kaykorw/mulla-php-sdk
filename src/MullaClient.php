<?php

declare(strict_types=1);

namespace Mulla;

use Mulla\Environment\Environment;
use Mulla\Exceptions\InvalidArgumentException;
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
     * @param array<string, mixed>|string $options see MullaConfig for every option, or just the secret key
     */
    public function __construct(#[\SensitiveParameter] array|string $options)
    {
        if (func_num_args() > 1) {
            throw new InvalidArgumentException(
                "MullaClient takes a single options array: new MullaClient(['api_key' => \$secretKey, 'base_url' => ...]).",
            );
        }
        $this->config = MullaConfig::create($options);
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

    /** Webhook verifier using $secret, or the client's "webhook_secret" option when omitted. */
    public function webhooks(#[\SensitiveParameter] ?string $secret = null, int $toleranceSeconds = 300): Webhook
    {
        $secret ??= $this->config->webhookSecret();
        if ($secret === null) {
            throw new InvalidArgumentException(
                'No webhook secret. Pass "webhook_secret" to new MullaClient([...]) or call $mulla->webhooks($secret).',
            );
        }

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
