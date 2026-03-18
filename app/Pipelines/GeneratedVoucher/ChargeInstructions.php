<?php

namespace App\Pipelines\GeneratedVoucher;

use App\Services\InstructionCostEvaluator;
use Closure;

class ChargeInstructions
{
    public function __construct(
        protected InstructionCostEvaluator $evaluator
    ) {}

    public function handle($voucher, Closure $next)
    {
        $owner = $voucher->owner;
        if (! $owner || ! $owner->wallet) {
            return $next($voucher);
        }

        $charges = $this->evaluator->evaluate($voucher->owner, $voucher->instructions);

        foreach ($charges as $charge) {
            // Skip charges with no item (e.g., cash amount which is handled separately)
            if ($charge['item'] === null) {
                continue;
            }

            // pay_count > 1 for slice fees: call pay() once per additional slice
            $payCount = $charge['pay_count'] ?? 1;
            for ($i = 0; $i < $payCount; $i++) {
                $owner->pay($charge['item']);
            }
        }

        return $next($voucher);
    }
}
