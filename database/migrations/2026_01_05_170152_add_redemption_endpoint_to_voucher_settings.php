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
        // Add default_redemption_endpoint setting to voucher settings
        DB::table('settings')->insert([
            'group' => 'voucher',
            'name' => 'default_redemption_endpoint',
            'payload' => json_encode('/disburse'),
            'locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'default_redemption_endpoint')
            ->delete();
    }
};
