<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    /**
     * Display the wallet dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        // Get recent transactions (last 5)
        $recentTransactions = $user->walletTransactions()
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => $tx->amountFloat, // Use Bavix Wallet's built-in accessor
                    'confirmed' => $tx->confirmed,
                    'created_at' => $tx->created_at,
                    'meta' => $tx->meta,
                ];
            });
        
        // Get quick stats
        $totalSpentCents = abs($user->walletTransactions()->where('amount', '<', 0)->sum('amount'));
        
        $stats = [
            'total_loaded' => $user->getPaidTopUps()->sum('amount'),
            'total_spent' => $totalSpentCents / 100, // Bavix stores in cents, convert to float
            'transaction_count' => $user->walletTransactions()->count(),
        ];
        
        return Inertia::render('wallet/Index', [
            'balance' => $user->balanceFloatNum,
            'recentTransactions' => $recentTransactions,
            'stats' => $stats,
        ]);
    }
}
