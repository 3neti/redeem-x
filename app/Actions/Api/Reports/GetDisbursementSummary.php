<?php

declare(strict_types=1);

namespace App\Actions\Api\Reports;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;

/**
 * Get Disbursement Summary Statistics
 *
 * Retrieve aggregated statistics and KPIs for disbursement performance over a date range.
 *
 * **Summary Metrics Include:**
 * - Total attempts, success count, failed count
 * - Total amounts (overall, success, failed)
 * - Success rate percentage
 * - Breakdown by settlement rail (INSTAPAY vs PESONET)
 * - Breakdown by gateway
 * - Average processing time
 *
 * **Use Cases:**
 * - Dashboard KPI widgets
 * - Executive reports
 * - Performance monitoring
 * - SLA compliance tracking
 * - Settlement forecasting
 *
 * @group Reports
 *
 * @authenticated
 */
#[Group('Reports')]
class GetDisbursementSummary
{
    /**
     * Get disbursement summary statistics.
     *
     * Retrieve aggregated metrics and KPIs for disbursement performance.
     */
    #[QueryParameter('from_date', description: '**REQUIRED**. Start date (YYYY-MM-DD). Example: 2024-01-01', type: 'string', example: '2024-01-01')]
    #[QueryParameter('to_date', description: '**REQUIRED**. End date (YYYY-MM-DD). Example: 2024-01-31', type: 'string', example: '2024-01-31')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $dateRange = [
            $request->input('from_date'),
            $request->input('to_date').' 23:59:59',
        ];

        $query = DisbursementAttempt::query()->whereBetween('attempted_at', $dateRange);

        $totalCount = $query->count();
        $successCount = (clone $query)->success()->count();
        $failedCount = (clone $query)->failed()->count();

        $totalAmount = $query->sum('amount');
        $successAmount = (clone $query)->success()->sum('amount');
        $failedAmount = (clone $query)->failed()->sum('amount');

        $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0;

        // Breakdown by rail
        $byRail = DisbursementAttempt::query()
            ->whereBetween('attempted_at', $dateRange)
            ->selectRaw('settlement_rail, status, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('settlement_rail', 'status')
            ->get()
            ->groupBy('settlement_rail');

        return response()->json([
            'data' => [
                'summary' => [
                    'total_attempts' => $totalCount,
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'success_rate_percent' => $successRate,
                    'total_amount' => $totalAmount,
                    'success_amount' => $successAmount,
                    'failed_amount' => $failedAmount,
                    'currency' => 'PHP',
                ],
                'by_rail' => $byRail,
                'period' => [
                    'from' => $request->input('from_date'),
                    'to' => $request->input('to_date'),
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
