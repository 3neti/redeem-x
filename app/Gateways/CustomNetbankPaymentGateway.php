<?php

namespace App\Gateways;

use App\Events\PaymentDetectedButNotConfirmed;
use App\Services\DepositClassificationService;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use Illuminate\Support\Facades\Log;

class CustomNetbankPaymentGateway extends NetbankPaymentGateway
{
    /**
     * Override hook to add payment classification logic
     */
    protected function afterDepositConfirmed(DepositResponseData $deposit, ?Contact $sender): void
    {
        if (!$sender) {
            return;
        }

        try {
            // Classify deposit using host app service
            $classification = app(DepositClassificationService::class)->classify($deposit->toArray());

            if ($classification['type'] === 'payment' && $classification['model']) {
                $paymentRequest = $classification['model'];

                // Only send SMS if still pending (not already confirmed manually)
                if ($paymentRequest->status === 'pending') {
                    event(new PaymentDetectedButNotConfirmed(
                        $paymentRequest->id,
                        $sender->mobile,
                        $deposit->amount,
                        $paymentRequest->voucher->code,
                    ));

                    Log::info('Payment detection event fired', [
                        'payment_request_id' => $paymentRequest->id,
                        'classification_strategy' => $classification['strategy'],
                        'confidence' => $classification['confidence'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to classify deposit for payment', [
                'error' => $e->getMessage(),
                'deposit_amount' => $deposit->amount,
            ]);
            // Don't fail the deposit - just log and continue
        }
    }
}
