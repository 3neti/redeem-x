<?php

declare(strict_types=1);

namespace App\Services;

use Bavix\Wallet\Interfaces\Customer;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

/**
 * Evaluates the cost of generating vouchers based on their instructions.
 * 
 * This service calculates what the voucher creator should be charged for
 * creating vouchers with specific configurations (e.g., higher amounts,
 * more features, longer expiry, etc.)
 */
class InstructionCostEvaluator
{
    /**
     * Evaluate the charges for creating a voucher with given instructions.
     *
     * @param Customer $owner The user creating the voucher
     * @param VoucherInstructionsData $instructions The voucher configuration
     * @return array<array{item: Cash, description: string, amount: float}> Array of charges
     */
    public function evaluate(Customer $owner, VoucherInstructionsData $instructions): array
    {
        Log::debug('[InstructionCostEvaluator] Evaluating charges', [
            'owner_id' => $owner->getKey(),
            'amount' => $instructions->cash->amount,
            'currency' => $instructions->cash->currency,
        ]);

        $charges = [];

        // Base charge: The voucher amount itself (escrowed from creator's wallet)
        $baseCharge = $this->createBaseCharge($instructions);
        if ($baseCharge) {
            $charges[] = $baseCharge;
        }

        // Optional: Service fee for advanced features
        $serviceFee = $this->calculateServiceFee($instructions);
        if ($serviceFee) {
            $charges[] = $serviceFee;
        }

        Log::info('[InstructionCostEvaluator] Charges calculated', [
            'owner_id' => $owner->getKey(),
            'total_charges' => count($charges),
            'total_amount' => array_sum(array_column($charges, 'amount')),
        ]);

        return $charges;
    }

    /**
     * Create the base charge (voucher amount to escrow).
     */
    protected function createBaseCharge(VoucherInstructionsData $instructions): ?array
    {
        $amount = $instructions->cash->amount;
        $currency = $instructions->cash->currency;

        if ($amount <= 0) {
            return null;
        }

        $cash = Cash::create([
            'amount' => $amount,
            'currency' => $currency,
            'meta' => [
                'type' => 'voucher_base_charge',
                'description' => 'Voucher face value escrow',
            ],
        ]);

        return [
            'item' => $cash,
            'description' => "Voucher face value ({$currency} {$amount})",
            'amount' => $amount,
        ];
    }

    /**
     * Calculate service fee based on voucher features.
     * 
     * You can customize this to charge extra for:
     * - Long expiry periods
     * - High-value vouchers
     * - Advanced features (feedback channels, KYC, etc.)
     */
    protected function calculateServiceFee(VoucherInstructionsData $instructions): ?array
    {
        // For now, no service fee
        // You can enable this in production based on business rules
        
        $serviceFeeAmount = 0.0;

        // Example: Charge 1% service fee for vouchers over 10,000
        if ($instructions->cash->amount > 10000) {
            $serviceFeeAmount = $instructions->cash->amount * 0.01;
        }

        // Example: Charge extra for long expiry (over 90 days)
        $ttlDays = $instructions->generation->ttl_days ?? 0;
        if ($ttlDays > 90) {
            $serviceFeeAmount += 10; // Flat 10 PHP fee
        }

        // Example: Charge for premium features
        $hasFeedback = !empty($instructions->feedback->channels ?? []);
        $hasRider = !empty($instructions->rider->message ?? null);
        if ($hasFeedback || $hasRider) {
            $serviceFeeAmount += 5; // Flat 5 PHP fee
        }

        if ($serviceFeeAmount <= 0) {
            return null;
        }

        $cash = Cash::create([
            'amount' => $serviceFeeAmount,
            'currency' => $instructions->cash->currency,
            'meta' => [
                'type' => 'service_fee',
                'description' => 'Voucher generation service fee',
            ],
        ]);

        return [
            'item' => $cash,
            'description' => 'Service fee',
            'amount' => $serviceFeeAmount,
        ];
    }

    /**
     * Get pricing rules configuration.
     * 
     * This can be moved to config file for easier management.
     */
    public function getPricingRules(): array
    {
        return [
            'high_value_threshold' => 10000,
            'high_value_fee_percent' => 1.0,
            'long_expiry_threshold_days' => 90,
            'long_expiry_fee' => 10,
            'premium_features_fee' => 5,
        ];
    }
}
