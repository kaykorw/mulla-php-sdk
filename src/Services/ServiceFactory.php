<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Exceptions\InvalidArgumentException;
use Mulla\Http\ApiRequestor;
use Mulla\MullaConfig;

/**
 * Factory: creates services lazily on first access and reuses them afterwards.
 */
final class ServiceFactory
{
    /** @var array<string, class-string<AbstractService>> */
    private const SERVICES = [
        'charges' => ChargeService::class,
        'checkout' => CheckoutService::class,
        'payouts' => PayoutService::class,
        'recharges' => RechargeService::class,
        'invoices' => InvoiceService::class,
        'customers' => CustomerService::class,
        'webhookEndpoint' => WebhookEndpointService::class,
        'referenceData' => ReferenceDataService::class,
    ];

    /** @var array<string, AbstractService> */
    private array $instances = [];

    public function __construct(
        private readonly ApiRequestor $requestor,
        private readonly MullaConfig $config,
    ) {
    }

    public function get(string $name): AbstractService
    {
        if (!isset(self::SERVICES[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown Mulla service "%s". Available: %s.',
                $name,
                implode(', ', array_keys(self::SERVICES)),
            ));
        }

        return $this->instances[$name] ??= new (self::SERVICES[$name])($this->requestor, $this->config);
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::SERVICES);
    }
}
