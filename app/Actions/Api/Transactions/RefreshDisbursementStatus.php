<?php

namespace App\Actions\Api\Transactions;

use App\Services\DisbursementStatusService;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Refresh disbursement status for a transaction
 * 
 * Queries the payment gateway for the latest status and updates the voucher.
 */
class RefreshDisbursementStatus
{
    use AsAction;

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
     * Handle as controller
     */
    public function asController(
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
