<?php

declare(strict_types=1);

namespace App\Actions\Api\Reports;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;

/**
 * Get Disbursement Report
 *
 * Retrieve comprehensive list of all disbursement attempts with filtering for reconciliation and auditing.
 * 
 * This endpoint exposes the complete audit trail of disbursement transactions,
 * essential for daily bank reconciliation, financial reporting, and compliance auditing.
 * 
 * **Use Cases:**
 * - Daily settlement reconciliation with banks
 * - Monthly financial reporting
 * - Audit trail for compliance
 * - Investigating specific disbursement issues
 * - Exporting data for accounting software
 * 
 * **Data Includes:**
 * - Voucher code and reference IDs
 * - Amount, currency, and recipient details
 * - Bank code and settlement rail (INSTAPAY/PESONET)
 * - Disbursement status and timestamps
 * - Gateway transaction IDs for bank matching
 * - Error details for failed attempts
 * 
 * **Filtering:**
 * - Date range (required for performance)
 * - Status (success/failed/pending)
 * - Settlement rail (INSTAPAY/PESONET)
 * - Gateway (netbank/icash)
 * - Error type for troubleshooting
 *
 * @group Reports
 * @authenticated
 */
#[Group('Reports')]
class GetDisbursementReport
{
    /**
     * Get disbursement report.
     *
     * Retrieve paginated list of disbursement attempts with comprehensive filtering for reconciliation.
     */
    #[QueryParameter('from_date', description: '**REQUIRED**. Start date for report (YYYY-MM-DD format). Reports are date-bounded for performance. Example: 2024-01-01', type: 'string', example: '2024-01-01')]
    #[QueryParameter('to_date', description: '**REQUIRED**. End date for report (YYYY-MM-DD format). Must be after or equal to from_date. Maximum 90-day range. Example: 2024-01-31', type: 'string', example: '2024-01-31')]
    #[QueryParameter('status', description: '*optional* - Filter by disbursement status. Values: "success" (completed), "failed" (rejected by bank), "pending" (in progress). Omit for all statuses.', type: 'string', example: 'success')]
    #[QueryParameter('settlement_rail', description: '*optional* - Filter by settlement rail. "INSTAPAY" (real-time, ≤₱50k) or "PESONET" (next day, ≤₱1M). Used for rail-specific reconciliation.', type: 'string', example: 'INSTAPAY')]
    #[QueryParameter('gateway', description: '*optional* - Filter by payment gateway. "netbank" (NetBank gateway), "icash" (iCash gateway). For multi-gateway deployments.', type: 'string', example: 'netbank')]
    #[QueryParameter('error_type', description: '*optional* - Filter by error type for troubleshooting. Common values: "timeout", "gateway_error", "insufficient_funds", "invalid_account".', type: 'string', example: 'timeout')]
    #[QueryParameter('per_page', description: '*optional* - Results per page (1-500). Default: 100. Use larger values for bulk exports.', type: 'integer', example: 100)]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'status' => ['nullable', 'string', 'in:success,failed,pending'],
            'settlement_rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
            'gateway' => ['nullable', 'string'],
            'error_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = DisbursementAttempt::query()
            ->whereBetween('attempted_at', [
                $request->input('from_date'),
                $request->input('to_date') . ' 23:59:59',
            ])
            ->orderByDesc('attempted_at');

        // Apply filters
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

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

        return response()->json([
            'data' => $disbursements->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $disbursements->currentPage(),
                    'per_page' => $disbursements->perPage(),
                    'total' => $disbursements->total(),
                    'last_page' => $disbursements->lastPage(),
                ],
                'filters_applied' => [
                    'from_date' => $request->input('from_date'),
                    'to_date' => $request->input('to_date'),
                    'status' => $status ?? null,
                    'settlement_rail' => $rail ?? null,
                    'gateway' => $gateway ?? null,
                    'error_type' => $errorType ?? null,
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
