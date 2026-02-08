<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add default_settlement_endpoint to voucher settings
        DB::table('settings')->insert([
            'group' => 'voucher',
            'name' => 'default_settlement_endpoint',
            'locked' => 0,
            'payload' => '"/pay"',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove default_settlement_endpoint from voucher settings
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'default_settlement_endpoint')
            ->delete();
    }
};
