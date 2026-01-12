<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert auto-disburse minimum threshold setting (default: 25 PHP)
        DB::table('settings')->insert([
            'group' => 'voucher',
            'name' => 'auto_disburse_minimum',
            'payload' => json_encode(25),
            'locked' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the setting
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'auto_disburse_minimum')
            ->delete();
    }
};
