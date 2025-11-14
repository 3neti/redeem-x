<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseResponseData,
    DisburseInputData
};
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

        // Store disbursement details on voucher for easy access
        $voucher->metadata = array_merge(
            $voucher->metadata ?? [],
            [
                'disbursement' => [
                    'operation_id' => $response->transaction_id,
                    'transaction_uuid' => $response->uuid,
                    'status' => $response->status,
                    'amount' => $input->amount,
                    'bank' => $input->bank,
                    'rail' => $input->via,
                    'account' => $input->account_number,
                    'disbursed_at' => now()->toIso8601String(),
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
