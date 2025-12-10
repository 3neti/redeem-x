<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\{
    DisburseResponseData,
    DisburseInputData
};
use LBHurtado\PaymentGateway\Support\BankRegistry;
use LBHurtado\PaymentGateway\Enums\{DisbursementStatus, SettlementRail};
use LBHurtado\Voucher\Events\DisburseInputPrepared;
use LBHurtado\Wallet\Actions\WithdrawCash;
use Illuminate\Support\Facades\Log;
use Closure;


class DisburseCash
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    /**
     * Attempts to disburse the Cash entity attached to the voucher.
     *
     * @param  mixed    $voucher
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        Log::debug('[DisburseCash] Starting', ['voucher' => $voucher->code]);

        $input = DisburseInputData::fromVoucher($voucher);

        event(new DisburseInputPrepared($voucher, $input));

        Log::debug('[DisburseCash] Payload ready', ['input' => $input->toArray()]);

        // TODO: make a pipeline to check voucher->cash and voucher->contact
        $response = $this->gateway->disburse($voucher->cash, $input);

        if ($response === false) {
            Log::error('[DisburseCash] Gateway returned false', [
                'voucher' => $voucher->code,
                'redeemer'=> $voucher->contact->mobile,
                'amount'  => $input->amount,
            ]);
            return null;
        }

        if (! $response instanceof DisburseResponseData) {
            Log::warning('[DisburseCash] Unexpected response type', [
                'voucher' => $voucher->code,
                'type'    => gettype($response),
            ]);
            return null;
        }

        // Store disbursement details on voucher in new generic format
        $bankRegistry = app(BankRegistry::class);
        $bankName = $bankRegistry->getBankName($input->bank);
        $bankLogo = $bankRegistry->getBankLogo($input->bank);
        $isEmi = $bankRegistry->isEMI($input->bank);
        
        // Normalize status using DisbursementStatus enum
        $gatewayName = config('payment-gateway.default', 'netbank');
        $normalizedStatus = DisbursementStatus::fromGateway($gatewayName, $response->status)->value;
        
        // Get fee for the selected rail
        $rail = SettlementRail::from($input->via);
        $feeAmount = $this->gateway->getRailFee($rail);
        $totalCost = ($input->amount * 100) + $feeAmount; // amount in pesos to centavos + fee
        $feeStrategy = $voucher->instructions?->cash?->fee_strategy ?? 'absorb';
        
        Log::debug('[DisburseCash] Fee calculation', [
            'rail' => $rail->value,
            'fee_amount' => $feeAmount,
            'disbursement_amount' => $input->amount,
            'total_cost' => $totalCost,
            'fee_strategy' => $feeStrategy,
        ]);
        
        // Withdraw funds from cash wallet (money has left the system)
        $cash = $voucher->cash;
        $withdrawal = WithdrawCash::run(
            $cash,
            $response->transaction_id,
            'Disbursed to external bank account'
        );
        
        $voucher->metadata = array_merge(
            $voucher->metadata ?? [],
            [
                'disbursement' => [
                    // New generic format
                    'gateway' => $gatewayName,
                    'transaction_id' => $response->transaction_id,
                    'status' => $normalizedStatus,
                    'amount' => $input->amount,
                    'currency' => 'PHP',
                    'settlement_rail' => $rail->value,
                    'fee_amount' => $feeAmount,
                    'total_cost' => $totalCost,
                    'fee_strategy' => $feeStrategy,
                    'recipient_identifier' => $input->account_number,
                    'disbursed_at' => now()->toIso8601String(),
                    'transaction_uuid' => $response->uuid,
                    'recipient_name' => $bankName,
                    'payment_method' => 'bank_transfer',
                    'cash_withdrawal_uuid' => $withdrawal->uuid,
                    'metadata' => [
                        'bank_code' => $input->bank,
                        'bank_name' => $bankName,
                        'bank_logo' => $bankLogo,
                        'rail' => $input->via,
                        'is_emi' => $isEmi,
                        // Legacy fields for backward compatibility
                        'operation_id' => $response->transaction_id,
                        'account' => $input->account_number,
                        'bank' => $input->bank,
                    ],
                ],
            ]
        );
        $voucher->save();

        Log::info('[DisburseCash] Success', [
            'voucher'       => $voucher->code,
            'transactionId' => $response->transaction_id,
            'uuid'          => $response->uuid,
            'status'        => $response->status,
            'amount'        => $input->amount,
            'bank'          => $input->bank,
            'via'           => $input->via,
            'account'       => $input->account_number,
        ]);

        return $next($voucher);
    }
}
