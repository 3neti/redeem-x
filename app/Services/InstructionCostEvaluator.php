<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\InstructionItemRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class InstructionCostEvaluator
{
    protected array $excludedFields = [
        'count',
        'mask',
        'ttl',
        'starts_at',
        'expires_at',
    ];

    public function __construct(
        protected InstructionItemRepository $repository
    ) {}

    public function evaluate(User $customer, VoucherInstructionsData $source): Collection
    {
        $charges = collect();
        $items = $this->repository->all();

        Log::debug('[InstructionCostEvaluator] Starting evaluation', [
            'user_id' => $customer->id,
            'instruction_items_count' => $items->count(),
        ]);

        foreach ($items as $item) {
            if (in_array($item->index, $this->excludedFields)) {
                continue;
            }

            $value = data_get($source, $item->index);
            
            $isTruthyString = is_string($value) && trim($value) !== '';
            $isTruthyBoolean = is_bool($value) && $value === true;
            $isTruthyFloat = is_float($value) && $value > 0.0;
            $shouldCharge = ($isTruthyString || $isTruthyBoolean || $isTruthyFloat) && $item->price > 0;

            $price = $item->getAmountProduct($customer);

            Log::debug("[InstructionCostEvaluator] Evaluating: {$item->index}", [
                'value' => $value,
                'type' => gettype($value),
                'price' => $price,
                'should_charge' => $shouldCharge,
            ]);

            if ($shouldCharge) {
                $label = $item->meta['label'] ?? $item->name;

                Log::info('[InstructionCostEvaluator] ✅ Chargeable instruction', [
                    'index' => $item->index,
                    'label' => $label,
                    'price' => $price,
                ]);

                $charges->push([
                    'item' => $item,
                    'value' => $value,
                    'price' => $price,
                    'currency' => $item->currency,
                    'label' => $label,
                ]);
            }
        }

        Log::info('[InstructionCostEvaluator] Evaluation complete', [
            'total_items_charged' => $charges->count(),
            'total_amount' => $charges->sum('price'),
        ]);

        return $charges;
    }
}
