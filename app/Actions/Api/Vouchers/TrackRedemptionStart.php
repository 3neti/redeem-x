<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Dedoc\Scramble\Attributes\Group;

/**
 * @group Vouchers
 *
 * Track voucher redemption start event via API.
 *
 * Endpoint: POST /api/v1/vouchers/{voucher}/timing/start
 */
#[Group('Vouchers')]
class TrackRedemptionStart
{
    use AsAction;

    /**
     * Track redemption start event
     * 
     * Record when a user begins the redemption process.
     * Public endpoint - no authentication required.
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
