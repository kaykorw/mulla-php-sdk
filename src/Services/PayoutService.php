<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Enums\PayoutStatus;
use Mulla\Http\RequestOptions;
use Mulla\Models\Collection;
use Mulla\Models\Payout;
use Mulla\Params\CreatePayoutParams;

/**
 * Send money to mobile money wallets. Requires the PAYOUTS scope.
 */
final class PayoutService extends AbstractService
{
    protected const SCOPE = \Mulla\Enums\ApiScope::PAYOUTS;

    /**
     * The amount is reserved from your balance immediately.
     * Throws InvalidRequestException (INSUFFICIENT_BALANCE) when your balance is too low.
     *
     * @param CreatePayoutParams|array{provider: \Mulla\Enums\Provider|string, amount: int|float, phone_number: string, reason: string, metadata?: array<string, mixed>} $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function initiate(CreatePayoutParams|array $params, RequestOptions|array|null $options = null): Payout
    {
        return $this->createModel(Payout::class, 'payouts/initiate', $params, $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(string $id, RequestOptions|array|null $options = null): Payout
    {
        return $this->retrieveModel(Payout::class, 'payouts/' . self::id($id, 'payout id'), [], $options);
    }

    /**
     * @param array{status?: PayoutStatus|string, keyword?: string, start_date?: string|\DateTimeInterface, end_date?: string|\DateTimeInterface, sortField?: string, sortDirection?: 'ASC'|'DESC', page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<Payout>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(Payout::class, 'payouts', $filters, $options);
    }

    public function waitForFinalStatus(string $id, int $timeoutSeconds = 120, int $intervalSeconds = 5): Payout
    {
        return $this->pollUntilFinal(fn(): Payout => $this->retrieve($id), $timeoutSeconds, $intervalSeconds);
    }
}
