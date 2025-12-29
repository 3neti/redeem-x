<?php

declare(strict_types=1);

namespace App\Actions\Api\Reports;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;

/**
 * Get Settlement Report by Rail
 *
 * Retrieve disbursements grouped by settlement rail (INSTAPAY/PESONET) for bank settlement reconciliation.
 * 
 * Banks settle transactions separately per rail with different timelines:
 * - **INSTAPAY**: Real-time settlement, same-day cutoff times
 * - **PESONET**: Next business day settlement, batch processing
 * 
 * This report format matches bank settlement reports for easy reconciliation.
 * 
 * **Report Structure:**
 * - Grouped by settlement rail
 * - Further grouped by status within each rail
 * - Transaction count and total amount per group
 * - Individual transaction details for matching
 * 
 * **Use Cases:**
 * - Daily settlement reconciliation with banks
 * - Rail-specific financial reporting
 * - Identifying rail-specific issues
 * - Forecasting settlement amounts by rail
 * - SLA compliance per rail (INSTAPAY vs PESONET)
 *
 * @group Reports
 * @authenticated
 */
#[Group('Reports')]
class GetSettlementReport
{
    /**
     * Get settlement report grouped by rail.
     *
     * Retrieve disbursements organized by settlement rail for bank reconciliation matching.
     */
    #[QueryParameter('from_date', description: '**REQUIRED**. Start date (YYYY-MM-DD). Typically settlement date from bank. Example: 2024-01-01', type: 'string', example: '2024-01-01')]
    #[QueryParameter('to_date', description: '**REQUIRED**. End date (YYYY-MM-DD). Example: 2024-01-31', type: 'string', example: '2024-01-31')]
    #[QueryParameter('settlement_rail', description: '*optional* - Limit to specific rail. "INSTAPAY" or "PESONET". Omit for both rails in separate sections.', type: 'string', example: 'INSTAPAY')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'settlement_rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
        ]);

        $dateRange = [
            $request->input('from_date'),
            $request->input('to_date') . ' 23:59:59',
        ];

        $query = DisbursementAttempt::query()
            ->whereBetween('attempted_at', $dateRange)
            ->orderBy('settlement_rail')
            ->orderByDesc('attempted_at');

        if ($rail = $request->input('settlement_rail')) {
            $query->where('settlement_rail', $rail);
        }

        $disbursements = $query->get();

        // Group by rail, then by status
        $byRail = $disbursements->groupBy('settlement_rail')->map(function ($railGroup) {
            $byStatus = $railGroup->groupBy('status');
            
            return [
                'totals' => [
                    'count' => $railGroup->count(),
                    'amount' => $railGroup->sum('amount'),
                    'currency' => 'PHP',
                ],
                'by_status' => $byStatus->map(function ($statusGroup, $status) {
                    return [
                        'status' => $status,
                        'count' => $statusGroup->count(),
                        'amount' => $statusGroup->sum('amount'),
                        'disbursements' => $statusGroup->values(),
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'data' => [
                'settlement_date_range' => [
                    'from' => $request->input('from_date'),
                    'to' => $request->input('to_date'),
                ],
                'by_rail' => $byRail,
                'grand_total' => [
                    'count' => $disbursements->count(),
                    'amount' => $disbursements->sum('amount'),
                    'currency' => 'PHP',
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'note' => 'Grouped by settlement rail for bank reconciliation',
            ],
        ]);
    }
}
