<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add cash.slice_fee InstructionItem for per-slice disbursement pricing.
     *
     * Each additional slice in a divisible voucher incurs this fee.
     * Slice 1 is covered by the existing cash.amount transaction fee.
     */
    public function up(): void
    {
        DB::table('instruction_items')->insertOrIgnore([
            'index' => 'cash.slice_fee',
            'name' => 'Slice Fee',
            'type' => 'amount',
            'price' => 1500,
            'currency' => 'PHP',
            'meta' => json_encode([
                'label' => 'Slice Disbursement Fee',
                'category' => 'base',
                'description' => 'Per-slice fund transfer cost for divisible vouchers',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('instruction_items')
            ->where('index', 'cash.slice_fee')
            ->delete();
    }
};
