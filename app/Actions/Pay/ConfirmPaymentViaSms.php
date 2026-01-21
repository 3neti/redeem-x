<?php

namespace App\Actions\Pay;

use App\Models\PaymentRequest;
use Illuminate\Http\RedirectResponse;

class ConfirmPaymentViaSms
{
    public function __invoke(PaymentRequest $paymentRequest): RedirectResponse
    {
        // Check if already confirmed
        if ($paymentRequest->status !== 'pending') {
            return redirect('/pay')->with('message', 'Payment already confirmed');
        }

        // Mark as awaiting confirmation
        $paymentRequest->markAsAwaitingConfirmation();

        return redirect('/pay')->with('success', 'Payment confirmed successfully! Please wait for voucher owner to verify.');
    }
}
