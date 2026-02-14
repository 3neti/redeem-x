<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PwaPortalController extends Controller
{
    /**
     * Display the PWA portal (home) page.
     *
     * Wallet-first dashboard with recent vouchers and quick actions.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get wallet balance
        $wallet = $user->wallet;
        $balance = $wallet ? $wallet->balanceFloat : 0;

        // Get recent vouchers (last 5)
        $recentVouchers = $user->vouchers()
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($voucher) {
                // Helper to extract amount from Money object
                $extractAmount = function ($moneyOrValue) {
                    if ($moneyOrValue instanceof \Brick\Money\Money) {
                        return $moneyOrValue->getAmount()->toFloat();
                    }
                    return is_numeric($moneyOrValue) ? (float) $moneyOrValue : 0;
                };
                
                // Extract amount based on voucher type
                $amount = match ($voucher->voucher_type) {
                    'payable' => $extractAmount($voucher->target_amount ?? 0),
                    'settlement' => $extractAmount($voucher->cash?->amount ?? 0), // Show loan amount
                    default => $extractAmount($voucher->cash?->amount ?? 0), // redeemable
                };
                
                return [
                    'code' => $voucher->code,
                    'amount' => $amount,
                    'target_amount' => $voucher->target_amount ? $extractAmount($voucher->target_amount) : null,
                    'currency' => $voucher->cash?->currency ?? 'PHP',
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                    'created_at' => $voucher->created_at->toIso8601String(),
                ];
            });

        // Check onboarding status
        $hasMobile = $user->mobile !== null;
        $hasMerchant = $user->merchant !== null;
        $hasBalance = $balance > 0;

        return Inertia::render('Pwa/Portal', [
            'balance' => $balance,
            'formattedBalance' => number_format($balance, 2),
            'currency' => 'PHP',
            'recentVouchers' => $recentVouchers,
            'onboarding' => [
                'hasMobile' => $hasMobile,
                'hasMerchant' => $hasMerchant,
                'hasBalance' => $hasBalance,
                'isComplete' => $hasMobile && $hasMerchant && $hasBalance,
            ],
        ]);
    }
}
