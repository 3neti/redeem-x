<?php

namespace App\Actions;

use App\Data\ChargeBreakdownData;
use App\Models\User;
use App\Services\InstructionCostEvaluator;
use Brick\Money\Money;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateChargeAction
{
    use AsAction;

    public function __construct(
        protected InstructionCostEvaluator $evaluator
    ) {}

    public function handle(User $user, VoucherInstructionsData $instructions): ChargeBreakdownData
    {
        $charges = $this->evaluator->evaluate($user, $instructions);
        $showPerUnit = config('redeem.cost_breakdown.show_per_unit_prices', true);
        
        $breakdown = [];
        $totalCentavos = 0;
        
        foreach ($charges as $charge) {
            $unitPrice = $charge['unit_price'];
            $quantity = $charge['quantity'];
            $totalPrice = $charge['price'];
            
            // Format price display based on config
            if ($showPerUnit && $quantity > 1) {
                $unitFormatted = '₱' . number_format($unitPrice / 100, 2);
                $totalFormatted = '₱' . number_format($totalPrice / 100, 2);
                $priceFormatted = "{$unitFormatted} × {$quantity} = {$totalFormatted}";
            } else {
                $priceFormatted = '₱' . number_format($totalPrice / 100, 2);
            }
            
            $breakdown[] = [
                'index' => $charge['index'],
                'label' => $charge['label'],
                'value' => $charge['value'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'price' => $totalPrice,
                'price_formatted' => $priceFormatted,
                'currency' => $charge['currency'],
            ];
            $totalCentavos += $totalPrice;
        }

        return new ChargeBreakdownData(
            breakdown: $breakdown,
            total: $totalCentavos
        );
    }

    public function asController(Request $request)
    {
        $instructions = VoucherInstructionsData::from($request->all());
        return $this->handle($request->user(), $instructions);
    }
}
