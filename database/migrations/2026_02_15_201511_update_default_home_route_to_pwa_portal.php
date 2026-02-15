<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update default_home_route from 'portal' to 'pwa.portal' to redirect
     * users to mobile-optimized PWA dashboard after login.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'default_home_route')
            ->update([
                'payload' => json_encode('pwa.portal'),
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Restore original home route to 'portal'
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'default_home_route')
            ->update([
                'payload' => json_encode('portal'),
            ]);
    }
};
