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

        // Get recent top-ups
        $topUps = $user->topUps()
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($topUp) {
                $amount = $topUp->amount;
                $amountFloat = is_numeric($amount) ? (float) $amount : 0;
                
                return [
                    'reference' => $topUp->reference,
                    'amount' => $amountFloat,
                    'currency' => $topUp->currency,
                    'status' => $topUp->status,
                    'created_at' => $topUp->created_at->toIso8601String(),
                ];
            });

        return Inertia::render('Pwa/Wallet', [
            'balance' => $balance,
            'formattedBalance' => number_format($balance, 2),
            'currency' => 'PHP',
            'topUps' => $topUps,
        ]);
    }
}
