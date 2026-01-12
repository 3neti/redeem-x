<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Settings\VoucherSettings;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;

/**
 * Reusable Disbursement Service
 * 
 * Handles bank disbursements for:
 * - Voucher redemptions (existing flow via DisburseCash pipeline)
 * - Settlement auto-disbursements (new feature)
 * 
 * Responsibilities:
 * - Validate minimum threshold
 * - Build disburse input data
 * - Call payment gateway
 * - Handle errors and logging
 */
class DisbursementService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected VoucherSettings $settings
    ) {}

    /**
     * Check if amount meets minimum threshold for disbursement.
     * 
     * @param float $amount Amount in pesos
     * @return bool True if amount >= threshold
     */
    public function meetsMinimumThreshold(float $amount): bool
    {
        $threshold = $this->settings->auto_disburse_minimum;
        return $amount >= $threshold;
    }

    /**
     * Disburse funds to user's bank account.
     * 
     * @param User $user User to disburse to
     * @param float $amount Amount in pesos
     * @param array $bankAccount Bank account data ['bank_code' => string, 'account_number' => string]
     * @param string|null $settlementRail Settlement rail (INSTAPAY/PESONET), null for auto
     * @param array $metadata Additional metadata to log
     * @return array{success: bool, message: string, transaction_id: ?string, error: ?string}
     */
    public function disburse(
        User $user,
        float $amount,
        array $bankAccount,
        ?string $settlementRail = null,
        array $metadata = []
    ): array {
        // Validate inputs
        if (empty($bankAccount['bank_code']) || empty($bankAccount['account_number'])) {
            return [
                'success' => false,
                'message' => 'Invalid bank account data',
                'transaction_id' => null,
                'error' => 'Missing bank_code or account_number',
            ];
        }

        // Check minimum threshold
        if (!$this->meetsMinimumThreshold($amount)) {
            $threshold = $this->settings->auto_disburse_minimum;
            return [
                'success' => false,
                'message' => "Amount ₱{$amount} is below minimum threshold ₱{$threshold}",
                'transaction_id' => null,
                'error' => 'below_threshold',
            ];
        }

        // Determine settlement rail
        $rail = $this->determineSettlementRail($amount, $settlementRail);

        // Build disbursement input data
        // Format: VOUCHER-MOBILE (e.g., GLWH-09173011987)
        $voucherCode = $metadata['voucher_code'] ?? 'SETTLE';
        $rawMobile = $user->mobile ?? $user->account_number ?? '09000000000';
        
        // Format mobile for local dialing (e.g., 09173011987)
        $mobile = phone($rawMobile, 'PH')->formatNational();
        // Remove spaces from formatted number
        $mobile = str_replace(' ', '', $mobile);
        
        $reference = "{$voucherCode}-{$mobile}";
        
        Log::debug('[DisbursementService] Building DisburseInputData', [
            'metadata' => $metadata,
            'voucher_id_from_metadata' => $metadata['voucher_id'] ?? null,
            'reference' => $reference,
        ]);
        
        $disburseData = DisburseInputData::from([
            'reference' => $reference,
            'amount' => $amount,
            'account_number' => $bankAccount['account_number'],
            'bank' => $bankAccount['bank_code'],
            'via' => $rail,
            'mobile' => $mobile,
            'voucher_id' => $metadata['voucher_id'] ?? null,
            'voucher_code' => $metadata['voucher_code'] ?? null,
            'user_id' => $user->id,
        ]);
        
        Log::debug('[DisbursementService] DisburseInputData created', [
            'voucher_id' => $disburseData->voucher_id,
            'voucher_code' => $disburseData->voucher_code,
            'user_id' => $disburseData->user_id,
        ]);

        Log::info('[DisbursementService] Initiating disbursement', [
            'user_id' => $user->id,
            'amount' => $amount,
            'bank_code' => $bankAccount['bank_code'],
            'rail' => $rail,
            'metadata' => $metadata,
        ]);

        try {
            // For settlement disbursements: Withdraw from voucher cash wallet first
            // This ensures funds are deducted from the voucher's balance
            if (isset($metadata['voucher_id'])) {
                $voucher = \LBHurtado\Voucher\Models\Voucher::find($metadata['voucher_id']);
                if ($voucher && $voucher->cash && $voucher->cash->wallet) {
                    $cashWallet = $voucher->cash->wallet;
                    $currentBalance = $cashWallet->balanceFloat;
                    
                    Log::info('[DisbursementService] Withdrawing from voucher cash wallet', [
                        'voucher_code' => $voucher->code,
                        'current_balance' => $currentBalance,
                        'withdraw_amount' => $amount,
                    ]);
                    
                    // Withdraw from voucher's cash wallet
                    // Note: Bavix Wallet expects int (centavos) not float (pesos)
                    $amountInCentavos = (int) ($amount * 100);
                    $cashWallet->withdraw($amountInCentavos, [
                        'type' => 'disbursement',
                        'voucher_code' => $voucher->code,
                        'reference' => $reference,
                        'bank_code' => $bankAccount['bank_code'],
                        'account_number' => $bankAccount['account_number'],
                    ]);
                    
                    Log::info('[DisbursementService] Withdrawn from cash wallet', [
                        'new_balance' => $cashWallet->balanceFloat,
                    ]);
                }
            }
            
            $response = $this->gateway->disburse($user, $disburseData);

            // Gateway returns DisburseResponseData on success, false on failure
            if ($response && $response instanceof \LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData) {
                Log::info('[DisbursementService] Disbursement successful', [
                    'user_id' => $user->id,
                    'transaction_id' => $response->transaction_id,
                    'uuid' => $response->uuid,
                    'status' => $response->status,
                ]);

                return [
                    'success' => true,
                    'message' => 'Disbursement successful',
                    'transaction_id' => $response->transaction_id,
                    'reference_id' => $reference,
                    'error' => null,
                ];
            }

            // Gateway returned false
            Log::warning('[DisbursementService] Disbursement failed', [
                'user_id' => $user->id,
                'response' => $response,
            ]);

            return [
                'success' => false,
                'message' => 'Disbursement failed',
                'transaction_id' => null,
                'error' => 'gateway_error',
            ];

        } catch (\Exception $e) {
            Log::error('[DisbursementService] Disbursement exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Disbursement failed: ' . $e->getMessage(),
                'transaction_id' => null,
                'error' => 'exception',
            ];
        }
    }

    /**
     * Determine appropriate settlement rail based on amount and preference.
     * 
     * @param float $amount Amount in pesos
     * @param string|null $preferred Preferred rail or null for auto
     * @return string INSTAPAY or PESONET
     */
    public function determineSettlementRail(float $amount, ?string $preferred): string
    {
        // If user specified a rail, use it
        if ($preferred && in_array(strtoupper($preferred), ['INSTAPAY', 'PESONET'])) {
            return strtoupper($preferred);
        }

        // Auto-select based on amount
        // INSTAPAY: ≤ ₱50,000
        // PESONET: > ₱50,000
        return $amount <= 50000 ? 'INSTAPAY' : 'PESONET';
    }

    /**
     * Get disbursement fee for a given rail and amount.
     * 
     * @param string $rail INSTAPAY or PESONET
     * @param float $amount Amount in pesos
     * @return int Fee in pesos
     */
    public function getFee(string $rail, float $amount): int
    {
        $railEnum = SettlementRail::from(strtoupper($rail));
        $feeInCentavos = $this->gateway->getRailFee($railEnum);
        return (int) ($feeInCentavos / 100); // Convert to pesos
    }
}
