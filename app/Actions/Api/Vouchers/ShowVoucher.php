<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Show voucher details via API.
 *
 * Endpoint: GET /api/v1/vouchers/{voucher}
 */
class ShowVoucher
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Check if user owns this voucher
        if ($voucher->owner_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to view this voucher.');
        }

        // Load relationships
        $voucher->load(['redeemers', 'owner']);

        // Transform to VoucherData DTO
        $voucherData = VoucherData::fromModel($voucher);

        // Add additional details
        $response = [
            'voucher' => $voucherData,
            'redemption_count' => $voucher->redeemers()->count(),
        ];

        // If voucher is redeemed, include redeemer details
        if ($voucher->isRedeemed()) {
            $redeemer = $voucher->redeemers->first();
            $response['redeemed_by'] = [
                'mobile' => $redeemer?->mobile,
                'name' => $redeemer?->name,
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
            ];
        }

        return ApiResponse::success($response);
    }

}
