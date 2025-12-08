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
        $count = $source->count ?? 1;
        $cashAmount = $source->cash?->amount ?? 0;

        Log::debug('[InstructionCostEvaluator] Starting evaluation', [
            'user_id' => $customer->id,
            'instruction_items_count' => $items->count(),
            'count' => $count,
            'cash_amount' => $cashAmount,
            'source_data' => $source->toArray(),
        ]);

        // Cash face value is NOT a charge.
        // We only charge configured InstructionItems (e.g., 'cash.amount' transaction fee).
        // The actual face value (cash.amount) is transferred to the redeemer and should not
        // be added to the cost breakdown here.

        foreach ($items as $item) {
            if (in_array($item->index, $this->excludedFields)) {
                continue;
            }

            // Handle inputs.fields specially - it's an array of enum objects or strings
            if (str_starts_with($item->index, 'inputs.fields.')) {
                $fieldName = str_replace('inputs.fields.', '', $item->index);
                $selectedFieldsRaw = data_get($source, 'inputs.fields', []);
                
                // Extract string values from enum objects
                $selectedFields = collect($selectedFieldsRaw)->map(function ($field) {
                    // If it's an enum object like {VoucherInputField: "email"}, extract the value
                    if (is_array($field) || is_object($field)) {
                        $values = collect((array) $field)->values();
                        return $values->first(); // Get the first (and only) value
                    }
                    return $field;
                })->filter()->toArray();
                
                // Case-insensitive comparison (enum values are uppercase)
                $isSelected = in_array(strtoupper($fieldName), array_map('strtoupper', $selectedFields));
                
                Log::debug("[InstructionCostEvaluator] Checking input field: {$fieldName}", [
                    'selectedFieldsRaw' => $selectedFieldsRaw,
                    'selectedFieldsExtracted' => $selectedFields,
                    'isSelected' => $isSelected,
                ]);
                $value = $isSelected ? $fieldName : null;
            } else {
                $value = data_get($source, $item->index);
            }
            
            // Special handling for validation items
            if (str_starts_with($item->index, 'validation.')) {
                // Value might be a Data object or array
                if (is_object($value) && method_exists($value, 'toArray')) {
                    $valueArray = $value->toArray();
                } elseif (is_array($value)) {
                    $valueArray = $value;
                } else {
                    $valueArray = [];
                }
                
                // Different validation types have different "enabled" criteria:
                // - Location: has 'required' field
                // - Time: enabled if window or limit_minutes is set
                if (isset($valueArray['required'])) {
                    // LocationValidationData
                    $isEnabled = $valueArray['required'] === true;
                } elseif (isset($valueArray['window']) || isset($valueArray['limit_minutes'])) {
                    // TimeValidationData - enabled if window or limit is configured
                    $isEnabled = !empty($valueArray['window']) || !empty($valueArray['limit_minutes']);
                } else {
                    $isEnabled = false;
                }
                
                $shouldCharge = $isEnabled && $item->price > 0;
                
                Log::debug("[InstructionCostEvaluator] Validation item: {$item->index}", [
                    'value' => $value,
                    'valueArray' => $valueArray,
                    'isEnabled' => $isEnabled,
                    'shouldCharge' => $shouldCharge,
                ]);
            } else {
                $isTruthyString = is_string($value) && trim($value) !== '';
                $isTruthyBoolean = is_bool($value) && $value === true;
                $isTruthyFloat = is_float($value) && $value > 0.0;
                $isTruthyObject = (is_array($value) || is_object($value)) && !empty((array) $value);
                $shouldCharge = ($isTruthyString || $isTruthyBoolean || $isTruthyFloat || $isTruthyObject) && $item->price > 0;
            }

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
                    'unit_price' => $price,
                    'quantity' => $count,
                    'total_price' => $price * $count,
                ]);

                $charges->push([
                    'index' => $item->index,
                    'item' => $item,
                    'value' => $value,
                    'unit_price' => $price,
                    'quantity' => $count,
                    'price' => $price * $count,
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
