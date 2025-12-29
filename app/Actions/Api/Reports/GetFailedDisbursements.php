<?php

declare(strict_types=1);

namespace App\Actions\Api\Reports;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;

/**
 * Get Failed Disbursements Report
 *
 * Retrieve only failed disbursement attempts for troubleshooting and recovery operations.
 * 
 * This specialized endpoint filters for failed disbursements, essential for operational
 * teams to identify and resolve payment issues quickly. Includes detailed error information
 * to diagnose root causes.
 * 
 * **Use Cases:**
 * - Daily failed disbursement review
 * - Customer support escalations
 * - Gateway performance monitoring
 * - Identifying systematic issues (e.g., bank downtime)
 * - Planning retry strategies
 * 
 * **Error Categories:**
 * - **timeout** - Gateway did not respond in time
 * - **gateway_error** - Payment gateway returned error
 * - **insufficient_funds** - User account has insufficient balance
 * - **invalid_account** - Recipient account invalid/closed
 * - **bank_rejected** - Receiving bank rejected transaction
 * 
 * **Data Includes:**
 * - All disbursement attempt details
 * - Specific error type and message
 * - Error details (JSON) for debugging
 * - Request/response payloads for analysis
 *
 * @group Reports
 * @authenticated
 */
#[Group('Reports')]
class GetFailedDisbursements
{
    /**
     * Get failed disbursements report.
     *
     * Retrieve list of only failed disbursement attempts with error details for troubleshooting.
     */
    #[QueryParameter('from_date', description: '**REQUIRED**. Start date for report (YYYY-MM-DD format). Example: 2024-01-01', type: 'string', example: '2024-01-01')]
    #[QueryParameter('to_date', description: '**REQUIRED**. End date for report (YYYY-MM-DD format). Must be after or equal to from_date. Example: 2024-01-31', type: 'string', example: '2024-01-31')]
    #[QueryParameter('settlement_rail', description: '*optional* - Filter by settlement rail. "INSTAPAY" or "PESONET". Useful for rail-specific failure analysis.', type: 'string', example: 'INSTAPAY')]
    #[QueryParameter('gateway', description: '*optional* - Filter by payment gateway. For multi-gateway failure comparison.', type: 'string', example: 'netbank')]
    #[QueryParameter('error_type', description: '*optional* - Filter by specific error type. Common values: "timeout", "gateway_error", "insufficient_funds", "invalid_account", "bank_rejected".', type: 'string', example: 'timeout')]
    #[QueryParameter('per_page', description: '*optional* - Results per page (1-500). Default: 100.', type: 'integer', example: 100)]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'settlement_rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
            'gateway' => ['nullable', 'string'],
            'error_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = DisbursementAttempt::query()
            ->failed()
            ->whereBetween('attempted_at', [
                $request->input('from_date'),
                $request->input('to_date') . ' 23:59:59',
            ])
            ->orderByDesc('attempted_at');

        // Apply filters
        if ($rail = $request->input('settlement_rail')) {
            $query->where('settlement_rail', $rail);
        }

        if ($gateway = $request->input('gateway')) {
            $query->byGateway($gateway);
        }

        if ($errorType = $request->input('error_type')) {
            $query->byErrorType($errorType);
        }

        $perPage = min($request->integer('per_page', 100), 500);
        $disbursements = $query->paginate($perPage);

        // Group by error type for quick insights
        $errorTypeCounts = DisbursementAttempt::query()
            ->failed()
            ->whereBetween('attempted_at', [
                $request->input('from_date'),
                $request->input('to_date') . ' 23:59:59',
            ])
            ->selectRaw('error_type, COUNT(*) as count')
            ->groupBy('error_type')
            ->pluck('count', 'error_type');

        return response()->json([
            'data' => $disbursements->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $disbursements->currentPage(),
                    'per_page' => $disbursements->perPage(),
                    'total' => $disbursements->total(),
                    'last_page' => $disbursements->lastPage(),
                ],
                'error_breakdown' => $errorTypeCounts,
                'filters_applied' => [
                    'from_date' => $request->input('from_date'),
                    'to_date' => $request->input('to_date'),
                    'settlement_rail' => $rail ?? null,
                    'gateway' => $gateway ?? null,
                    'error_type' => $errorType ?? null,
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
