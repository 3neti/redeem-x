<?php

namespace App\Data\Api\Wallet;

use Spatie\LaravelData\Data;

class BalanceData extends Data
{
    public function __construct(
        public string $balance,
        public string $currency,
        public int $balance_cents,
    ) {}

    public static function fromWallet($wallet): self
    {
        return new self(
            balance: number_format($wallet->balanceFloat, 2, '.', ''),
            currency: 'PHP',
            balance_cents: $wallet->balance,
        );
    }
}
