<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Dedoc\Scramble\Attributes\Group;

/**
 * @group Vouchers
 *
 * Set external metadata for a voucher via API.
 *
 * Endpoint: POST /api/v1/vouchers/{voucher}/external
 */
#[Group('Vouchers')]
class SetExternalMetadata
{
    use AsAction;

    /**
     * Set external metadata
     * 
     * Attach custom external metadata to a voucher for integration purposes.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Check if user owns this voucher
        if ($voucher->owner_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to modify this voucher.');
        }

        // Create ExternalMetadataData from validated request
        $externalMetadata = ExternalMetadataData::from($request->validated());

        // Set external metadata using trait
        $voucher->external_metadata = $externalMetadata;
        $voucher->save();

        return ApiResponse::success([
            'message' => 'External metadata updated successfully',
            'external_metadata' => $voucher->external_metadata,
        ]);
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'external_id' => 'nullable|string|max:255',
            'external_type' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'user_id' => 'nullable|string|max:255',
            'custom' => 'nullable|array',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function getValidationMessages(): array
    {
        return [
            'external_id.string' => 'External ID must be a string.',
            'external_type.string' => 'External type must be a string.',
            'reference_id.string' => 'Reference ID must be a string.',
            'user_id.string' => 'User ID must be a string.',
            'custom.array' => 'Custom data must be an array.',
        ];
    }
}
