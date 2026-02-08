<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Lock Voucher
 *
 * Temporarily suspend a voucher to prevent both payments and redemptions.
 * Useful for fraud prevention, dispute resolution, or investigation.
 *
 * @group Vouchers
 *
 * @authenticated
 */
#[Group('Vouchers')]
class LockVoucher
{
    /**
     * Lock a voucher
     *
     * Change voucher state to LOCKED. Only vouchers in ACTIVE state can be locked.
     * Locked vouchers cannot accept payments or be redeemed.
     */
    #[BodyParameter('code', description: 'Voucher code', type: 'string', example: '2Q2T', required: true)]
    #[BodyParameter('reason', description: 'Reason for locking (audit trail)', type: 'string', example: 'Fraud investigation', required: false)]
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

        if (! $voucher) {
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
        if ($voucher->state !== VoucherState::ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => "Cannot lock voucher. Current state: {$voucher->state->value}. Only ACTIVE vouchers can be locked.",
            ], 422);
        }

        // Lock voucher
        $oldState = $voucher->state;
        $voucher->update([
            'state' => VoucherState::LOCKED,
            'locked_at' => now(),
            'metadata' => array_merge($voucher->metadata ?? [], [
                'lock_history' => array_merge($voucher->metadata['lock_history'] ?? [], [[
                    'locked_at' => now()->toIso8601String(),
                    'locked_by_user_id' => $user->id,
                    'locked_by_name' => $user->name,
                    'reason' => $reason,
                    'previous_state' => $oldState->value,
                ]]),
            ]),
        ]);

        Log::info('[LockVoucher] Voucher locked', [
            'voucher_code' => $voucher->code,
            'user_id' => $user->id,
            'reason' => $reason,
            'old_state' => $oldState->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher locked successfully',
            'data' => [
                'code' => $voucher->code,
                'state' => $voucher->state->value,
                'locked_at' => $voucher->locked_at->toIso8601String(),
            ],
        ]);
    }
}
