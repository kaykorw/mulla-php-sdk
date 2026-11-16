<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Enums\RechargeStatus;
use Mulla\Http\RequestOptions;
use Mulla\Models\Collection;
use Mulla\Models\Recharge;
use Mulla\Params\InitiateRechargeParams;

/**
 * Top up your Mulla balance. Requires the RECHARGES scope.
 */
final class RechargeService extends AbstractService
{
    /**
     * @param InitiateRechargeParams|array{provider: \Mulla\Enums\Provider|string, amount: int|float, phone_number: string, reason?: string, metadata?: array<string, mixed>} $params
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function initiate(InitiateRechargeParams|array $params, RequestOptions|array|null $options = null): Recharge
    {
        return $this->createModel(Recharge::class, 'recharge/initiate', $params, $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(string $id, RequestOptions|array|null $options = null): Recharge
    {
        return $this->retrieveModel(Recharge::class, 'recharge/' . self::id($id, 'recharge id'), [], $options);
    }

    /**
     * @param array{status?: RechargeStatus|string, keyword?: string, start_date?: string|\DateTimeInterface, end_date?: string|\DateTimeInterface, page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<Recharge>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(Recharge::class, 'recharge', $filters, $options);
    }

    public function waitForFinalStatus(string $id, int $timeoutSeconds = 120, int $intervalSeconds = 5): Recharge
    {
        return $this->pollUntilFinal(fn(): Recharge => $this->retrieve($id), $timeoutSeconds, $intervalSeconds);
    }
}
