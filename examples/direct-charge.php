<?php

/**
 * Direct charge: push a payment prompt to the customer's phone, then wait for the result.
 *
 *   MULLA_SECRET_KEY=sk_test_... MULLA_BASE_URL=https://<host>/gateway/v1 php examples/direct-charge.php 250788000000
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mulla\Enums\Provider;
use Mulla\Exceptions\InvalidRequestException;
use Mulla\Exceptions\MullaException;
use Mulla\MullaClient;
use Mulla\Params\InitiateChargeParams;

$mulla = new MullaClient((string) getenv('MULLA_SECRET_KEY'));

try {
    $charge = $mulla->charges->initiate(
        InitiateChargeParams::make()
            ->provider(Provider::MTN_RW)
            ->phoneNumber($argv[1] ?? '250788000000')
            ->amount(1000)
            ->reason('Order #1001')
            ->metadata(['order_id' => 1001]),
    );

    echo "Charge {$charge->reference} created. Approve the prompt on your phone...\n";

    $charge = $mulla->charges->waitForFinalStatus($charge->id, timeoutSeconds: 180);

    echo match (true) {
        $charge->isSuccessful() => "Paid! Net amount: {$charge->netAmount} {$charge->currency}\n",
        $charge->isFinal() => "Payment {$charge->status?->value}: {$charge->failureMessage}\n",
        default => "Still pending; you will get a webhook when it completes.\n",
    };
} catch (InvalidRequestException $e) {
    echo "Invalid request ({$e->getErrorCode()}): {$e->getMessage()}\n";
} catch (MullaException $e) {
    echo 'Mulla error: ' . $e->getMessage() . "\n";
}
