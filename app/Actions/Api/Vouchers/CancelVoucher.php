<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use App\Http\Responses\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @group Vouchers
 *
 * Cancel (soft delete) a voucher via API.
 *
 * Endpoint: DELETE /api/v1/vouchers/{voucher}
 *
 * Note: Can only cancel vouchers that haven't been redeemed yet.
 */
#[Group('Vouchers')]
class CancelVoucher
{
    use AsAction;

    /**
     * Cancel a voucher
     *
     * Soft delete an unredeemed voucher to prevent future redemption.
     */
    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Check if user owns this voucher
        if ($voucher->owner_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to cancel this voucher.');
        }

        // Check if voucher is already redeemed
        if ($voucher->isRedeemed()) {
            return ApiResponse::error(
                'Cannot cancel a voucher that has already been redeemed.',
                400
            );
        }

        // Soft delete the voucher
        $voucher->delete();

        return ApiResponse::success([
            'message' => 'Voucher cancelled successfully.',
            'code' => $voucher->code,
        ]);
    }
}
