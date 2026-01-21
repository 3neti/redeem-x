<?php

namespace App\Gateways;

use App\Events\PaymentDetectedButNotConfirmed;
use App\Services\DepositClassificationService;
use Bavix\Wallet\External\Dto\Extra;
use Bavix\Wallet\External\Dto\Option;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\Wallet\Services\SystemUserResolverService;
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
                $voucher = $paymentRequest->voucher;

                // Create unconfirmed transfer to voucher wallet
                $this->createUnconfirmedPaymentTransfer($paymentRequest, $voucher, $deposit);

                // Send SMS notification if still pending
                if ($paymentRequest->status === 'pending') {
                    event(new PaymentDetectedButNotConfirmed(
                        $paymentRequest->id,
                        $sender->mobile,
                        $deposit->amount,
                        $voucher->code,
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
    
    /**
     * Create unconfirmed transfer from system wallet to voucher wallet
     */
    protected function createUnconfirmedPaymentTransfer($paymentRequest, $voucher, DepositResponseData $deposit): void
    {
        try {
            // Get or create cash entity for voucher
            $cash = $voucher->cash;
            if (!$cash) {
                Log::info('[Payment] Creating cash entity for first payment', [
                    'voucher_code' => $voucher->code,
                ]);
                
                $cash = \LBHurtado\Cash\Models\Cash::create([
                    'amount' => 0,
                    'currency' => 'PHP',
                ]);
                $voucher->cashable()->associate($cash);
                $voucher->save();
            }
            
            // Get system wallet
            $system = app(SystemUserResolverService::class)->resolve();
            
            // Create UNCONFIRMED transfer: System → Voucher
            $transfer = $system->transferFloat(
                $cash,
                $deposit->amount,
                new Extra(
                    withdraw: new Option(
                        meta: [
                            'payment_request_id' => $paymentRequest->id,
                            'flow' => 'payment',
                            'voucher_code' => $voucher->code,
                        ],
                        confirmed: true  // System wallet withdraw: confirmed
                    ),
                    deposit: new Option(
                        meta: [
                            'payment_request_id' => $paymentRequest->id,
                            'reference_id' => $paymentRequest->reference_id,
                            'voucher_code' => $voucher->code,
                            'flow' => 'payment',
                            'sender_mobile' => $deposit->sender->accountNumber,
                            'sender_name' => $deposit->sender->name,
                        ],
                        confirmed: false  // Voucher wallet deposit: UNCONFIRMED
                    )
                )
            );
            
            // Store transaction UUID in PaymentRequest for later confirmation
            $paymentRequest->update([
                'meta' => [
                    'transaction_uuid' => $transfer->deposit->uuid,
                    'transfer_uuid' => $transfer->uuid,
                ],
            ]);
            
            Log::info('[Payment] Unconfirmed transfer created', [
                'payment_request_id' => $paymentRequest->id,
                'voucher_code' => $voucher->code,
                'amount' => $deposit->amount,
                'transaction_uuid' => $transfer->deposit->uuid,
                'deposit_confirmed' => $transfer->deposit->confirmed,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[Payment] Failed to create unconfirmed transfer', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
