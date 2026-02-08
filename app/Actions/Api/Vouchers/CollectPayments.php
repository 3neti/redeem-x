<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Collect Payments from Settlement Voucher
 *
 * Transfer collected payments from voucher's cash wallet to owner's personal wallet.
 * This is how lenders collect repayments from borrowers on settlement vouchers.
 *
 * @group Vouchers
 *
 * @authenticated
 */
#[Group('Vouchers')]
class CollectPayments
{
    /**
     * Collect payments from settlement voucher
     *
     * Transfer all collected payments from the voucher's cash wallet to the owner's wallet.
     * Only the voucher owner can collect. Only works for settlement/payable vouchers with balance.
     */
    public function __invoke(string $code, Request $request): JsonResponse
    {
        $user = $request->user();

        // Find voucher with owner
        $voucher = Voucher::with('owner')->where('code', $code)->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }

        // Check ownership
        if (! $voucher->owner || $voucher->owner->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this voucher',
            ], 403);
        }

        // Validate voucher type
        if (! in_array($voucher->voucher_type, [VoucherType::PAYABLE, VoucherType::SETTLEMENT])) {
            return response()->json([
                'success' => false,
                'message' => 'Only settlement and payable vouchers can have payments collected',
            ], 422);
        }

        // Get cash entity
        $cash = $voucher->cash;
        if (! $cash || ! $cash->wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher has no wallet',
            ], 500);
        }

        // Check if there's any balance to collect
        $paidTotal = $voucher->getPaidTotal();
        $availableBalance = $cash->balanceFloat;

        if ($availableBalance <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No payments available to collect',
            ], 422);
        }

        try {
            DB::beginTransaction();

            Log::info('[CollectPayments] Collecting payments from voucher', [
                'voucher_code' => $voucher->code,
                'owner_id' => $user->id,
                'available_balance' => $availableBalance,
                'owner_balance_before' => $user->balanceFloat,
            ]);

            // Transfer from cash wallet to user wallet
            // This is the "collection" - lender taking repayments
            $amountInMinorUnits = (int) ($availableBalance * 100);
            $transfer = $cash->transfer($user, $amountInMinorUnits, [
                'type' => 'voucher_collection',
                'voucher_code' => $voucher->code,
                'voucher_id' => $voucher->id,
                'collected_by_user_id' => $user->id,
                'payment_method' => 'Settlement Collection',
            ]);

            Log::info('[CollectPayments] Collection completed', [
                'voucher_code' => $voucher->code,
                'amount_collected' => $availableBalance,
                'transfer_uuid' => $transfer->uuid,
                'owner_balance_after' => $user->fresh()->balanceFloat,
                'cash_balance_after' => $cash->fresh()->balanceFloat,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payments collected successfully',
                'data' => [
                    'amount_collected' => $availableBalance,
                    'new_balance' => $user->fresh()->balanceFloat,
                    'voucher_remaining_balance' => $cash->fresh()->balanceFloat,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('[CollectPayments] Collection failed', [
                'voucher_code' => $voucher->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to collect payments: '.$e->getMessage(),
            ], 500);
        }
    }
}
