<?php

namespace App\Actions\Settings;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class CreateDefaultCampaigns
{
    public function handle(User $user): void
    {
        // 1. Blank Template
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Blank Template',
            'slug' => Str::slug('blank-template-' . $user->id),
            'status' => 'active',
            'description' => 'Start from scratch with no pre-filled fields',
            'instructions' => VoucherInstructionsData::generateFromScratch(),
        ]);

        // 2. Standard Campaign
        Campaign::create([
            'user_id' => $user->id,
            'name' => 'Standard Campaign',
            'slug' => Str::slug('standard-campaign-' . $user->id),
            'status' => 'active',
            'description' => 'Full verification with selfie, signature, location, and contact info',
            'instructions' => VoucherInstructionsData::from([
                'cash' => [
                    'amount' => 100,
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
                    'fields' => [
                        'selfie',
                        'signature',
                        'location',
                        'name',
                        'email',
                        'mobile',
                    ],
                ],
                'feedback' => [
                    'email' => $user->email,
                    'mobile' => $user->mobile ?? null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => 'Thank you for redeeming your voucher!',
                    'url' => null,
                ],
                'count' => 1,
                'prefix' => '',
                'mask' => '****',
                'ttl' => null,
            ]),
        ]);
    }
}
