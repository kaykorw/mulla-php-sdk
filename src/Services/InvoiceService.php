<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Enums\InvoiceStatus;
use Mulla\Enums\Provider;
use Mulla\Http\RequestOptions;
use Mulla\Models\Collection;
use Mulla\Models\Invoice;
use Mulla\Models\PaymentInitiation;
use Mulla\Models\PaymentTransaction;
use Mulla\Params\InvoiceParams;

/**
 * Create and manage invoices. Requires the INVOICES scope
 * (except retrievePublic() and pay(), which are public).
 */
final class InvoiceService extends AbstractService
{
    /**
     * @param InvoiceParams|array<string, mixed> $params customer + items are required
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function create(InvoiceParams|array $params, RequestOptions|array|null $options = null): Invoice
    {
        return $this->createModel(Invoice::class, 'invoices', $params, $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(string $id, RequestOptions|array|null $options = null): Invoice
    {
        return $this->retrieveModel(Invoice::class, 'invoices/' . self::id($id, 'invoice id'), [], $options);
    }

    /**
     * @param array{keyword?: string, status?: InvoiceStatus|string, customer?: string, start_date?: string|\DateTimeInterface, end_date?: string|\DateTimeInterface, sortField?: string, sortDirection?: 'ASC'|'DESC', page?: int, limit?: int} $filters
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<Invoice>
     */
    public function list(array $filters = [], RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(Invoice::class, 'invoices', $filters, $options);
    }

    /**
     * @param InvoiceParams|array<string, mixed> $params use InvoiceParams::forUpdate()
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function update(string $id, InvoiceParams|array $params, RequestOptions|array|null $options = null): Invoice
    {
        return $this->updateModel(Invoice::class, 'invoices/' . self::id($id, 'invoice id'), $params, $options);
    }

    /**
     * Allowed transitions: ISSUED, CANCELLED, OVERDUE.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function updateStatus(string $id, InvoiceStatus|string $status, RequestOptions|array|null $options = null): Invoice
    {
        return $this->updateModel(Invoice::class, 'invoices/' . self::id($id, 'invoice id') . '/status', ['status' => $status], $options);
    }

    /**
     * @param RequestOptions|array<string, mixed>|null $options
     * @return Collection<PaymentTransaction>
     */
    public function listPayments(string $id, RequestOptions|array|null $options = null): Collection
    {
        return $this->listModels(PaymentTransaction::class, 'invoices/' . self::id($id, 'invoice id') . '/payments', [], $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrievePayment(string $id, string $transactionId, RequestOptions|array|null $options = null): PaymentTransaction
    {
        return $this->retrieveModel(
            PaymentTransaction::class,
            'invoices/' . self::id($id, 'invoice id') . '/payments/' . self::id($transactionId, 'transaction id'),
            [],
            $options,
        );
    }

    /**
     * Public view of an invoice (what the customer sees).
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function retrievePublic(string $id, RequestOptions|array|null $options = null): Invoice
    {
        return $this->retrieveModel(Invoice::class, 'invoice/' . self::id($id, 'invoice id'), [], $options);
    }

    /**
     * Public: starts payment of an invoice from the customer's phone.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function pay(string $id, Provider|string $provider, string $phoneNumber, ?string $notes = null, RequestOptions|array|null $options = null): PaymentInitiation
    {
        return $this->createModel(
            PaymentInitiation::class,
            'invoice/' . self::id($id, 'invoice id') . '/pay',
            ['provider' => $provider, 'phone_number' => $phoneNumber, 'notes' => $notes],
            $options,
        );
    }
}
