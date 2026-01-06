<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Confirm Voucher Payment
 *
 * Manually confirm a payment received for a settlement/payable voucher.
 * Only the voucher owner can confirm payments.
 * 
 * @group Vouchers
 * @authenticated
 */
#[Group('Vouchers')]
class ConfirmPayment
{
    /**
     * Confirm payment for a voucher
     */
    #[BodyParameter('voucher_code', description: 'Voucher code', type: 'string', example: '2Q2T', required: true)]
    #[BodyParameter('amount', description: 'Payment amount in PHP', type: 'number', example: 100, required: true)]
    #[BodyParameter('payment_id', description: 'Optional payment reference ID', type: 'string', example: 'GCASH-123456')]
    #[BodyParameter('payer', description: 'Optional payer mobile/email', type: 'string', example: '09171234567')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'voucher_code' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_id' => ['nullable', 'string'],
            'payer' => ['nullable', 'string'],
        ]);

        $voucherCode = $request->input('voucher_code');
        $amount = (float) $request->input('amount');
        $paymentId = $request->input('payment_id') ?: 'WEB-' . now()->timestamp;
        $payer = $request->input('payer');
        $user = $request->user();

        // Find voucher with owner
        $voucher = Voucher::with('owner')->where('code', $voucherCode)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }

        // Check ownership
        if (!$voucher->owner || $voucher->owner->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this voucher',
            ], 403);
        }

        // Validate voucher can accept payment
        if (!$voucher->canAcceptPayment()) {
            return response()->json([
                'success' => false,
                'message' => 'This voucher cannot accept payments',
            ], 422);
        }

        // Validate amount against remaining
        $remaining = $voucher->getRemaining();
        if ($amount > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Amount exceeds remaining balance. Maximum: ₱{$remaining}",
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get cash entity (voucher's wallet)
            $cash = $voucher->cash;
            if (!$cash || !$cash->wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher has no wallet',
                ], 500);
            }

            // Credit cash entity's wallet
            $cash->wallet->deposit($amount * 100, [ // Convert to minor units
                'flow' => 'pay',
                'voucher_code' => $voucher->code,
                'payment_id' => $paymentId,
                'payer' => $payer,
                'confirmed_by' => 'web',
                'confirmed_by_user_id' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => [
                    'amount' => $amount,
                    'payment_id' => $paymentId,
                    'new_paid_total' => $voucher->fresh()->getPaidTotal(),
                    'remaining' => $voucher->fresh()->getRemaining(),
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
