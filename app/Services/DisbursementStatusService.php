<?php

namespace App\Services;

use App\Services\DataEnrichers\DataEnricherRegistry;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use LBHurtado\Voucher\Data\DisbursementData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing disbursement status updates
 * 
 * Orchestrates polling the payment gateway for transaction status
 * and updating voucher records accordingly.
 */
class DisbursementStatusService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}
    
    /**
     * Update status for a single voucher
     *
     * @param Voucher $voucher
     * @return bool True if status was updated, false otherwise
     */
    public function updateVoucherStatus(Voucher $voucher): bool
    {
        $disbursement = DisbursementData::fromMetadata($voucher->metadata);
        
        if (!$disbursement) {
            Log::warning('[StatusService] No disbursement data', ['voucher' => $voucher->code]);
            return false;
        }
        
        // Skip if already in final state
        if ($disbursement->isFinal()) {
            Log::debug('[StatusService] Already in final state', [
                'voucher' => $voucher->code,
                'status' => $disbursement->status
            ]);
            return false;
        }
        
        // Check status with gateway
        $result = $this->gateway->checkDisbursementStatus($disbursement->transaction_id);
        $newStatus = $result['status'];
        
        // No change
        if ($newStatus === $disbursement->status) {
            Log::debug('[StatusService] Status unchanged', [
                'voucher' => $voucher->code,
                'status' => $newStatus
            ]);
            return false;
        }
        
        // Update voucher metadata with enhanced data
        $metadata = $voucher->metadata;
        $metadata['disbursement']['status'] = $newStatus;
        $metadata['disbursement']['status_updated_at'] = now()->toIso8601String();
        
        // Extract rich data using gateway-specific enricher
        if (!empty($result['raw'])) {
            $enricher = app(DataEnricherRegistry::class)->getEnricher($disbursement->gateway);
            $enricher->extract($metadata, $result['raw']);
        }
        
        // Store complete raw response for audit
        $metadata['disbursement']['status_raw'] = $result['raw'];
        
        $voucher->metadata = $metadata;
        $voucher->save();
        
        Log::info('[StatusService] Status updated', [
            'voucher' => $voucher->code,
            'old_status' => $disbursement->status,
            'new_status' => $newStatus,
        ]);
        
        // Dispatch event if now in final state
        $updatedStatus = DisbursementStatus::fromGateway($disbursement->gateway, $newStatus);
        if ($updatedStatus->isFinal()) {
            event(new DisbursementConfirmed($voucher));
            
            Log::info('[StatusService] Disbursement finalized', [
                'voucher' => $voucher->code,
                'final_status' => $newStatus,
            ]);
        }
        
        return true;
    }
    
    /**
     * Update status for multiple vouchers with pending disbursements
     *
     * @param int $limit Maximum number of vouchers to process
     * @return int Number of vouchers updated
     */
    public function updatePendingVouchers(int $limit = 100): int
    {
        $updated = 0;
        
        // Get vouchers with pending disbursements
        $vouchers = Voucher::query()
            ->whereNotNull('redeemed_at')
            ->whereNotNull('metadata->disbursement')
            ->whereIn('metadata->disbursement->status', ['pending', 'processing'])
            ->limit($limit)
            ->get();
        
        Log::info('[StatusService] Checking pending vouchers', [
            'count' => $vouchers->count(),
            'limit' => $limit
        ]);
        
        foreach ($vouchers as $voucher) {
            try {
                if ($this->updateVoucherStatus($voucher)) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                Log::error('[StatusService] Failed to update voucher', [
                    'voucher' => $voucher->code,
                    'error' => $e->getMessage()
                ]);
                // Continue with next voucher
            }
        }
        
        Log::info('[StatusService] Batch update complete', [
            'checked' => $vouchers->count(),
            'updated' => $updated
        ]);
        
        return $updated;
    }
    
}
