<?php

/**
 * Payout: send money from your Mulla balance to a phone.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mulla\Enums\Provider;
use Mulla\Exceptions\InvalidRequestException;
use Mulla\MullaClient;
use Mulla\Params\CreatePayoutParams;

$mulla = new MullaClient((string) getenv('MULLA_SECRET_KEY'));

try {
    $payout = $mulla->payouts->initiate(
        CreatePayoutParams::make()
            ->provider(Provider::AIRTEL_RW)
            ->phoneNumber('250733000000')
            ->amount(5000)
            ->reason('Supplier payment #42'),
    );

    $payout = $mulla->payouts->waitForFinalStatus($payout->id);
    echo "Payout {$payout->id}: {$payout->status?->value}\n";
} catch (InvalidRequestException $e) {
    if ($e->getErrorCode() === 'INSUFFICIENT_BALANCE') {
        echo "Top up your balance first (see \$mulla->recharges).\n";
    } else {
        throw $e;
    }
}
