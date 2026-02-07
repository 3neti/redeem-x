<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,active,archived',
            'instructions' => 'required|array',
            'envelope_config' => 'nullable|array',
            'envelope_config.enabled' => 'boolean',
            'envelope_config.driver_id' => 'required_if:envelope_config.enabled,true|nullable|string',
            'envelope_config.driver_version' => 'required_if:envelope_config.enabled,true|nullable|string',
            'envelope_config.initial_payload' => 'nullable|array',
        ], $this->prefixRules(VoucherInstructionsData::rules(), 'instructions'));
    }

    /**
     * Prefix validation rules for nested data.
     */
    protected function prefixRules(array $rules, string $prefix): array
    {
        $prefixed = [];
        foreach ($rules as $key => $rule) {
            $prefixed["{$prefix}.{$key}"] = $rule;
        }
        return $prefixed;
    }
}
