<?php

namespace App\Actions\Wallet;

use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Number;
use LBHurtado\Wallet\Enums\WalletType;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckBalance
{
    use AsAction;

    public function handle(
        User $user,
        ?WalletType $walletType = null
    ): Money {
        $slug = is_null($walletType) 
            ? WalletType::default()->value 
            : $walletType->value;
            
        // Get or create wallet if it doesn't exist
        $wallet = $user->getWallet($slug);
        
        if (!$wallet) {
            // Create the wallet if it doesn't exist
            $wallet = $user->createWallet([
                'name' => $slug,
                'slug' => $slug,
            ]);
        }
        
        $float = (float) $wallet->balanceFloat;

        return Money::of($float, Number::defaultCurrency());
    }
}
