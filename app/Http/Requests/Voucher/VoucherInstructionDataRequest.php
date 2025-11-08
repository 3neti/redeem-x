<?php

declare(strict_types=1);

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Spatie\LaravelData\WithData;

/**
 * Form request for voucher generation.
 *
 * Uses Spatie Laravel Data to automatically hydrate VoucherInstructionsData DTO.
 * Provides default values from cache or config for a better UX.
 */
class VoucherInstructionDataRequest extends FormRequest
{
    use WithData;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated users can generate vouchers.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules are defined in the VoucherInstructionsData DTO.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return VoucherInstructionsData::rules();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash.amount.required' => 'Please specify the voucher amount.',
            'cash.amount.numeric' => 'The amount must be a valid number.',
            'cash.amount.min' => 'Minimum amount is :min.',
            'cash.currency.required' => 'Currency is required.',
            'inputs.fields.required' => 'Please specify which inputs to collect from redeemers.',
            'inputs.fields.array' => 'Input fields must be an array.',
            'count.required' => 'Please specify how many vouchers to generate.',
            'count.integer' => 'Count must be an integer.',
            'count.min' => 'You must generate at least 1 voucher.',
            'prefix.string' => 'Prefix must be a string.',
            'mask.string' => 'Mask must be a string.',
            'mask.regex' => 'Mask must contain asterisks (*).',
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
            'cash.amount' => 'amount',
            'cash.currency' => 'currency',
            'inputs.fields' => 'input fields',
            'feedback.email' => 'feedback email',
            'feedback.mobile' => 'feedback mobile',
            'feedback.webhook' => 'feedback webhook',
            'rider.message' => 'message',
            'rider.url' => 'redirect URL',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Merge with cached/default values for better UX.
     */
    protected function prepareForValidation(): void
    {
        $userId = auth()->id();

        // Try to fetch last used instructions from cache (7 days)
        $cached = Cache::get("voucher.last_instructions.user:{$userId}");

        if ($cached && is_array($cached)) {
            // Merge with request data (request data takes precedence)
            $merged = array_merge($cached, $this->all());
            $this->merge($merged);
        }
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        $userId = auth()->id();

        // Cache the validated instructions for next time (7 days)
        Cache::put(
            "voucher.last_instructions.user:{$userId}",
            $this->validated(),
            now()->addDays(7)
        );
    }

    /**
     * Get the DTO class for Spatie Laravel Data.
     */
    protected function dataClass(): string
    {
        return VoucherInstructionsData::class;
    }
}
