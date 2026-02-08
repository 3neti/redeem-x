<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\User;
use Brick\Money\Money;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

/**
 * Centralized gate for voucher generation that enforces balance validation.
 *
 * All voucher generation entry points (SMS, API, Web, Console) MUST use this
 * gate to validate the user has sufficient funds before proceeding.
 *
 * The gate calculates total cost including:
 * - Face value (cash amount × count)
 * - Instruction fees (input fields, validations, etc.)
 */
class VoucherGenerationGate
{
    public function __construct(
        protected InstructionCostEvaluator $costEvaluator
    ) {}

    /**
     * Validate that user has sufficient balance for voucher generation.
     *
     * @throws InsufficientFundsException if balance is insufficient
     */
    public function validate(User $user, VoucherInstructionsData $instructions): void
    {
        // Check if gate is disabled via configuration
        if (! config('redeem.voucher_generation_gate_enabled', false)) {
            return;
        }

        $costBreakdown = $this->calculateTotalCost($user, $instructions);
        $totalCost = $costBreakdown['total'];
        $available = $user->balanceFloatNum;

        if ($available < $totalCost) {
            throw new InsufficientFundsException(
                required: $totalCost,
                available: $available,
                breakdown: $costBreakdown
            );
        }
    }

    /**
     * Calculate the total cost for voucher generation including all fees.
     *
     * @return array{total: float, face_value: float, fees: float, count: int, breakdown: array}
     */
    public function calculateTotalCost(User $user, VoucherInstructionsData $instructions): array
    {
        $count = $instructions->count ?? 1;
        $faceValuePerVoucher = $instructions->cash->amount ?? 0;
        $totalFaceValue = $faceValuePerVoucher * $count;

        // Calculate instruction fees using the cost evaluator (returns prices in centavos)
        $charges = $this->costEvaluator->evaluate($user, $instructions);
        $totalFeesInCentavos = $charges->sum('price');

        // Convert centavos to float using brick/money
        $totalFeesFloat = Money::ofMinor($totalFeesInCentavos, 'PHP')->getAmount()->toFloat();

        // Build detailed breakdown (convert all centavos to float)
        $breakdown = [
            'face_value' => [
                'per_voucher' => $faceValuePerVoucher,
                'count' => $count,
                'total' => $totalFaceValue,
            ],
            'fees' => $charges->map(fn ($charge) => [
                'label' => $charge['label'],
                'unit_price' => Money::ofMinor($charge['unit_price'], 'PHP')->getAmount()->toFloat(),
                'quantity' => $charge['quantity'],
                'total' => Money::ofMinor($charge['price'], 'PHP')->getAmount()->toFloat(),
            ])->values()->toArray(),
        ];

        return [
            'total' => $totalFaceValue + $totalFeesFloat,
            'face_value' => $totalFaceValue,
            'fees' => $totalFeesFloat,
            'count' => $count,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Check if user can afford voucher generation without throwing.
     */
    public function canAfford(User $user, VoucherInstructionsData $instructions): bool
    {
        $costBreakdown = $this->calculateTotalCost($user, $instructions);

        return $user->balanceFloatNum >= $costBreakdown['total'];
    }
}
