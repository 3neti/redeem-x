<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\BalanceData;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBalance
{
    use AsAction;

    /**
     * Get the authenticated user's wallet balance.
     */
    public function handle(User $user): BalanceData
    {
        return BalanceData::fromWallet($user->wallet);
    }

    /**
     * Handle as controller action.
     */
    public function asController(): array
    {
        $user = auth()->user();
        $balance = $this->handle($user);

        return [
            'data' => $balance,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ];
    }
}
