<?php

namespace App\Actions\Pay;

use App\Models\PaymentRequest;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Notifications\PaymentConfirmationNotification;

class ConfirmPaymentViaSms
{
    public function __invoke(PaymentRequest $paymentRequest): RedirectResponse
    {
        $voucher = $paymentRequest->voucher;
        
        // Check if already confirmed
        if ($paymentRequest->status !== 'pending') {
            return redirect()->route('pay.confirmed', $paymentRequest);
        }

        try {
            // Confirm the unconfirmed transaction
            $this->confirmPaymentTransaction($paymentRequest);
            
            // Mark payment request as awaiting final owner confirmation
            $paymentRequest->markAsAwaitingConfirmation();

            // Persist a database notification for audit/UI (same content as SMS)
            $paymentRequest->notify(new PaymentConfirmationNotification($paymentRequest));
            
            Log::info('[SMS Confirm] Payment confirmed by payer', [
                'payment_request_id' => $paymentRequest->id,
                'voucher_code' => $voucher->code,
            ]);
            
            return redirect()->route('pay.confirmed', $paymentRequest);
            
        } catch (\Throwable $e) {
            Log::error('[SMS Confirm] Failed to confirm payment', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect('/pay')->with('error', 'Failed to confirm payment. Please contact support.');
        }
    }
    
    /**
     * Confirm the unconfirmed transaction
     */
    protected function confirmPaymentTransaction(PaymentRequest $paymentRequest): void
    {
        $transactionUuid = $paymentRequest->meta['transaction_uuid'] ?? null;
        
        if (!$transactionUuid) {
            Log::warning('[SMS Confirm] No transaction UUID found', [
                'payment_request_id' => $paymentRequest->id,
            ]);
            return;
        }
        
        // Find the unconfirmed transaction
        $transaction = Transaction::where('uuid', $transactionUuid)
            ->where('confirmed', false)
            ->first();
        
        if (!$transaction) {
            Log::warning('[SMS Confirm] Transaction not found or already confirmed', [
                'payment_request_id' => $paymentRequest->id,
                'transaction_uuid' => $transactionUuid,
            ]);
            return;
        }
        
        // Verify it belongs to the voucher's wallet (safety check)
        $voucher = $paymentRequest->voucher;
        if ($transaction->wallet_id !== $voucher->cash->wallet->getKey()) {
            Log::error('[SMS Confirm] Transaction wallet mismatch', [
                'payment_request_id' => $paymentRequest->id,
                'transaction_wallet' => $transaction->wallet_id,
                'expected_wallet' => $voucher->cash->wallet->getKey(),
            ]);
            throw new \RuntimeException('Transaction wallet mismatch');
        }
        
        // Confirm the transaction (credits the voucher wallet)
        $voucher->cash->confirm($transaction);
        
        Log::info('[SMS Confirm] Transaction confirmed', [
            'payment_request_id' => $paymentRequest->id,
            'transaction_uuid' => $transactionUuid,
            'voucher_code' => $voucher->code,
        ]);
    }
}
