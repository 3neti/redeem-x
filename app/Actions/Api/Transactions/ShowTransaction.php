<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;

/**
 * Show Transaction Details
 *
 * Retrieve detailed information about a specific voucher redemption transaction.
 * 
 * Returns complete transaction data including voucher details, redemption information,
 * disbursement status, and redeemer details. Essential for transaction lookups,
 * customer support, and detailed auditing.
 * 
 * **Transaction Details Include:**
 * - Voucher code, amount, and currency
 * - Redemption timestamp and location
 * - Disbursement bank, account, and status
 * - Settlement rail and operation IDs
 * - Redeemer information (if available)
 * - Input data collected during redemption
 * 
 * **Use Cases:**
 * - Customer support inquiries
 * - Transaction dispute resolution
 * - Detailed audit trails
 * - Debugging disbursement issues
 *
 * @group Transactions
 * @authenticated
 */
#[Group('Transactions')]
class ShowTransaction
{
    /**
     * Get transaction details.
     *
     * Retrieve complete details of a specific voucher redemption transaction including disbursement status.
     */
    #[PathParameter('voucher', description: 'Voucher code to retrieve transaction for. Must be a redeemed voucher. Example: "PROMO-AB12CD34"', type: 'string', example: 'PROMO-AB12CD34')]
    public function __invoke(Request $request, Voucher $voucher): JsonResponse
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
