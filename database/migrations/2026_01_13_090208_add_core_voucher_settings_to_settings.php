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
        // Insert core voucher settings that were previously in VoucherSettingsSeeder
        // Using insertOrIgnore to safely handle cases where settings already exist
        DB::table('settings')->insertOrIgnore([
            [
                'group' => 'voucher',
                'name' => 'default_amount',
                'payload' => json_encode(config('generate.basic_settings.amount.default', 50)),
                'locked' => false,
            ],
            [
                'group' => 'voucher',
                'name' => 'default_expiry_days',
                'payload' => json_encode(config('generate.basic_settings.ttl.default')),
                'locked' => false,
            ],
            [
                'group' => 'voucher',
                'name' => 'default_rider_url',
                'payload' => json_encode(config('generate.rider.url.default', config('app.url'))),
                'locked' => false,
            ],
            [
                'group' => 'voucher',
                'name' => 'default_success_message',
                'payload' => json_encode(config('generate.rider.message.placeholder', 'Thank you for redeeming your voucher! The cash will be transferred shortly.')),
                'locked' => false,
            ],
            [
                'group' => 'voucher',
                'name' => 'default_redemption_endpoint',
                'payload' => json_encode('/disburse'),
                'locked' => false,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the core voucher settings
        DB::table('settings')
            ->where('group', 'voucher')
            ->whereIn('name', [
                'default_amount',
                'default_expiry_days',
                'default_rider_url',
                'default_success_message',
                'default_redemption_endpoint',
            ])
            ->delete();
    }
};
