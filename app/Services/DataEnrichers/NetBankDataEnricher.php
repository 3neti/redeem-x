<?php

namespace App\Services\DataEnrichers;

use Illuminate\Support\Facades\Log;

/**
 * NetBank-specific data enricher.
 * 
 * Extracts rich metadata from NetBank API responses including:
 * - Settled timestamp
 * - Reference number
 * - Fees
 * - Status history
 * - Sender information
 * - Settlement rail confirmation
 */
class NetBankDataEnricher extends AbstractDataEnricher
{
    /**
     * Check if this enricher supports the given gateway.
     *
     * @param string $gateway Gateway name
     * @return bool
     */
    public function supports(string $gateway): bool
    {
        return strtolower($gateway) === 'netbank';
    }
    
    /**
     * Extract rich data from NetBank API response.
     *
     * @param array &$metadata Voucher metadata (passed by reference)
     * @param array $raw Raw NetBank API response
     * @return void
     */
    public function extract(array &$metadata, array $raw): void
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
        
        Log::debug('[NetBankEnricher] Extracted rich data', [
            'has_settled_at' => isset($metadata['disbursement']['settled_at']),
            'has_reference_number' => isset($metadata['disbursement']['reference_number']),
            'has_fees' => isset($metadata['disbursement']['fees']),
            'status_history_count' => count($metadata['disbursement']['status_history'] ?? []),
        ]);
    }
}
