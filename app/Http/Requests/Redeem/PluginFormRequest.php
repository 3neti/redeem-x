<?php

declare(strict_types=1);

namespace App\Http\Requests\Redeem;

use App\Support\RedeemPluginMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use LBHurtado\ModelInput\Support\InputRuleBuilder;
use LBHurtado\Voucher\Enums\VoucherInputField;

/**
 * Dynamic form request for plugin-specific inputs during redemption.
 *
 * This request handles validation for any plugin (inputs, signature, etc.)
 * by dynamically building rules based on:
 * 1. The voucher's required fields (from instructions)
 * 2. The plugin's capable fields (from config)
 * 3. The intersection of the two (what THIS plugin should collect)
 *
 * Example:
 * - Voucher requires: [NAME, EMAIL, SIGNATURE]
 * - Plugin 'inputs' handles: [NAME, EMAIL, ADDRESS, ...]
 * - Plugin 'signature' handles: [SIGNATURE]
 * - This request validates: [NAME, EMAIL] for 'inputs', [SIGNATURE] for 'signature'
 */
class PluginFormRequest extends FormRequest
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
     * Rules are dynamically built based on:
     * - Voucher's required input fields
     * - Plugin's capable fields
     * - Intersection of the two
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \LBHurtado\Voucher\Models\Voucher $voucher */
        $voucher = $this->route('voucher');

        /** @var string $plugin */
        $plugin = $this->route('plugin');

        // Step 1: Get fields associated with this plugin
        $pluginFields = RedeemPluginMap::fieldsFor($plugin);
        $pluginFieldKeys = array_map(
            fn (VoucherInputField $field) => $field->value,
            $pluginFields
        );

        // Step 2: Get fields required by the voucher
        $voucherFieldKeys = array_map(
            fn (VoucherInputField $field) => $field->value,
            $voucher->instructions->inputs->fields
        );

        // Step 3: Get intersection (what THIS plugin should validate)
        $requestedFieldKeys = array_intersect($pluginFieldKeys, $voucherFieldKeys);

        // Step 4: Build full rules from voucher instructions
        $allRules = InputRuleBuilder::from($voucher->instructions->inputs);

        // Step 5: Filter to only this plugin's fields
        return Arr::only($allRules, $requestedFieldKeys);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'address.required' => 'Please enter your complete address.',
            'birth_date.required' => 'Please enter your birth date.',
            'birth_date.date' => 'Please enter a valid date.',
            'gross_monthly_income.required' => 'Please enter your gross monthly income.',
            'gross_monthly_income.numeric' => 'Income must be a number.',
            'location.required' => 'Please provide your location.',
            'reference_code.required' => 'Please enter the reference code.',
            'otp.required' => 'Please enter the OTP.',
            'otp.digits' => 'OTP must be 6 digits.',
            'signature.required' => 'Please provide your signature.',
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
            'name' => 'full name',
            'email' => 'email address',
            'address' => 'address',
            'birth_date' => 'birth date',
            'gross_monthly_income' => 'monthly income',
            'location' => 'location',
            'reference_code' => 'reference code',
            'otp' => 'OTP',
            'signature' => 'signature',
        ];
    }

    /**
     * Configure the validator instance with additional rules.
     *
     * Add custom validation for OTP if present.
     *
     * @param  Validator  $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $otp = $this->input('otp');

            if (! is_null($otp) && $otp !== '') {
                /** @var \LBHurtado\Voucher\Models\Voucher $voucher */
                $voucher = $this->route('voucher');
                $voucherCode = $voucher->code;

                // Get TOTP URI from cache
                $uri = cache()->get("otp.uri.{$voucherCode}");

                if (! $uri) {
                    $v->errors()->add('otp', 'OTP verification not available. Please try again.');

                    return;
                }

                try {
                    $verifier = \OTPHP\Factory::loadFromProvisioningUri($uri);
                    $isValid = $verifier->verify($otp);

                    if (! $isValid) {
                        $v->errors()->add('otp', 'Invalid OTP. Please check and try again.');
                    }
                } catch (\Throwable $e) {
                    $v->errors()->add('otp', 'Error verifying OTP. Please try again.');
                    \Log::error('[PluginFormRequest] OTP verification error', [
                        'error' => $e->getMessage(),
                        'voucher' => $voucherCode,
                    ]);
                }
            }
        });
    }
}
