<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Track voucher redemption start event via API.
 *
 * Endpoint: POST /api/v1/vouchers/{voucher}/timing/start
 */
class TrackRedemptionStart
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Check if user owns this voucher
        if ($voucher->owner_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to modify this voucher.');
        }

        // Track redemption start using trait
        $voucher->trackRedemptionStart();
        $voucher->save();

        return ApiResponse::success([
            'message' => 'Redemption start tracked successfully',
            'timing' => $voucher->timing,
        ]);
    }
}
