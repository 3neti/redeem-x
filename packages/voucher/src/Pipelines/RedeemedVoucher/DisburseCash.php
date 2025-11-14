<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseResponseData,
    DisburseInputData
};
use LBHurtado\PaymentGateway\Support\BankRegistry;
use LBHurtado\Voucher\Events\DisburseInputPrepared;
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
        
        $voucher->metadata = array_merge(
            $voucher->metadata ?? [],
            [
                'disbursement' => [
                    // New generic format
                    'gateway' => 'netbank',
                    'transaction_id' => $response->transaction_id,
                    'status' => $response->status,
                    'amount' => $input->amount,
                    'currency' => 'PHP',
                    'recipient_identifier' => $input->account_number,
                    'disbursed_at' => now()->toIso8601String(),
                    'transaction_uuid' => $response->uuid,
                    'recipient_name' => $bankName,
                    'payment_method' => 'bank_transfer',
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
