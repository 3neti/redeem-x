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

        // Get voucher stats
        $activeVouchersCount = $user->vouchers()
            ->whereIn('state', ['active', 'locked'])
            ->count();

        // Redeemed = has redeemed_at date, regardless of state
        $redeemedThisMonthCount = $user->vouchers()
            ->whereNotNull('redeemed_at')
            ->whereMonth('redeemed_at', now()->month)
            ->whereYear('redeemed_at', now()->year)
            ->count();

        // Calculate total issued this month
        // Load voucherEntities relationship to access cash via accessor
        $vouchersIssuedThisMonth = $user->vouchers()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->with('voucherEntities.entity')
            ->get();

        $totalIssuedThisMonth = $vouchersIssuedThisMonth->sum(function ($voucher) {
            if (!$voucher->cash || !$voucher->cash->amount) {
                return 0;
            }
            $amount = $voucher->cash->amount;
            if ($amount instanceof \Brick\Money\Money) {
                return $amount->getAmount()->toFloat();
            }
            return is_numeric($amount) ? (float) $amount : 0;
        });

        // Get expiring vouchers (within 7 days) that are still active
        $expiringVouchersCount = $user->vouchers()
            ->where('state', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        // Check onboarding status
        $hasMobile = $user->mobile !== null;
        $hasMerchant = $user->merchant !== null;
        $hasBalance = $balance > 0;

        // Build alerts array
        $alerts = [];
        
        // Low balance alert
        if ($balance < 100) {
            $alerts[] = [
                'type' => 'low_balance',
                'message' => 'Wallet balance below ₱100',
                'action' => '/pwa/topup',
                'action_label' => 'Add Funds',
            ];
        }

        // Expiring vouchers alert
        if ($expiringVouchersCount > 0) {
            $alerts[] = [
                'type' => 'expiring_vouchers',
                'message' => "{$expiringVouchersCount} voucher(s) expiring within 7 days",
                'action' => '/pwa/vouchers?filter=active',
                'action_label' => 'View Vouchers',
            ];
        }

        return Inertia::render('pwa/Portal', [
            'balance' => $balance,
            'formattedBalance' => number_format($balance, 2),
            'currency' => 'PHP',
            'stats' => [
                'active_vouchers_count' => $activeVouchersCount,
                'redeemed_this_month_count' => $redeemedThisMonthCount,
                'total_issued_this_month' => $totalIssuedThisMonth,
                'formatted_total_issued_this_month' => number_format($totalIssuedThisMonth, 2),
            ],
            'alerts' => $alerts,
            'onboarding' => [
                'hasMobile' => $hasMobile,
                'hasMerchant' => $hasMerchant,
                'hasBalance' => $hasBalance,
                'isComplete' => $hasMobile && $hasMerchant && $hasBalance,
            ],
        ]);
    }
}
