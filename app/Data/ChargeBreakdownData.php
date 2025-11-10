<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ChargeBreakdownData extends Data
{
    public function __construct(
        public array $breakdown, // ['cash.amount' => 20.00, 'feedback.email' => 1.00]
        public float $total      // Total charge to customer
    ) {}
}
