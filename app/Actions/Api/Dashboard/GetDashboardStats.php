<?php

declare(strict_types=1);

namespace App\Actions\Api\Dashboard;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;

/**
 * Get dashboard statistics via API.
 *
 * Endpoint: GET /api/v1/dashboard/stats
 */
class GetDashboardStats
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Voucher Stats
        $voucherStats = $this->getVoucherStats($user);

        // Transaction Stats (Disbursements)
        $transactionStats = $this->getTransactionStats($user);

        // Deposit Stats
        $depositStats = $this->getDepositStats($user);

        // Wallet Stats
        $walletStats = $this->getWalletStats($user);

        // Billing Stats
        $billingStats = $this->getBillingStats($user);

        // Disbursement Success Rate
        $disbursementStats = $this->getDisbursementStats();
        
        // Settlement Voucher Stats
        $settlementStats = $this->getSettlementStats($user);

        return ApiResponse::success([
            'stats' => [
                'vouchers' => $voucherStats,
                'transactions' => $transactionStats,
                'deposits' => $depositStats,
                'wallet' => $walletStats,
                'billing' => $billingStats,
                'disbursements' => $disbursementStats,
                'settlements' => $settlementStats,
            ],
        ]);
    }

    private function getVoucherStats($user): array
    {
        $totalVouchers = $user->vouchers()->count();
        
        $activeVouchers = $user->vouchers()
            ->whereNull('redeemed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $redeemedVouchers = $user->vouchers()
            ->whereNotNull('redeemed_at')
            ->count();

        $expiredVouchers = $user->vouchers()
            ->whereNull('redeemed_at')
            ->where('expires_at', '<', now())
            ->count();

        return [
            'total' => $totalVouchers,
            'active' => $activeVouchers,
            'redeemed' => $redeemedVouchers,
            'expired' => $expiredVouchers,
        ];
    }

    private function getTransactionStats($user): array
    {
        $today = $user->vouchers()->whereNotNull('redeemed_at')
            ->whereDate('redeemed_at', today())
            ->count();

        $thisMonth = $user->vouchers()->whereNotNull('redeemed_at')
            ->whereMonth('redeemed_at', now()->month)
            ->whereYear('redeemed_at', now()->year)
            ->count();

        $vouchers = $user->vouchers()->whereNotNull('redeemed_at')
            ->whereMonth('redeemed_at', now()->month)
            ->whereYear('redeemed_at', now()->year)
            ->get();

        $totalAmount = $vouchers->sum(function ($voucher) {
            return $voucher->instructions->cash->amount ?? 0;
        });

        return [
            'today' => $today,
            'this_month' => $thisMonth,
            'total_amount' => $totalAmount,
            'currency' => 'PHP',
        ];
    }

    private function getDepositStats($user): array
    {
        // Calculate total deposits from wallet transactions + sender transactions
        $walletDeposits = $user->walletTransactions()
            ->where('type', 'deposit')
            ->where('amount', '>', 0)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();

        $totalAmount = $walletDeposits->sum(function ($tx) {
            return $tx->amount / 100; // Convert cents to pesos
        });

        $todayCount = $user->walletTransactions()
            ->where('type', 'deposit')
            ->where('amount', '>', 0)
            ->whereDate('created_at', today())
            ->count();

        $thisMonthCount = $walletDeposits->count();

        // Get unique senders
        $uniqueSenders = $user->senders()
            ->wherePivot('last_transaction_at', '>=', now()->startOfMonth())
            ->count();

        return [
            'today' => $todayCount,
            'this_month' => $thisMonthCount,
            'total_amount' => $totalAmount,
            'unique_senders' => $uniqueSenders,
            'currency' => 'PHP',
        ];
    }

    private function getWalletStats($user): array
    {
        return [
            'balance' => $user->balanceFloatNum,
            'currency' => 'PHP',
        ];
    }

    private function getBillingStats($user): array
    {
        $currentMonthCharges = $user->monthlyCharges(now()->year, now()->month)
            ->sum('total_charge');

        $totalVouchersGenerated = $user->voucherGenerationCharges()
            ->sum('voucher_count');

        return [
            'current_month_charges' => $currentMonthCharges,
            'total_vouchers_generated' => $totalVouchersGenerated,
            'currency' => 'PHP',
        ];
    }

    private function getDisbursementStats(): array
    {
        // Get disbursement attempts from the last 30 days
        $attempts = DisbursementAttempt::where('attempted_at', '>=', now()->subDays(30))
            ->get();

        $total = $attempts->count();
        $successful = $attempts->where('status', 'success')->count();
        $failed = $attempts->where('status', 'failed')->count();

        $successRate = $total > 0 ? round(($successful / $total) * 100, 1) : 0;

        return [
            'success_rate' => $successRate,
            'total_attempts' => $total,
            'successful' => $successful,
            'failed' => $failed,
        ];
    }
    
    private function getSettlementStats($user): array
    {
        // Total payable/settlement vouchers
        $settlementVouchers = $user->vouchers()
            ->whereIn('voucher_type', ['payable', 'settlement'])
            ->get();
        
        $totalPayable = $settlementVouchers->where('voucher_type', 'payable')->count();
        $totalSettlement = $settlementVouchers->where('voucher_type', 'settlement')->count();
        
        // Active vs Closed
        $activeCount = $settlementVouchers->where('state', 'active')->count();
        $closedCount = $settlementVouchers->where('state', 'closed')->count();
        
        // Total amount collected via payments
        $totalCollected = $settlementVouchers->sum(function ($voucher) {
            return $voucher->getPaidTotal();
        });
        
        // Total target amount
        $totalTarget = $settlementVouchers->sum('target_amount');
        
        return [
            'total_payable' => $totalPayable,
            'total_settlement' => $totalSettlement,
            'total_vouchers' => $settlementVouchers->count(),
            'active_count' => $activeCount,
            'closed_count' => $closedCount,
            'total_collected' => $totalCollected,
            'total_target' => $totalTarget,
            'currency' => 'PHP',
        ];
    }
}
