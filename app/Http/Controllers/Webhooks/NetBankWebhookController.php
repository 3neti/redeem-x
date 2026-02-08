<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\TopUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;

class NetBankWebhookController extends Controller
{
    /**
     * Handle Direct Checkout payment notification.
     */
    public function handlePayment(Request $request)
    {
        Log::info('[NetBank Webhook] Payment notification received', [
            'payload' => $request->all(),
        ]);

        try {
            // Validate required fields
            $validated = $request->validate([
                'reference_no' => 'required|string',
                'payment_id' => 'required|string',
                'payment_status' => 'required|string',
                'amount' => 'required|array',
                'amount.value' => 'required|numeric',
            ]);

            // Detect payment type: voucher or top-up
            $referenceNo = $validated['reference_no'];
            $isVoucherPayment = ! str_starts_with($referenceNo, 'TOPUP-');

            if ($isVoucherPayment && Feature::active('settlement-vouchers')) {
                return $this->handleVoucherPayment($validated);
            }

            // Handle top-up payment
            $topUp = TopUp::where('reference_no', $referenceNo)->first();

            if (! $topUp) {
                Log::warning('[NetBank Webhook] Top-up not found', [
                    'reference_no' => $referenceNo,
                ]);

                return response()->json([
                    'error' => 'Top-up not found',
                ], 404);
            }

            // Check if already processed
            if ($topUp->isPaid()) {
                Log::info('[NetBank Webhook] Top-up already processed', [
                    'reference_no' => $topUp->reference_no,
                ]);

                return response()->json([
                    'message' => 'Already processed',
                ], 200);
            }

            // Update top-up status
            if (strtoupper($validated['payment_status']) === 'PAID') {
                $topUp->markAsPaid($validated['payment_id']);

                // Credit user wallet
                // For webhook top-ups, the initiator is the user themselves (not an admin)
                $user = $topUp->user;
                $user->creditWalletFromTopUp($topUp, $user);

                Log::info('[NetBank Webhook] Top-up completed and wallet credited', [
                    'user_id' => $user->id,
                    'reference_no' => $topUp->reference_no,
                    'amount' => $topUp->amount,
                    'new_balance' => $user->fresh()->balanceFloat,
                ]);

                return response()->json([
                    'message' => 'Payment processed successfully',
                ], 200);
            }

            // Handle other statuses (FAILED, EXPIRED)
            $topUp->update([
                'payment_status' => strtoupper($validated['payment_status']),
            ]);

            Log::info('[NetBank Webhook] Top-up status updated', [
                'reference_no' => $topUp->reference_no,
                'status' => $validated['payment_status'],
            ]);

            return response()->json([
                'message' => 'Status updated',
            ], 200);
        } catch (\Exception $e) {
            Log::error('[NetBank Webhook] Processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Handle voucher payment notification.
     */
    protected function handleVoucherPayment(array $validated)
    {
        $voucherCode = $validated['reference_no'];

        Log::info('[NetBank Webhook] Voucher payment notification received', [
            'voucher_code' => $voucherCode,
            'payment_id' => $validated['payment_id'],
            'amount' => $validated['amount']['value'],
            'status' => $validated['payment_status'],
        ]);

        // Find voucher
        $voucher = Voucher::where('code', $voucherCode)->first();

        if (! $voucher) {
            Log::warning('[NetBank Webhook] Voucher not found', [
                'voucher_code' => $voucherCode,
            ]);

            return response()->json([
                'error' => 'Voucher not found',
            ], 404);
        }

        // Check if voucher can accept payments
        if (! $voucher->canAcceptPayment()) {
            Log::warning('[NetBank Webhook] Voucher cannot accept payments', [
                'voucher_code' => $voucherCode,
                'type' => $voucher->voucher_type->value,
                'state' => $voucher->state->value,
                'expired' => $voucher->isExpired(),
            ]);

            return response()->json([
                'error' => 'Voucher cannot accept payments',
            ], 422);
        }

        // Only process PAID status
        if (strtoupper($validated['payment_status']) !== 'PAID') {
            Log::info('[NetBank Webhook] Non-paid status ignored', [
                'voucher_code' => $voucherCode,
                'status' => $validated['payment_status'],
            ]);

            return response()->json([
                'message' => 'Status noted',
            ], 200);
        }

        // Get payment amount
        $paymentAmount = (float) $validated['amount']['value'];
        $amountInCents = (int) ($paymentAmount * 100);

        // Check for duplicate payment (idempotency)
        $existingPayment = $voucher->cash?->wallet?->transactions()
            ->where('type', 'deposit')
            ->whereJsonContains('meta->flow', 'pay')
            ->whereJsonContains('meta->payment_id', $validated['payment_id'])
            ->exists();

        if ($existingPayment) {
            Log::info('[NetBank Webhook] Duplicate payment detected', [
                'voucher_code' => $voucherCode,
                'payment_id' => $validated['payment_id'],
            ]);

            return response()->json([
                'message' => 'Payment already processed',
            ], 200);
        }

        DB::beginTransaction();
        try {
            // Credit cash wallet
            $voucher->cash->wallet->deposit($amountInCents, [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'flow' => 'pay',
                'payment_id' => $validated['payment_id'],
                'gateway' => 'netbank',
                'type' => 'voucher_payment',
            ]);

            // Refresh voucher to get updated totals
            $voucher->refresh();
            $paidTotal = $voucher->getPaidTotal();
            $remaining = $voucher->getRemaining();

            Log::info('[NetBank Webhook] Voucher payment credited', [
                'voucher_code' => $voucherCode,
                'payment_amount' => $paymentAmount,
                'paid_total' => $paidTotal,
                'remaining' => $remaining,
                'target_amount' => $voucher->target_amount,
            ]);

            // Auto-close if fully paid (within ₱0.01 tolerance)
            if ($remaining <= 0.01) {
                $voucher->update([
                    'state' => VoucherState::CLOSED,
                    'closed_at' => now(),
                ]);

                Log::info('[NetBank Webhook] Voucher auto-closed (fully paid)', [
                    'voucher_code' => $voucherCode,
                    'final_paid_total' => $paidTotal,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Voucher payment processed successfully',
                'voucher_code' => $voucherCode,
                'paid_total' => $paidTotal,
                'remaining' => max(0, $remaining),
                'auto_closed' => $remaining <= 0.01,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[NetBank Webhook] Voucher payment credit failed', [
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Payment credit failed',
            ], 500);
        }
    }
}
