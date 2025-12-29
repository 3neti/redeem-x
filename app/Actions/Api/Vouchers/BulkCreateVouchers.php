<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use App\Models\Campaign;
use App\Models\CampaignVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\DataCollection;
use Dedoc\Scramble\Attributes\Group;

/**
 * @group Vouchers
 *
 * Bulk create vouchers with external metadata via API.
 *
 * Endpoint: POST /api/v1/vouchers/bulk-create
 */
#[Group('Vouchers')]
class BulkCreateVouchers
{
    use AsAction;

    /**
     * Bulk create vouchers from campaign
     * 
     * Generate multiple vouchers at once using a campaign template with external metadata.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $campaign = Campaign::find($validated['campaign_id']);

        // Check if user owns the campaign
        if (!$campaign || $campaign->user_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to use this campaign.');
        }

        // Calculate total cost
        $amount = $campaign->instructions->cash->amount ?? 0;
        $count = count($validated['vouchers']);
        $totalCost = $amount * $count;

        // Check wallet balance
        if ($request->user()->balanceFloatNum < $totalCost) {
            return ApiResponse::forbidden('Insufficient wallet balance to generate vouchers.');
        }

        $generatedVouchers = [];
        $errors = [];

        DB::transaction(function () use ($campaign, $validated, $request, &$generatedVouchers, &$errors) {
            foreach ($validated['vouchers'] as $index => $voucherData) {
                try {
                    // Clone campaign instructions and override mobile/count if provided
                    $instructionsArray = $campaign->instructions->toArray();
                    
                    if (!empty($voucherData['mobile'])) {
                        $instructionsArray['cash']['validation']['mobile'] = $voucherData['mobile'];
                    }
                    
                    // Set count to 1 for single voucher generation
                    $instructionsArray['count'] = 1;
                    
                    $instructions = VoucherInstructionsData::from($instructionsArray);

                    // Generate single voucher
                    $vouchers = GenerateVouchers::run($instructions);
                    $voucher = $vouchers->first();

                    if (!$voucher) {
                        throw new \Exception('Failed to generate voucher');
                    }

                    // Set external metadata if provided
                    if (!empty($voucherData['external_metadata'])) {
                        $externalMetadata = ExternalMetadataData::from($voucherData['external_metadata']);
                        $voucher->external_metadata = $externalMetadata;
                        $voucher->save();
                    }

                    // Attach to campaign
                    CampaignVoucher::create([
                        'campaign_id' => $campaign->id,
                        'voucher_id' => $voucher->id,
                        'instructions_snapshot' => $campaign->instructions->toArray(),
                    ]);

                    $generatedVouchers[] = $voucher;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'mobile' => $voucherData['mobile'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        // Transform to VoucherData DTOs
        $voucherData = new DataCollection(VoucherData::class, $generatedVouchers);

        $response = [
            'count' => count($generatedVouchers),
            'vouchers' => $voucherData,
            'total_amount' => count($generatedVouchers) * $amount,
            'currency' => $campaign->instructions->cash->currency ?? 'PHP',
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return ApiResponse::created($response);
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'vouchers' => 'required|array|min:1|max:100',
            'vouchers.*.mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'vouchers.*.external_metadata' => 'nullable|array',
            'vouchers.*.external_metadata.external_id' => 'nullable|string|max:255',
            'vouchers.*.external_metadata.external_type' => 'nullable|string|max:255',
            'vouchers.*.external_metadata.reference_id' => 'nullable|string|max:255',
            'vouchers.*.external_metadata.user_id' => 'nullable|string|max:255',
            'vouchers.*.external_metadata.custom' => 'nullable|array',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function getValidationMessages(): array
    {
        return [
            'campaign_id.required' => 'Campaign ID is required.',
            'campaign_id.exists' => 'The specified campaign does not exist.',
            'vouchers.required' => 'Vouchers array is required.',
            'vouchers.min' => 'You must create at least 1 voucher.',
            'vouchers.max' => 'You cannot create more than 100 vouchers at once.',
            'vouchers.*.mobile.phone' => 'Invalid Philippine mobile number.',
        ];
    }
}
