<?php

/**
 * Hosted checkout: create a session and redirect the customer to Mulla's payment page.
 * Confirm the payment with a webhook (see webhook.php), not with the return URL.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mulla\MullaClient;
use Mulla\Params\CreateCheckoutParams;

$mulla = new MullaClient(['api_key' => getenv('MULLA_SECRET_KEY')]);

$orderId = 1001;

$session = $mulla->charges->createCheckout(
    CreateCheckoutParams::make()
        ->amount(25000)
        ->reason("Order #{$orderId}")
        ->metadata(['order_id' => $orderId])
        ->successUrl("https://shop.example/orders/{$orderId}/thanks")
        ->cancelUrl('https://shop.example/cart'),
    // Reusing a key derived from your order makes retries of this call safe.
    ['idempotency_key' => '6f1c2b9e-4a7d-4c3e-9b1a-' . str_pad((string) $orderId, 12, '0', STR_PAD_LEFT)],
);

header('Location: ' . $session->checkoutUrl, true, 303);
