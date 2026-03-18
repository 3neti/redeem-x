<?php

namespace App\Actions\Billing;

use App\Data\ChargeBreakdownData;
use App\Models\User;
use App\Services\InstructionCostEvaluator;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateCharge
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
            $sliceCount = $charge['slice_count'] ?? null;

            // Format price display based on config
            if ($sliceCount && $sliceCount > 0) {
                // Slice fee: show per-slice price × slice count (× voucher count if > 1)
                $perSliceFormatted = '₱'.number_format($unitPrice / 100, 2);
                $sliceTotal = $unitPrice * $sliceCount;
                $sliceTotalFormatted = '₱'.number_format($sliceTotal / 100, 2);
                if ($quantity > 1) {
                    $grandFormatted = '₱'.number_format($totalPrice / 100, 2);
                    $priceFormatted = "{$perSliceFormatted} × {$sliceCount} slice".($sliceCount > 1 ? 's' : '')." × {$quantity} = {$grandFormatted}";
                } else {
                    $priceFormatted = "{$perSliceFormatted} × {$sliceCount} slice".($sliceCount > 1 ? 's' : '')." = {$sliceTotalFormatted}";
                }
            } elseif ($showPerUnit && $quantity > 1) {
                $unitFormatted = '₱'.number_format($unitPrice / 100, 2);
                $totalFormatted = '₱'.number_format($totalPrice / 100, 2);
                $priceFormatted = "{$unitFormatted} × {$quantity} = {$totalFormatted}";
            } else {
                $priceFormatted = '₱'.number_format($totalPrice / 100, 2);
            }

            $item = [
                'index' => $charge['index'],
                'label' => $charge['label'],
                'category' => $charge['item']->category ?? 'other',
                'value' => $charge['value'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'price' => $totalPrice,
                'price_formatted' => $priceFormatted,
                'currency' => $charge['currency'],
            ];

            if ($sliceCount) {
                $item['slice_count'] = $sliceCount;
            }

            $breakdown[] = $item;
            $totalCentavos += $totalPrice;
        }

        return new ChargeBreakdownData(
            breakdown: $breakdown,
            total: $totalCentavos
        );
    }

    public function asController(Request $request)
    {
        $data = $request->all();

        // Ensure nested DTOs have defaults so VoucherInstructionsData::from() doesn't fail
        // when lightweight pricing payloads omit non-nullable nested objects.
        if (isset($data['cash']) && !isset($data['cash']['validation'])) {
            $data['cash']['validation'] = [
                'secret' => null, 'mobile' => null, 'payable' => null,
                'country' => null, 'location' => null, 'radius' => null,
            ];
        }
        $data['feedback'] ??= ['email' => null, 'mobile' => null, 'webhook' => null];
        $data['rider'] ??= ['message' => null, 'url' => null];

        $instructions = VoucherInstructionsData::from($data);

        return $this->handle($request->user(), $instructions);
    }
}
