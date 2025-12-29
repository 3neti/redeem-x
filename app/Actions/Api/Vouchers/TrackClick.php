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
 * Track voucher click event via API.
 *
 * Endpoint: POST /api/v1/vouchers/{voucher}/timing/click
 */
#[Group('Vouchers')]
class TrackClick
{
    use AsAction;

    /**
     * Track voucher click event
     * 
     * Record when a user clicks on a voucher link (first click only, idempotent).
     * Public endpoint - no authentication required.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Track click using trait (idempotent - only tracks first click)
        $voucher->trackClick();

        return ApiResponse::success([
            'message' => 'Click tracked successfully',
            'timing' => $voucher->timing?->toArray(),
        ]);
    }
}
