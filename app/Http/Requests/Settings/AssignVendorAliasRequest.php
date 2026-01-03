<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\Merchant\Rules\ValidVendorAlias;

class AssignVendorAliasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $minLength = config('merchant.alias.min_length', 3);
        $maxLength = config('merchant.alias.max_length', 8);
        
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'alias' => [
                'required',
                'string',
                'uppercase',
                "min:{$minLength}",
                "max:{$maxLength}",
                new ValidVendorAlias(),
                'unique:vendor_aliases,alias',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user.',
            'user_id.exists' => 'The selected user does not exist.',
            'alias.required' => 'Please enter a vendor alias.',
            'alias.uppercase' => 'The alias must be uppercase.',
            'alias.unique' => 'This alias is already assigned to another user.',
        ];
    }
}
