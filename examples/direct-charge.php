<?php

/**
 * Direct charge: push a payment prompt to the customer's phone, then wait for the result.
 *
 *   MULLA_SECRET_KEY=sk_test_... php examples/direct-charge.php 250788000000
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mulla\Enums\Language;
use Mulla\Enums\Provider;
use Mulla\Environment\Environment;
use Mulla\Exceptions\InvalidRequestException;
use Mulla\Exceptions\MullaException;
use Mulla\Http\Request;
use Mulla\Http\Response;
use Mulla\MullaClient;
use Mulla\Params\InitiateChargeParams;

$mulla = new MullaClient([
    'api_key' => getenv('MULLA_SECRET_KEY'),
    'environment' => Environment::TEST,
    'base_url' => getenv('MULLA_BASE_URL'),
    'webhook_secret' => getenv('MULLA_WEBHOOK_SECRET'),
    'webhook_url' => getenv('MULLA_WEBHOOK_URL'),
    'timeout' => 10,
    'max_retries' => 2,
    'lang' => Language::EN,
    'app_info' => ['name' => 'my-shop', 'version' => '1.4.0', 'url' => 'https://shop.example'],
    'logger' => static fn(string $level, string $message, array $context) => error_log("[{$level}] {$message}"),
    'middleware' => [
        static fn(Request $request, callable $next): Response => $next($request->withHeader('x-trace-id', bin2hex(random_bytes(8)))),
    ],
]);

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
