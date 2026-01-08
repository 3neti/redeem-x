<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Services\ReferenceLookup;
use LBHurtado\PaymentGateway\Services\ResolvePayable;
use LBHurtado\PaymentGateway\Tests\Models\User;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DepositConfirmed;
use LBHurtado\Wallet\Jobs\BroadcastBalanceUpdated;
use LBHurtado\Contact\Models\Contact;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;

trait CanConfirmDeposit
{

    public function confirmDeposit(array $payload): bool
    {
        $response = DepositResponseData::from($payload);
        Log::info('Processing Netbank deposit confirmation', $response->toArray());

        $dto = RecipientAccountNumberData::fromRecipientAccountNumber(
            $response->recipientAccountNumber
        );

        try {
            $wallet = app(ResolvePayable::class)->execute($dto);
        } catch (\Throwable $e) {
            Log::error('Could not resolve recipient to a wallet', [
                'error' => $e->getMessage(),
                'payload' => $response->toArray(),
            ]);
            return false;
        }

        if (! $wallet instanceof Wallet) {
            Log::warning('No wallet found for reference or mobile', [
                'referenceCode' => $dto->referenceCode,
                'alias'         => $dto->alias,
            ]);
            return false;
        }
        
        // Create/update sender contact
        $sender = null;
        if ($wallet instanceof \App\Models\User) {
            try {
                $sender = Contact::fromWebhookSender([
                    'accountNumber' => $response->sender->accountNumber,
                    'name' => $response->sender->name,
                    'institutionCode' => $response->sender->institutionCode,
                ]);
                
                Log::info('Sender contact processed', [
                    'contact_id' => $sender->id,
                    'mobile' => $sender->mobile,
                    'name' => $sender->name,
                ]);
                
            } catch (\Throwable $e) {
                Log::error('Failed to create sender contact', [
                    'error' => $e->getMessage(),
                    'sender_data' => [
                        'account' => $response->sender->accountNumber,
                        'name' => $response->sender->name,
                        'institution' => $response->sender->institutionCode,
                    ],
                ]);
                // Continue processing - don't fail deposit on contact creation error
            }
        }

        $this->transferToWallet($wallet, $response);
        
        // Record sender relationship
        if ($sender && $wallet instanceof \App\Models\User) {
            try {
                $wallet->recordDepositFrom($sender, $response->amount, [
                    'operation_id' => $response->operationId,
                    'channel' => $response->channel,
                    'reference_number' => $response->referenceNumber,
                    'institution' => $response->sender->institutionCode,
                    'transfer_type' => $response->transferType,
                    'timestamp' => $response->registrationTime,
                ]);
                
                Log::info('Sender relationship recorded', [
                    'user_id' => $wallet->id,
                    'contact_id' => $sender->id,
                    'amount' => $response->amount,
                ]);
                
            } catch (\Throwable $e) {
                Log::error('Failed to record sender relationship', [
                    'error' => $e->getMessage(),
                    'user_id' => $wallet->id ?? null,
                    'contact_id' => $sender->id ?? null,
                ]);
            }
        }

        return true;
    }
//
//    public function confirmDeposit(array $payload): bool
//    {
//        $response = DepositResponseData::from($payload);
//        Log::info('Processing Netbank deposit confirmation', $response->toArray());
//
//
//        $recipientAccountNumberData = RecipientAccountNumberData::fromRecipientAccountNumber($response->recipientAccountNumber);
//        $user = app(ResolvePayable::class)->execute($recipientAccountNumberData);
//
////        $user = app(config('payment-gateway.models.user'))::findByMobile($recipientAccountNumberData->referenceCode);
////        $user = app(config('payment-gateway.models.user'))::findByMobile($response->merchant_details->merchant_account);
//
//        if (!$user) {
//            Log::warning('No user wallet found for mobile or voucher code.');
//            return false;
//        }
//
//        $this->transferToWallet($user, $response);
//
//        return true;
//    }

    protected function transferToWallet(Wallet $user, DepositResponseData $deposit): void
    {
        // NetBank sends amounts in pesos (e.g., 15 = ₱15.00)
        $amountInPesos = $deposit->amount;
        
        // TODO: Distinguish between top-up and settlement repayment
        // Current: All deposits treated as user top-up (works for both via manual confirmation)
        // Future: When QR encodes voucher code, use CheckVoucher to return Cash entity for direct repayment
        // For now, settlement payments are manually confirmed via /pay/voucher endpoint
        $logMessage = 'Treating deposit as user top-up';
        $transaction = TopupWalletAction::run($user, $amountInPesos)->deposit;
        Log::info($logMessage, ['amount_pesos' => $amountInPesos, 'amount_centavos' => $deposit->amount]);

        $transaction->meta = $deposit->toArray();
        $transaction->save();

        DepositConfirmed::dispatch($transaction);
        
        // Queue job to broadcast balance update
        $wallet = $user instanceof \LBHurtado\Cash\Models\Cash ? $user : $user->wallet;
        if ($wallet) {
            BroadcastBalanceUpdated::dispatch($wallet->getKey());
        }
    }
}
