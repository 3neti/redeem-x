<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\User;
use Carbon\CarbonInterval;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class VoucherTestHelper
{
    public static function createVouchersWithInstructions(
        User $user,
        int $count = 1,
        string $prefix = '',
        ?array $customInstructions = null
    ) {
        $instructions = VoucherInstructionsData::from($customInstructions ?? [
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
            'inputs' => ['fields' => []],
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => ['message' => null, 'url' => null],
            'count' => $count,
            'prefix' => $prefix,
            'mask' => '****',
            'ttl' => CarbonInterval::hours(12),
        ]);

        // Authenticate as user for GenerateVouchers
        // Set user on default guard for GenerateVouchers to find
        auth()->setUser($user);
        
        return GenerateVouchers::run($instructions);
    }
}
