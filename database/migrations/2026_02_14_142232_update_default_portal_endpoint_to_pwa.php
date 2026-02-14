<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update default portal endpoint from /portal to /pwa/portal
     * to use mobile-optimized PWA dashboard as default.
     */
    public function up(): void
    {
        // Update portal endpoint setting
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'default_portal_endpoint')
            ->update([
                'payload' => json_encode('/pwa/portal'),
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Restore original portal endpoint to /portal
     */
    public function down(): void
    {
        // Restore original value
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'default_portal_endpoint')
            ->update([
                'payload' => json_encode('/portal'),
            ]);
    }
};
