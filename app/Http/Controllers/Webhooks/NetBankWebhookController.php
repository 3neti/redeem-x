<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\TopUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;

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

            $topUp = TopUp::where('reference_no', $validated['reference_no'])->first();

            if (!$topUp) {
                Log::warning('[NetBank Webhook] Top-up not found', [
                    'reference_no' => $validated['reference_no'],
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
                $user = $topUp->user;
                $user->creditWalletFromTopUp($topUp);

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
}
