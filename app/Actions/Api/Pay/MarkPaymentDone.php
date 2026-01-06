<?php

declare(strict_types=1);

namespace App\Actions\Api\Pay;

use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Mark Payment as Done
 *
 * Called by payer after completing payment via QR code to signal payment completion.
 * Updates payment request status to 'awaiting_confirmation' so voucher owner can confirm.
 * 
 * @group Payments
 */
#[Group('Payments')]
class MarkPaymentDone
{
    /**
     * Mark payment request as done (awaiting confirmation)
     * 
     * Payer calls this after scanning QR and completing payment in their bank app.
     * The voucher owner will then see this payment awaiting confirmation.
     */
    #[BodyParameter('payment_request_id', description: 'Payment request ID', type: 'integer', example: 1, required: true)]
    #[BodyParameter('payer_info', description: 'Optional payer details (name, mobile, etc.)', type: 'object', example: ['name' => 'Juan Dela Cruz', 'mobile' => '09171234567'], required: false)]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'payment_request_id' => ['required', 'integer', 'exists:payment_requests,id'],
            'payer_info' => ['nullable', 'array'],
        ]);
        
        $paymentRequestId = $request->input('payment_request_id');
        $payerInfo = $request->input('payer_info', []);
        
        $paymentRequest = PaymentRequest::find($paymentRequestId);
        
        if (!$paymentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request not found',
            ], 404);
        }
        
        // Only allow marking pending payments as done
        if ($paymentRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Payment request is not in pending status',
            ], 422);
        }
        
        // Update status and optionally store payer info
        $paymentRequest->update([
            'status' => 'awaiting_confirmation',
            'payer_info' => array_merge($paymentRequest->payer_info ?? [], $payerInfo),
        ]);
        
        Log::info('[MarkPaymentDone] Payment marked as done', [
            'payment_request_id' => $paymentRequestId,
            'reference_id' => $paymentRequest->reference_id,
            'voucher_code' => $paymentRequest->voucher->code ?? null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment marked as done. Awaiting voucher owner confirmation.',
            'data' => [
                'payment_request_id' => $paymentRequest->id,
                'reference_id' => $paymentRequest->reference_id,
                'status' => $paymentRequest->status,
            ],
        ]);
    }
}
