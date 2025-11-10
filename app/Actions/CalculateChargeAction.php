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
        
        $breakdown = [];
        $totalCentavos = 0;
        
        foreach ($charges as $charge) {
            $breakdown[] = [
                'index' => $charge['item']->index,
                'label' => $charge['label'],
                'value' => $charge['value'],
                'price' => $charge['price'],
                'currency' => $charge['currency'],
            ];
            $totalCentavos += $charge['price'];
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
