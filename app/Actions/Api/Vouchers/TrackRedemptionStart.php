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
     * 
     * Public endpoint - no authentication required since vouchers can be redeemed publicly.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Track redemption start using trait
        $voucher->trackRedemptionStart();

        return ApiResponse::success([
            'message' => 'Redemption start tracked successfully',
            'timing' => $voucher->timing?->toArray(),
        ]);
    }
}
