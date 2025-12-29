<?php

namespace App\Actions\Api\Transactions;

use App\Services\DisbursementStatusService;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;

/**
 * Refresh Disbursement Status
 *
 * Manually query the payment gateway to update a transaction's disbursement status.
 * 
 * This endpoint contacts the payment gateway (NetBank, etc.) to fetch the latest disbursement
 * status and updates the local transaction record. Useful when automatic status updates fail
 * or when manual verification is needed.
 * 
 * **When to Use:**
 * - Disbursement status appears stuck or outdated
 * - Customer reports payment not received but shows "pending"
 * - Troubleshooting disbursement issues
 * - Manual reconciliation with bank statements
 * 
 * **What Happens:**
 * 1. Queries payment gateway API for current status
 * 2. Updates voucher metadata with latest disbursement data
 * 3. Returns old vs. new status comparison
 * 4. Preserves operation IDs for audit trail
 * 
 * **Possible Status Changes:**
 * - pending → success (payment completed)
 * - pending → failed (payment rejected by bank)
 * - success → unchanged (already completed)
 * 
 * **Important:** This does NOT retry failed disbursements, only updates the status.
 *
 * @group Transactions
 * @authenticated
 */
#[Group('Transactions')]
class RefreshDisbursementStatus
{
    public function handle(Voucher $voucher, DisbursementStatusService $service): array
    {
        // Get current status
        $currentStatus = $voucher->metadata['disbursement']['status'] ?? null;
        
        // Update status
        $updated = $service->updateVoucherStatus($voucher);
        
        // Get new status
        $voucher->refresh();
        $newStatus = $voucher->metadata['disbursement']['status'] ?? null;
        
        return [
            'updated' => $updated,
            'current_status' => $currentStatus,
            'new_status' => $newStatus,
            'voucher_code' => $voucher->code,
            'disbursement' => $voucher->metadata['disbursement'] ?? null,
        ];
    }

    /**
     * Refresh disbursement status.
     *
     * Query payment gateway for latest status and update transaction record. Returns status comparison.
     */
    #[PathParameter('code', description: 'Voucher code to refresh disbursement status for. Must have existing disbursement data. Example: "PROMO-AB12CD34"', type: 'string', example: 'PROMO-AB12CD34')]
    public function __invoke(
        Request $request,
        string $code,
        DisbursementStatusService $service
    ): JsonResponse {
        $voucher = Voucher::where('code', $code)->firstOrFail();
        
        // Check if voucher has disbursement
        if (!isset($voucher->metadata['disbursement'])) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction has no disbursement data',
            ], 400);
        }
        
        try {
            $result = $this->handle($voucher, $service);
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $result['updated'] 
                    ? 'Status updated successfully' 
                    : 'Status unchanged',
            ]);
            
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh status: ' . $e->getMessage(),
            ], 500);
        }
    }
}
