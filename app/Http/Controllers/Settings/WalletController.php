<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    /**
     * Show the user's wallet settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        
        // Get or create default wallet
        $wallet = $user->wallet ?? $user->createWallet([
            'name' => 'Default Wallet',
        ]);

        // Get wallet transactions (recent 10)
        $transactions = $wallet->transactions()
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount / 100, // Convert from minor to major units
                    'confirmed' => $transaction->confirmed,
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('settings/Wallet', [
            'wallet' => [
                'balance' => $wallet->balanceFloat,
                'currency' => 'PHP',
            ],
            'transactions' => $transactions,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Add funds to the user's wallet.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
        ]);

        $user = $request->user();
        $wallet = $user->wallet ?? $user->createWallet([
            'name' => 'Default Wallet',
        ]);

        // Deposit funds
        $wallet->depositFloat($request->amount);

        return to_route('wallet.edit')->with('status', 'Funds added successfully!');
    }
}
