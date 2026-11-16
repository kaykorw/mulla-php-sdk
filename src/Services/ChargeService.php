<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Enums\ChargeStatus;
use Mulla\Http\RequestOptions;
use Mulla\Models\Charge;
use Mulla\Models\CheckoutSession;
use Mulla\Models\Collection;
use Mulla\Params\CreateCheckoutParams;
use Mulla\Params\InitiateChargeParams;

/**
 * Collect money from customers. Requires the CHARGES scope.
 */
final class ChargeService extends AbstractService
{
    /**
     * Sends a payment prompt to the customer's phone. Returns a PENDING charge;
     * wait for the webhook or call waitForFinalStatus().
     *
     * @param InitiateChargeParams|array{provider: \Mulla\Enums\Provider|string, amount: int|float, phone_number: string, currency?: string, reason?: string, application?: string, notes?: string, billing_details?: array<string, mixed>, shipping_details?: array<string, mixed>, metadata?: array<string, mixed>, customer?: array{id: string}} $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function initiate(InitiateChargeParams|array $params, RequestOptions|array|null $options = null): Charge
    {
        return $this->createModel(Charge::class, 'charges/initiate', $params, $options);
    }

    /**
     * Creates a hosted checkout session. Redirect the customer to $session->checkoutUrl.
     *
     * @param CreateCheckoutParams|array{amount: int|float, success_url: string, cancel_url: string, currency?: string, reason?: string, application?: string, notes?: string, billing_details?: array<string, mixed>, shipping_details?: array<string, mixed>, metadata?: array<string, mixed>, customer?: array{id: string}} $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function createCheckout(CreateCheckoutParams|array $params, RequestOptions|array|null $options = null): CheckoutSession
    {
        return $this->createModel(CheckoutSession::class, 'charges/checkout', $params, $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(string $id, RequestOptions|array|null $options = null): Charge
    {
        return $this->retrieveModel(Charge::class, 'charges/' . self::id($id, 'charge id'), [], $options);
    }

    /**
     * @param array{status?: ChargeStatus|string, keyword?: string, start_date?: string|\DateTimeInterface, end_date?: string|\DateTimeInterface, sortField?: string, sortDirection?: 'ASC'|'DESC', page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<Charge>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(Charge::class, 'charges', $filters, $options);
    }

    /**
     * Polls the charge until it is SUCCESSFUL, FAILED or EXPIRED, or until the timeout.
     * Returns the latest charge either way. Prefer webhooks in production; use this for
     * scripts, CLIs, or as a fallback.
     */
    public function waitForFinalStatus(string $id, int $timeoutSeconds = 120, int $intervalSeconds = 5): Charge
    {
        return $this->pollUntilFinal(fn(): Charge => $this->retrieve($id), $timeoutSeconds, $intervalSeconds);
    }
}
