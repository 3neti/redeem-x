<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use App\Models\Campaign;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Actions\GenerateVouchers as BaseGenerateVouchers;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\DataCollection;

/**
 * Generate vouchers via API.
 *
 * Endpoint: POST /api/v1/vouchers
 */
class GenerateVouchers
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        // Get validated data (ActionRequest auto-validates with rules() method)
        $validated = $request->validated();

        // Check if user has sufficient balance
        $amount = $validated['amount'];
        $count = $validated['count'];
        $totalCost = $amount * $count;

        if ($request->user()->balanceFloatNum < $totalCost) {
            return ApiResponse::forbidden('Insufficient wallet balance to generate vouchers.');
        }

        // Convert request to instructions
        $instructions = $this->toInstructions($validated);

        // Generate vouchers using package action
        $vouchers = BaseGenerateVouchers::run($instructions);

        // Attach vouchers to campaign if campaign_id provided
        if (!empty($validated['campaign_id'])) {
            $campaign = Campaign::find($validated['campaign_id']);
            if ($campaign && $campaign->user_id === $request->user()->id) {
                $now = now();
                $pivotData = $vouchers->map(fn ($voucher) => [
                    'campaign_id' => $campaign->id,
                    'voucher_id' => $voucher->id,
                    'instructions_snapshot' => json_encode($campaign->instructions->toArray()),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();
                
                DB::table('campaign_voucher')->insert($pivotData);
            }
        }

        // Transform to VoucherData DTOs using DataCollection
        $voucherData = new DataCollection(VoucherData::class, $vouchers->all());

        // Calculate totals
        $totalAmount = $vouchers->sum(fn ($v) => $v->instructions->cash->amount ?? 0);

        return ApiResponse::created([
            'count' => $vouchers->count(),
            'vouchers' => $voucherData,
            'total_amount' => $totalAmount,
            'currency' => $instructions->cash->currency ?? 'PHP',
        ]);
    }


    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'count' => 'required|integer|min:1|max:1000',
            'prefix' => 'nullable|string|min:1|max:10',
            'mask' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (! preg_match("/^[\\*\\-]+$/", $value)) {
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
            'input_fields.*' => ['nullable', 'string', 'in:'.implode(',', VoucherInputField::values())],

            'validation_secret' => 'nullable|string',
            'validation_mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],

            'feedback_email' => 'nullable|email',
            'feedback_mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback_webhook' => 'nullable|url',

            'rider_message' => 'nullable|string|min:1',
            'rider_url' => 'nullable|url',

            'campaign_id' => 'nullable|integer|exists:campaigns,id',
        ];
    }

    /**
     * Convert validated data to VoucherInstructionsData.
     */
    protected function toInstructions(array $validated): VoucherInstructionsData
    {
        // Parse input_fields if it's JSON string
        $inputFields = $validated['input_fields'] ?? [];
        if (is_string($inputFields)) {
            $inputFields = json_decode($inputFields, true) ?? [];
        }

        // Convert ttl_days to CarbonInterval
        $ttl = null;
        if (! empty($validated['ttl_days'])) {
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
            ],
            'count' => $validated['count'],
            'prefix' => $validated['prefix'] ?? '',
            'mask' => $validated['mask'] ?? '',
            'ttl' => $ttl,
        ];

        return VoucherInstructionsData::from($data_array);
    }

    /**
     * Custom validation messages.
     */
    public function getValidationMessages(): array
    {
        return [
            'amount.required' => 'Voucher amount is required.',
            'amount.min' => 'Voucher amount must be at least 0.',
            'count.required' => 'Voucher count is required.',
            'count.min' => 'You must generate at least 1 voucher.',
            'count.max' => 'You cannot generate more than 1000 vouchers at once.',
        ];
    }

}
