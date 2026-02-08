<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Get Transaction Statistics
 *
 * Retrieve aggregated statistics and metrics for voucher redemption transactions.
 *
 * Provides high-level overview of transaction activity including counts, total amounts,
 * and time-based breakdowns. Essential for dashboards, KPI monitoring, and business intelligence.
 *
 * **Statistics Included:**
 * - Total transactions count and amount
 * - Today's transaction count
 * - This month's transaction count
 * - Currency information
 *
 * **Use Cases:**
 * - Dashboard widgets and charts
 * - Business performance monitoring
 * - Transaction volume analysis
 * - Financial reporting summaries
 *
 * @group Transactions
 *
 * @authenticated
 */
#[Group('Transactions')]
class GetTransactionStats
{
    /**
     * Get transaction statistics.
     *
     * Retrieve aggregated transaction metrics with optional date range filtering.
     */
    #[QueryParameter('date_from', description: '*optional* - Calculate stats from this date (YYYY-MM-DD format). Filters by redemption date. Example: 2024-01-01', type: 'string', example: '2024-01-01')]
    #[QueryParameter('date_to', description: '*optional* - Calculate stats until this date (YYYY-MM-DD format). Must be after or equal to date_from. Example: 2024-12-31', type: 'string', example: '2024-12-31')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user = $request->user();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = $user->vouchers()->whereNotNull('redeemed_at');

        // Apply date filters
        if ($dateFrom) {
            $query->whereDate('redeemed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('redeemed_at', '<=', $dateTo);
        }

        $totalTransactions = $query->count();

        // Calculate total amount
        $vouchers = $query->get();
        $totalAmount = $vouchers->sum(function ($voucher) {
            return $voucher->instructions->cash->amount ?? 0;
        });

        // Today's transactions
        $todayTransactions = $user->vouchers()->whereNotNull('redeemed_at')
            ->whereDate('redeemed_at', today())
            ->count();

        // This month's transactions
        $monthTransactions = $user->vouchers()->whereNotNull('redeemed_at')
            ->whereMonth('redeemed_at', now()->month)
            ->whereYear('redeemed_at', now()->year)
            ->count();

        return ApiResponse::success([
            'stats' => [
                'total' => $totalTransactions,
                'total_amount' => $totalAmount,
                'today' => $todayTransactions,
                'this_month' => $monthTransactions,
                'currency' => 'PHP',
            ],
        ]);
    }
}
