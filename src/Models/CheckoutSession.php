<?php

declare(strict_types=1);

namespace Mulla\Models;

use Mulla\Enums\ChargeStatus;
use Mulla\Enums\Provider;

/**
 * A hosted checkout session. Redirect your customer to $checkoutUrl; Mulla sends
 * them back to $successUrl or $cancelUrl. Always confirm the result server-side
 * (webhook or $mulla->charges->retrieve($session->charge)): return URLs are not signed.
 */
final class CheckoutSession extends AbstractModel
{
    public readonly string $id;
    /** Id of the underlying charge. */
    public readonly ?string $charge;
    public readonly ?string $reference;
    public readonly int|float|null $amount;
    public readonly int|float|null $feeAmount;
    public readonly int|float|null $netAmount;
    public readonly ?string $currency;
    public readonly ?string $reason;
    public readonly ?string $phoneNumber;
    public readonly ?Provider $provider;
    public readonly ?ChargeStatus $status;
    public readonly ?string $successUrl;
    public readonly ?string $cancelUrl;
    /** Only returned when the session is created. */
    public readonly ?string $checkoutUrl;
    /** Only returned when the session is created. Needed for the public checkout endpoints. */
    public readonly ?string $token;
    /**
     * Keys: name, merchant_code, logo_url, support_email, support_phone.
     *
     * @var array<string, mixed>|null
     */
    public readonly ?array $merchant;
    public readonly ?\DateTimeImmutable $expiresAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->charge = self::relationId($data, 'charge');
        $this->reference = self::str($data, 'reference');
        $this->amount = self::money($data, 'amount');
        $this->feeAmount = self::money($data, 'fee_amount');
        $this->netAmount = self::money($data, 'net_amount');
        $this->currency = self::str($data, 'currency');
        $this->reason = self::str($data, 'reason');
        $this->phoneNumber = self::str($data, 'phone_number');
        $this->provider = self::enum($data, 'provider', Provider::class);
        $this->status = self::enum($data, 'status', ChargeStatus::class);
        $this->successUrl = self::str($data, 'success_url');
        $this->cancelUrl = self::str($data, 'cancel_url');
        $this->checkoutUrl = self::str($data, 'checkout_url');
        $this->token = self::str($data, 'token');
        $this->merchant = self::arr($data, 'merchant');
        $this->expiresAt = self::date($data, 'expires_at');
    }

    public function isSuccessful(): bool
    {
        return $this->status === ChargeStatus::SUCCESSFUL;
    }

    public function isFinal(): bool
    {
        return $this->status?->isFinal() ?? false;
    }
}
