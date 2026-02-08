<?php

namespace App\Actions\Settings;

use App\Models\Campaign;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class CreateDefaultCampaigns
{
    public function handle(User $user): void
    {
        // Eager load channels for mobile access
        $user->load('channels');

        // 1. Quick Cash - Instant gratification, no friction
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Quick Cash',
            'slug' => Str::slug('quick-cash-'.$user->id),
            'status' => 'active',
            'description' => 'Instant cash voucher - no verification required',
            'instructions' => VoucherInstructionsData::from([
                'cash' => [
                    'amount' => 500,
                    'currency' => 'PHP',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                ],
                'inputs' => [
                    'fields' => [],
                ],
                'feedback' => [
                    'email' => null,
                    'mobile' => null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => null,
                    'url' => null,
                ],
                'count' => 1,
                'prefix' => '',
                'mask' => '****',
                'ttl' => CarbonInterval::days(1),
            ]),
        ]);

        // 2. Petty Cash - Basic tracking with location + email
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Petty Cash',
            'slug' => Str::slug('petty-cash-'.$user->id),
            'status' => 'active',
            'description' => 'Track redemption location and get email notifications',
            'instructions' => VoucherInstructionsData::from([
                'cash' => [
                    'amount' => 1000,
                    'currency' => 'PHP',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                ],
                'inputs' => [
                    'fields' => ['location'],
                ],
                'feedback' => [
                    'email' => $user->email,
                    'mobile' => null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => null,
                    'url' => null,
                ],
                'count' => 1,
                'prefix' => '',
                'mask' => '****',
                'ttl' => CarbonInterval::days(3),
            ]),
        ]);

        // 3. Cash Gift - Full verification with signature + dual notifications
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Cash Gift',
            'slug' => Str::slug('cash-gift-'.$user->id),
            'status' => 'active',
            'description' => 'Verified cash gift with signature capture and dual notifications',
            'instructions' => VoucherInstructionsData::from([
                'cash' => [
                    'amount' => 1537,
                    'currency' => 'PHP',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                ],
                'inputs' => [
                    'fields' => ['location', 'signature'],
                ],
                'feedback' => [
                    'email' => $user->email,
                    'mobile' => $user->mobile ?? null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => null,
                    'url' => null,
                ],
                'count' => 1,
                'prefix' => '',
                'mask' => '****',
                'ttl' => CarbonInterval::days(7),
            ]),
        ]);
    }
}
