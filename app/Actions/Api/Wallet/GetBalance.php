<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\BalanceData;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * @group Wallet
 *
 * @authenticated
 *
 * Get wallet balance.
 *
 * Retrieve the authenticated user's current wallet balance with detailed breakdown.
 * Returns available balance, pending transactions, and total balance in PHP currency.
 */
#[Group('Wallet')]
class GetBalance
{
    /**
     * Get wallet balance.
     *
     * Retrieve your current wallet balance including available funds, pending amounts, and currency details.
     * Use this to verify sufficient funds before generating vouchers or making withdrawals.
     */
    public function __invoke(Request $request): array
    {
        $user = $request->user();
        $balance = BalanceData::fromWallet($user->wallet);

        return [
            'data' => $balance,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ];
    }
}
