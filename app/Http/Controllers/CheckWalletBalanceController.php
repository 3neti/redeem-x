<?php

namespace App\Http\Controllers;

use App\Actions\CheckBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\Wallet\Enums\WalletType;

class CheckWalletBalanceController extends Controller
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $walletType = $request->has('type')
            ? WalletType::from($request->input('type'))
            : null;

        $balance = CheckBalance::run($user, $walletType);

        if ($request->wantsJson()) {
            return response()->json([
                'balance' => $balance->getAmount()->toFloat(),
                'currency' => $balance->getCurrency()->getCurrencyCode(),
                'type' => $walletType?->value ?? 'default',
                'datetime' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        return Inertia::render('Wallet/Balance', [
            'balance' => [
                'amount' => $balance->getAmount()->toFloat(),
                'currency' => $balance->getCurrency()->getCurrencyCode(),
                'type' => $walletType?->value ?? 'default',
                'datetime' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
