<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add default_settlement_endpoint to voucher settings
        DB::table('settings')
            ->where('group', 'voucher')
            ->update([
                'payload' => DB::raw("json_set(payload, '$.default_settlement_endpoint', '/pay')"),
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
            ->update([
                'payload' => DB::raw("json_remove(payload, '$.default_settlement_endpoint')"),
            ]);
    }
};
