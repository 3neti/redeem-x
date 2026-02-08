<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated This seeder is deprecated. Voucher settings are now managed via migrations.
 *
 * All voucher settings have been moved to individual migrations:
 * - 2026_01_13_090208_add_core_voucher_settings_to_settings.php (core settings)
 * - 2026_01_07_091011_add_default_settlement_endpoint_to_settings.php
 * - 2026_01_11_121748_add_portal_endpoint_to_voucher_settings.php
 * - 2026_01_12_075432_add_auto_disburse_minimum_to_voucher_settings.php
 *
 * This file is kept for historical reference but is no longer called by DatabaseSeeder.
 * Use migrations for any new voucher settings.
 */
class VoucherSettingsSeeder extends Seeder
{
    /**
     * Seed the voucher settings.
     *
     * @deprecated Use migrations instead
     */
    public function run(): void
    {
        $settings = [
            [
                'name' => 'default_amount',
                'payload' => json_encode(config('generate.basic_settings.amount.default', 50)),
            ],
            [
                'name' => 'default_expiry_days',
                'payload' => json_encode(config('generate.basic_settings.ttl.default')),
            ],
            [
                'name' => 'default_rider_url',
                'payload' => json_encode(config('generate.rider.url.default', config('app.url'))),
            ],
            [
                'name' => 'default_success_message',
                'payload' => json_encode(config('generate.rider.message.placeholder', 'Thank you for redeeming your voucher! The cash will be transferred shortly.')),
            ],
            [
                'name' => 'default_redemption_endpoint',
                'payload' => json_encode('/disburse'),
            ],
            [
                'name' => 'default_settlement_endpoint',
                'payload' => json_encode('/pay'),
            ],
            [
                'name' => 'default_portal_endpoint',
                'payload' => json_encode('/portal'),
            ],
            [
                'name' => 'default_home_route',
                'payload' => json_encode('portal'),
            ],
            [
                'name' => 'auto_disburse_minimum',
                'payload' => json_encode(25),
            ],
        ];

        $addedCount = 0;

        foreach ($settings as $setting) {
            $exists = DB::table('settings')
                ->where('group', 'voucher')
                ->where('name', $setting['name'])
                ->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'group' => 'voucher',
                    'name' => $setting['name'],
                    'payload' => $setting['payload'],
                    'locked' => false,
                ]);
                $addedCount++;
            }
        }

        if ($addedCount > 0) {
            $this->command->info("Added {$addedCount} missing VoucherSettings.");
        } else {
            $this->command->info('All VoucherSettings already exist.');
        }
    }
}
