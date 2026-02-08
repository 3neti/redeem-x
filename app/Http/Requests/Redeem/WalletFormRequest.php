<?php

declare(strict_types=1);

namespace App\Http\Requests\Redeem;

use App\Validators\VoucherRedemptionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Form request for wallet/bank account collection during redemption.
 *
 * Validates:
 * - Mobile number (Philippines, mobile type only)
 * - Bank code and account number (optional, for InstaPay/PESONet)
 * - Secret (optional, if voucher has secret protection)
 * - Country code
 *
 * Cross-validates:
 * - Mobile number against voucher's expected recipient (via VoucherRedemptionValidator)
 * - Secret against voucher's hashed secret (via VoucherRedemptionValidator)
 */
class WalletFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Redemption is public (no authentication required).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mobile' => [
                'required',
                (new Phone)->country('PH')->type('mobile'),
            ],
            'country' => [
                'required',
                'string',
                'size:2', // ISO 3166-1 alpha-2
            ],
            'bank_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'secret' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.required' => 'Please enter your mobile number.',
            'country.required' => 'Country is required.',
            'country.size' => 'Country must be a 2-letter code (e.g., PH).',
            'bank_code.max' => 'Bank code is too long.',
            'account_number.max' => 'Account number is too long.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mobile' => 'mobile number',
            'bank_code' => 'bank',
            'account_number' => 'account number',
        ];
    }

    /**
     * Configure the validator instance with additional rules.
     *
     * Adds custom voucher-specific validations:
     * - Mobile number must match voucher's expected recipient (if set)
     * - Secret must match voucher's hashed secret (if set)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var \LBHurtado\Voucher\Models\Voucher $voucher */
            $voucher = $this->route('voucher');

            $redemptionValidator = new VoucherRedemptionValidator($voucher, $v->errors());

            // Validate voucher status (not expired, not redeemed, etc.)
            $redemptionValidator->validateVoucherStatus();

            // Validate mobile against voucher's expected recipient
            $redemptionValidator->validateMobile($this->input('mobile'));

            // Validate secret against voucher's hashed secret
            $redemptionValidator->validateSecret($this->input('secret'));
        });
    }

    /**
     * Prepare the data for validation.
     *
     * Normalize country code to uppercase.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('country')) {
            $this->merge([
                'country' => strtoupper($this->input('country')),
            ]);
        }
    }
}
