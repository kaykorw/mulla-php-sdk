<?php

declare(strict_types=1);

namespace Mulla\Tests\Unit;

use Mulla\MullaClient;
use Mulla\Testing\FakeTransport;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const KEY = 'sk_test_AbC123_s3cr3tRandomPart';
    protected const BASE = 'https://mulla.test/gateway/v1';

    protected FakeTransport $fake;
    /** @var list<float> */
    protected array $sleeps = [];

    protected function client(array $options = []): MullaClient
    {
        $this->fake = new FakeTransport();
        $this->sleeps = [];

        return new MullaClient(self::KEY, $options + [
            'baseUrl' => self::BASE,
            'transport' => $this->fake,
            'sleeper' => function (float $s): void {
                $this->sleeps[] = $s;
            },
        ]);
    }

    /** @return array<string, mixed> */
    protected static function charge(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9b2e5c1a-7d4f-4a3b-8c6e-1f2a3b4c5d6e',
            'reference' => 'CHG20261116ABCD',
            'amount' => 5000,
            'fee_amount' => 100,
            'net_amount' => 4900,
            'currency' => 'RWF',
            'phone_number' => '250788000000',
            'provider' => 'MTN_RW',
            'status' => 'PENDING',
            'metadata' => ['order_id' => 1001],
            'expires_at' => '2026-11-16T10:10:00.000Z',
            'created_at' => '2026-11-16T10:00:00.000Z',
        ];
    }
}
