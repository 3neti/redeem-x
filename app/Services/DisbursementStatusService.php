<?php

namespace App\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use LBHurtado\PaymentGateway\Events\DisbursementConfirmed;
use LBHurtado\Voucher\Data\DisbursementData;
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
        
        // Extract rich data from NetBank response
        if ($disbursement->gateway === 'netbank' && !empty($result['raw'])) {
            $this->extractNetBankData($metadata, $result['raw']);
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
    
    /**
     * Extract rich data from NetBank API response
     *
     * @param array &$metadata Voucher metadata (passed by reference)
     * @param array $raw Raw NetBank API response
     * @return void
     */
    protected function extractNetBankData(array &$metadata, array $raw): void
    {
        // Extract settled timestamp (when it actually completed)
        if (isset($raw['status_details']) && is_array($raw['status_details'])) {
            foreach ($raw['status_details'] as $statusDetail) {
                if (isset($statusDetail['status']) && strtolower($statusDetail['status']) === 'settled') {
                    $metadata['disbursement']['settled_at'] = $statusDetail['updated'] ?? null;
                    break;
                }
            }
        }
        
        // Extract reference number (bank's reference)
        if (isset($raw['reference_number'])) {
            $metadata['disbursement']['reference_number'] = $raw['reference_number'];
        }
        
        // Extract fees
        if (isset($raw['fees']) && is_array($raw['fees']) && !empty($raw['fees'])) {
            $firstFee = $raw['fees'][0] ?? null;
            if ($firstFee && isset($firstFee['amount'])) {
                $metadata['disbursement']['fees'] = [
                    'amount' => $firstFee['amount']['num'] ?? null,
                    'currency' => $firstFee['amount']['cur'] ?? 'PHP',
                ];
            }
        }
        
        // Extract status history
        if (isset($raw['status_details']) && is_array($raw['status_details'])) {
            $metadata['disbursement']['status_history'] = array_map(function ($detail) {
                return [
                    'status' => $detail['status'] ?? 'Unknown',
                    'timestamp' => $detail['updated'] ?? null,
                ];
            }, $raw['status_details']);
        }
        
        // Extract sender name (might be useful)
        if (isset($raw['sender_name'])) {
            $metadata['disbursement']['sender_name'] = $raw['sender_name'];
        }
        
        // Extract settlement rail (confirm what was used)
        if (isset($raw['settlement_rail'])) {
            $metadata['disbursement']['metadata']['rail'] = $raw['settlement_rail'];
        }
        
        Log::debug('[StatusService] Extracted NetBank rich data', [
            'has_settled_at' => isset($metadata['disbursement']['settled_at']),
            'has_reference_number' => isset($metadata['disbursement']['reference_number']),
            'has_fees' => isset($metadata['disbursement']['fees']),
            'status_history_count' => count($metadata['disbursement']['status_history'] ?? []),
        ]);
    }
}
