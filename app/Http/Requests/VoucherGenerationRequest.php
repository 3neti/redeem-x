<?php

namespace App\Http\Requests;

use Carbon\CarbonInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Propaganistas\LaravelPhone\Rules\Phone;

class VoucherGenerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'count' => 'required|integer|min:1',
            'prefix' => 'nullable|string|min:1|max:10',
            'mask' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match("/^[\*\-]+$/", $value)) {
                        $fail('The :attribute may only contain asterisks (*) and hyphens (-).');
                    }

                    $asterisks = substr_count($value, '*');

                    if ($asterisks < 4) {
                        $fail('The :attribute must contain at least 4 asterisks (*).');
                    }

                    if ($asterisks > 6) {
                        $fail('The :attribute must contain at most 6 asterisks (*).');
                    }
                },
            ],
            'ttl_days' => 'nullable|integer|min:1',
            
            'input_fields' => 'nullable|array',
            'input_fields.*' => ['nullable', 'string', 'in:' . implode(',', VoucherInputField::values())],
            
            'validation_secret' => 'nullable|string',
            'validation_mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            
            'feedback_email' => 'nullable|email',
            'feedback_mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback_webhook' => 'nullable|url',
            
            'rider_message' => 'nullable|string|min:1',
            'rider_url' => 'nullable|url',
            'rider_redirect_timeout' => 'nullable|integer|min:0|max:300',
            
            // Settlement rail and fee strategy
            'settlement_rail' => 'nullable|string|in:INSTAPAY,PESONET',
            'fee_strategy' => 'nullable|string|in:absorb,include,add',
            
            // External metadata for external system integration
            'external_metadata' => 'nullable|array|max:5',
            'external_metadata.external_id' => 'nullable|string|max:255',
            'external_metadata.external_type' => 'nullable|string|max:100',
            'external_metadata.reference_id' => 'nullable|string|max:255',
            'external_metadata.user_id' => 'nullable|string|max:255',
            'external_metadata.custom' => 'nullable|array',
        ];
    }

    /**
     * Convert form data to VoucherInstructionsData.
     */
    public function toInstructions(): VoucherInstructionsData
    {
        $validated = $this->validated();
        
        // Parse input_fields if it's JSON string
        $inputFields = $validated['input_fields'] ?? [];
        if (is_string($inputFields)) {
            $inputFields = json_decode($inputFields, true) ?? [];
        }

        // Convert ttl_days to CarbonInterval
        $ttl = null;
        if (!empty($validated['ttl_days'])) {
            $ttl = CarbonInterval::days($validated['ttl_days']);
        }

        $data_array = [
            'cash' => [
                'amount' => $validated['amount'],
                'currency' => Number::defaultCurrency(),
                'validation' => [
                    'secret' => $validated['validation_secret'] ?? null,
                    'mobile' => $validated['validation_mobile'] ?? null,
                    'country' => config('instructions.cash.validation_rules.country', 'PH'),
                    'location' => null,
                    'radius' => null,
                ],
                'settlement_rail' => $validated['settlement_rail'] ?? null,
                'fee_strategy' => $validated['fee_strategy'] ?? 'absorb',
            ],
            'inputs' => [
                'fields' => $inputFields,
            ],
            'feedback' => [
                'email' => $validated['feedback_email'] ?? null,
                'mobile' => $validated['feedback_mobile'] ?? null,
                'webhook' => $validated['feedback_webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['rider_message'] ?? null,
                'url' => $validated['rider_url'] ?? null,
                'redirect_timeout' => $validated['rider_redirect_timeout'] ?? null,
            ],
            'count' => $validated['count'],
            'prefix' => $validated['prefix'] ?? '',
            'mask' => $validated['mask'] ?? '',
            'ttl' => $ttl,
        ];

        return VoucherInstructionsData::from($data_array);
    }
}
