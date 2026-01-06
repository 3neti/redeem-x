<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Unlock Voucher
 *
 * Restore a locked voucher back to ACTIVE state.
 * Cannot unlock expired, closed, or cancelled vouchers.
 * 
 * @group Vouchers
 * @authenticated
 */
#[Group('Vouchers')]
class UnlockVoucher
{
    /**
     * Unlock a voucher
     * 
     * Change voucher state from LOCKED back to ACTIVE.
     * Only locked vouchers that haven't expired can be unlocked.
     */
    #[BodyParameter('code', description: 'Voucher code', type: 'string', example: '2Q2T', required: true)]
    #[BodyParameter('reason', description: 'Reason for unlocking (audit trail)', type: 'string', example: 'Investigation completed', required: false)]
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

        // Validate current state
        if ($voucher->state !== VoucherState::LOCKED) {
            return response()->json([
                'success' => false,
                'message' => "Cannot unlock voucher. Current state: {$voucher->state->value}. Only LOCKED vouchers can be unlocked.",
            ], 422);
        }

        // Check if expired
        if ($voucher->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unlock an expired voucher',
            ], 422);
        }

        // Unlock voucher
        $oldState = $voucher->state;
        $voucher->update([
            'state' => VoucherState::ACTIVE,
            'metadata' => array_merge($voucher->metadata ?? [], [
                'unlock_history' => array_merge($voucher->metadata['unlock_history'] ?? [], [[
                    'unlocked_at' => now()->toIso8601String(),
                    'unlocked_by_user_id' => $user->id,
                    'unlocked_by_name' => $user->name,
                    'reason' => $reason,
                    'previous_state' => $oldState->value,
                ]]),
            ]),
        ]);

        Log::info('[UnlockVoucher] Voucher unlocked', [
            'voucher_code' => $voucher->code,
            'user_id' => $user->id,
            'reason' => $reason,
            'old_state' => $oldState->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher unlocked successfully',
            'data' => [
                'code' => $voucher->code,
                'state' => $voucher->state->value,
            ],
        ]);
    }
}
