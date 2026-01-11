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
        // Insert default portal endpoint setting
        DB::table('settings')->insert([
            [
                'group' => 'voucher',
                'name' => 'default_portal_endpoint',
                'payload' => json_encode('/portal'),
                'locked' => false,
            ],
            [
                'group' => 'voucher',
                'name' => 'default_home_route',
                'payload' => json_encode('portal'),
                'locked' => false,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the settings
        DB::table('settings')
            ->where('group', 'voucher')
            ->whereIn('name', ['default_portal_endpoint', 'default_home_route'])
            ->delete();
    }
};
