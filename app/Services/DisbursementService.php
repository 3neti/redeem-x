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
        $disburseData = DisburseInputData::fromArray([
            'mobile' => $user->mobile ?? $user->account_number ?? '09000000000', // Fallback
            'amount' => $amount,
            'bank_code' => $bankAccount['bank_code'],
            'account_number' => $bankAccount['account_number'],
            'settlement_rail' => $rail,
        ]);

        Log::info('[DisbursementService] Initiating disbursement', [
            'user_id' => $user->id,
            'amount' => $amount,
            'bank_code' => $bankAccount['bank_code'],
            'rail' => $rail,
            'metadata' => $metadata,
        ]);

        try {
            $response = $this->gateway->disburse($user, $disburseData);

            if ($response && $response->success) {
                Log::info('[DisbursementService] Disbursement successful', [
                    'user_id' => $user->id,
                    'transaction_id' => $response->transactionId,
                    'reference_id' => $response->referenceId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Disbursement successful',
                    'transaction_id' => $response->transactionId,
                    'reference_id' => $response->referenceId ?? null,
                    'error' => null,
                ];
            }

            // Gateway returned false or failed response
            Log::warning('[DisbursementService] Disbursement failed', [
                'user_id' => $user->id,
                'response' => $response,
            ]);

            return [
                'success' => false,
                'message' => $response->message ?? 'Disbursement failed',
                'transaction_id' => null,
                'error' => $response->error ?? 'gateway_error',
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
    protected function determineSettlementRail(float $amount, ?string $preferred): string
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
