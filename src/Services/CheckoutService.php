<?php

declare(strict_types=1);

namespace Mulla\Services;

use Mulla\Enums\Provider;
use Mulla\Http\RequestOptions;
use Mulla\Models\CheckoutSession;

/**
 * Public checkout endpoints, authorised by the session token (not by your API key).
 * Use these to build your own checkout UI on top of a session created with
 * $mulla->charges->createCheckout().
 */
final class CheckoutService extends AbstractService
{
    /** @param RequestOptions|array<string, mixed>|null $options */
    public function retrieve(string $id, string $token, RequestOptions|array|null $options = null): CheckoutSession
    {
        return $this->retrieveModel(CheckoutSession::class, 'checkout/' . self::id($id, 'checkout id'), ['token' => $token], $options);
    }

    /**
     * Starts payment of a checkout session: sends the prompt to the customer's phone.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function pay(string $id, string $token, Provider|string $provider, string $phoneNumber, RequestOptions|array|null $options = null): CheckoutSession
    {
        return $this->send(
            CheckoutSession::class,
            'POST',
            'checkout/' . self::id($id, 'checkout id') . '/pay',
            ['token' => $token],
            ['provider' => $provider instanceof Provider ? $provider->value : $provider, 'phone_number' => $phoneNumber],
            $options,
        );
    }

    /**
     * URL of the Server-Sent Events stream for a session. Browsers can listen to it with
     * `new EventSource(url)` to get live status updates without polling.
     */
    public function eventsUrl(string $id, string $token): string
    {
        return $this->config->baseUrl . '/checkout/' . self::id($id, 'checkout id') . '/events?token=' . rawurlencode($token);
    }
}
