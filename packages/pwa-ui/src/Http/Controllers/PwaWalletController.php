<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PwaWalletController extends Controller
{
    /**
     * Display wallet page with balance and top-up options.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $user->wallet;
        $balance = $wallet ? $wallet->balanceFloat : 0;

        // Get recent transactions (last 10)
        $recentTransactions = $user->walletTransactions()
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => $tx->amountFloat, // Use Bavix Wallet's built-in accessor
                    'confirmed' => $tx->confirmed,
                    'created_at' => $tx->created_at->toIso8601String(),
                    'meta' => $tx->meta,
                ];
            });

        return Inertia::render('pwa/Wallet', [
            'balance' => $balance,
            'formattedBalance' => number_format($balance, 2),
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
