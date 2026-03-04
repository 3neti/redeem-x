<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\Voucher\Events\DisburseInputPrepared;
use LBHurtado\Voucher\Exceptions\InvalidSettlementRailException;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\Wallet\Events\DisbursementFailed;
use RuntimeException;

class DisburseCash
{
    private const DEBUG = false;

    public function __construct(protected PaymentGatewayInterface $gateway) {}

    /**
     * Attempts to disburse the Cash entity attached to the voucher.
     *
     * @param  mixed  $voucher
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        if (self::DEBUG) {
            Log::debug('[DisburseCash] Starting', ['voucher' => $voucher->code]);
        }

        $input = DisburseInputData::fromVoucher($voucher);

        event(new DisburseInputPrepared($voucher, $input));

        if (self::DEBUG) {
            Log::debug('[DisburseCash] Payload ready', ['input' => $input->toArray()]);
        }

        // CRITICAL: Validate EMI + PESONET combination
        $bankRegistry = app(BankRegistry::class);
        $rail = SettlementRail::from($input->via);

        if ($rail === SettlementRail::PESONET && $bankRegistry->isEMI($input->bank)) {
            $bankName = $bankRegistry->getBankName($input->bank);

            Log::warning('[DisburseCash] EMI with PESONET detected - blocking disbursement', [
                'voucher' => $voucher->code,
                'bank_code' => $input->bank,
                'bank_name' => $bankName,
                'rail' => $rail->value,
                'amount' => $input->amount,
            ]);

            throw InvalidSettlementRailException::emiRequiresInstapay(
                $bankName,
                $input->bank,
                $rail->value
            );
        }

        // Attempt disbursement — failures should NOT roll back redemption.
        // Redemption is sacred: once user completes the flow, voucher stays redeemed.
        // Bank failures are recorded as 'pending' for later reconciliation.
        try {
            $response = $this->gateway->disburse($voucher->cash, $input);

            if ($response === false) {
                throw new RuntimeException('Gateway returned false - disbursement failed');
            }

            if (! $response instanceof DisburseResponseData) {
                throw new RuntimeException('Unexpected response type: '.gettype($response));
            }
        } catch (\Throwable $e) {
            Log::warning('[DisburseCash] Disbursement failed — recording pending status', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
                'amount' => $input->amount,
                'bank' => $input->bank,
                'via' => $input->via,
            ]);

            $this->recordPendingDisbursement($voucher, $input, $bankRegistry, $e);

            event(new DisbursementFailed($voucher, $e, $voucher->contact?->mobile));

            return $next($voucher);
        }

        // === SUCCESS PATH (gateway responded positively) ===

        // Store disbursement details on voucher in new generic format
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

        if (self::DEBUG) {
            Log::debug('[DisburseCash] Fee calculation', [
                'rail' => $rail->value,
                'fee_amount' => $feeAmount,
                'disbursement_amount' => $input->amount,
                'total_cost' => $totalCost,
                'fee_strategy' => $feeStrategy,
            ]);
        }

        // Withdraw funds from cash wallet (money has left the system)
        $cash = $voucher->cash;
        $withdrawal = WithdrawCash::run(
            $cash,
            $response->transaction_id,
            'Disbursed to external bank account',
            [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'flow' => 'redeem',
                'counterparty' => $bankName,
                'reference' => $input->account_number,
                'idempotency_key' => $response->uuid,
            ]
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
            'voucher' => $voucher->code,
            'transactionId' => $response->transaction_id,
            'uuid' => $response->uuid,
            'status' => $response->status,
            'amount' => $input->amount,
            'bank' => $input->bank,
            'via' => $input->via,
            'account' => $input->account_number,
        ]);

        return $next($voucher);
    }

    /**
     * Record a pending disbursement on the voucher when the gateway fails.
     * Preserves enough data for later reconciliation via disbursement:check/recover.
     */
    private function recordPendingDisbursement($voucher, DisburseInputData $input, BankRegistry $bankRegistry, \Throwable $e): void
    {
        $gatewayName = config('payment-gateway.default', 'netbank');
        $bankName = $bankRegistry->getBankName($input->bank);

        $voucher->metadata = array_merge($voucher->metadata ?? [], [
            'disbursement' => [
                'gateway' => $gatewayName,
                'transaction_id' => $input->reference,
                'status' => DisbursementStatus::PENDING->value,
                'amount' => $input->amount,
                'currency' => 'PHP',
                'settlement_rail' => $input->via,
                'recipient_identifier' => $input->account_number,
                'disbursed_at' => now()->toIso8601String(),
                'recipient_name' => $bankName,
                'payment_method' => 'bank_transfer',
                'error' => $e->getMessage(),
                'requires_reconciliation' => true,
                'metadata' => [
                    'bank_code' => $input->bank,
                    'bank_name' => $bankName,
                    'bank_logo' => $bankRegistry->getBankLogo($input->bank),
                    'rail' => $input->via,
                    'is_emi' => $bankRegistry->isEMI($input->bank),
                ],
            ],
        ]);
        $voucher->save();
    }
}
