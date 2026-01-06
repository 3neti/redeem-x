<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Force Close Voucher
 *
 * Manually close a settlement/payable voucher before reaching full payment.
 * Useful for partial fulfillment scenarios or ending payment collection early.
 * 
 * @group Vouchers
 * @authenticated
 */
#[Group('Vouchers')]
class ForceCloseVoucher
{
    /**
     * Force close a voucher
     * 
     * Change voucher state to CLOSED. Only ACTIVE or LOCKED settlement/payable vouchers can be force-closed.
     * Cannot close redeemable vouchers (use cancel instead).
     */
    #[BodyParameter('code', description: 'Voucher code', type: 'string', example: '2Q2T', required: true)]
    #[BodyParameter('reason', description: 'Reason for force closing (audit trail)', type: 'string', example: 'Partial fulfillment accepted', required: false)]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $code = $request->input('code');
        $reason = $request->input('reason');
        $user = $request->user();

        // Find voucher
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }

        // Check ownership
        if ($voucher->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this voucher',
            ], 403);
        }

        // Validate voucher type
        if ($voucher->voucher_type === VoucherType::REDEEMABLE) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot force close a REDEEMABLE voucher. Use cancel instead.',
            ], 422);
        }

        // Validate current state
        if (!in_array($voucher->state, [VoucherState::ACTIVE, VoucherState::LOCKED])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot force close voucher. Current state: {$voucher->state->value}. Only ACTIVE or LOCKED vouchers can be closed.",
            ], 422);
        }

        // Check if already fully paid
        if ($voucher->getRemaining() <= 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher is already fully paid. No need to force close.',
            ], 422);
        }

        // Force close voucher
        $oldState = $voucher->state;
        $paidTotal = $voucher->getPaidTotal();
        $targetAmount = $voucher->target_amount;
        
        $voucher->update([
            'state' => VoucherState::CLOSED,
            'closed_at' => now(),
            'metadata' => array_merge($voucher->metadata ?? [], [
                'force_close_history' => array_merge($voucher->metadata['force_close_history'] ?? [], [[
                    'closed_at' => now()->toIso8601String(),
                    'closed_by_user_id' => $user->id,
                    'closed_by_name' => $user->name,
                    'reason' => $reason,
                    'previous_state' => $oldState->value,
                    'paid_total' => $paidTotal,
                    'target_amount' => $targetAmount,
                    'remaining' => $targetAmount - $paidTotal,
                ]]),
            ]),
        ]);

        Log::info('[ForceCloseVoucher] Voucher force closed', [
            'voucher_code' => $voucher->code,
            'user_id' => $user->id,
            'reason' => $reason,
            'old_state' => $oldState->value,
            'paid_total' => $paidTotal,
            'target_amount' => $targetAmount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher closed successfully',
            'data' => [
                'code' => $voucher->code,
                'state' => $voucher->state->value,
                'closed_at' => $voucher->closed_at->toIso8601String(),
                'paid_total' => $paidTotal,
                'target_amount' => $targetAmount,
            ],
        ]);
    }
}
