<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Show single transaction details via API.
 *
 * Endpoint: GET /api/v1/transactions/{voucher}
 */
class ShowTransaction
{
    use AsAction;

    public function asController(ActionRequest $request, Voucher $voucher): JsonResponse
    {
        // Load relationships
        $voucher->load(['owner', 'redeemers']);
        
        // Transform to VoucherData DTO
        $voucherData = VoucherData::fromModel($voucher);
        
        // Check if it's a redeemed voucher using DTO's computed field
        if (!$voucherData->is_redeemed) {
            return ApiResponse::error('Voucher has not been redeemed yet.', 404);
        }

        return ApiResponse::success([
            'transaction' => $voucherData,
            'redemption_count' => $voucher->redeemers()->count(),
        ]);
    }
}
