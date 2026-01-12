<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoucherSettingsSeeder extends Seeder
{
    /**
     * Seed the voucher settings.
     */
    public function run(): void
    {
        // Check if settings already exist
        $existingSettings = DB::table('settings')
            ->where('group', 'voucher')
            ->count();

        if ($existingSettings > 0) {
            $this->command->info('VoucherSettings already seeded, skipping...');
            return;
        }

        // Insert default voucher settings from config
        DB::table('settings')->insert([
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
            [
                'group' => 'voucher',
                'name' => 'default_settlement_endpoint',
                'payload' => json_encode('/pay'),
                'locked' => false,
            ],
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
            [
                'group' => 'voucher',
                'name' => 'auto_disburse_minimum',
                'payload' => json_encode(25),
                'locked' => false,
            ],
        ]);

        $this->command->info('VoucherSettings seeded successfully!');
    }
}
