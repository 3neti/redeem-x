<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Dedoc\Scramble\Attributes\Group;

/**
 * @group Vouchers
 *
 * Show voucher details via API.
 *
 * Endpoint: GET /api/v1/vouchers/{voucher}
 */
#[Group('Vouchers')]
class ShowVoucher
{
    use AsAction;

    /**
     * Get voucher details
     * 
     * Retrieve complete voucher information including redemption status, inputs, and metadata.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Check if user owns this voucher
        if ($voucher->owner_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to view this voucher.');
        }

        // Load relationships
        $voucher->load(['redeemers', 'owner', 'inputs']);

        // Transform to VoucherData DTO
        $voucherData = VoucherData::fromModel($voucher);

        // Add additional details
        $response = [
            'voucher' => $voucherData,
            'redemption_count' => $voucher->redeemers()->count(),
            'external_metadata' => $voucher->external_metadata,
            'timing' => $voucher->timing,
            'validation_results' => $voucher->getValidationResults(),
        ];

        // Include collected inputs if voucher has any
        if ($voucher->inputs->isNotEmpty()) {
            $response['inputs'] = $this->formatInputs($voucher);
        }

        // If voucher is redeemed, include redeemer details
        if ($voucher->isRedeemed() && $voucher->contact) {
            $contactData = ContactData::fromModel($voucher->contact)->toArray();
            $contactData['redeemed_at'] = $voucher->redeemed_at?->toIso8601String();
            $response['redeemed_by'] = $contactData;
        }

        return ApiResponse::success($response);
    }

    /**
     * Format inputs for API response.
     */
    private function formatInputs(Voucher $voucher): array
    {
        $formatted = [];

        foreach ($voucher->inputs as $input) {
            $name = $input->name;
            $value = $input->value;

            // Handle location field - parse JSON
            if ($name === 'location') {
                try {
                    $locationData = json_decode($value, true);
                    $formatted['location'] = [
                        'latitude' => $locationData['latitude'] ?? null,
                        'longitude' => $locationData['longitude'] ?? null,
                        'accuracy' => $locationData['accuracy'] ?? null,
                        'altitude' => $locationData['altitude'] ?? null,
                        'formatted_address' => $locationData['address']['formatted'] ?? null,
                        'has_snapshot' => isset($locationData['snapshot']),
                    ];
                } catch (\Exception $e) {
                    $formatted['location'] = $value;
                }
            }
            // Handle signature/selfie - indicate presence but don't send full data URL
            elseif (in_array($name, ['signature', 'selfie'])) {
                $formatted[$name] = [
                    'present' => !empty($value),
                    'size_bytes' => strlen($value),
                    'format' => $this->extractImageFormat($value),
                ];
            }
            // Regular fields
            else {
                $formatted[$name] = $value;
            }
        }

        return $formatted;
    }

    /**
     * Extract image format from data URL.
     */
    private function extractImageFormat(string $dataUrl): ?string
    {
        if (preg_match('/^data:image\/(\w+);base64/', $dataUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

}
