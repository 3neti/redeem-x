<?php

declare(strict_types=1);

namespace App\Actions\Voucher;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\Wallet\Events\DisbursementFailed;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Withdraw a slice from a divisible voucher.
 *
 * This action handles subsequent withdrawals after the initial redemption.
 * It validates state, determines amount, calls the payment gateway to
 * disburse funds externally, then records the wallet withdrawal.
 */
class WithdrawFromVoucher
{
    use AsAction;

    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected BankRegistry $bankRegistry,
    ) {}

    /**
     * API endpoint: POST /api/v1/vouchers/{code}/withdraw
     */
    public function asController(Voucher $voucher, ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Look up contact by mobile
        $contact = $voucher->contact;

        if (! $contact) {
            return ApiResponse::error('This voucher has not been redeemed yet.', 422);
        }

        // Normalize mobile for comparison (last 10 digits)
        $normalizedInput = preg_replace('/\D/', '', $validated['mobile']);
        $normalizedContact = preg_replace('/\D/', '', $contact->mobile ?? '');

        if (substr($normalizedInput, -10) !== substr($normalizedContact, -10)) {
            return ApiResponse::error('Mobile number does not match the original redeemer.', 403);
        }

        $amount = isset($validated['amount']) ? (float) $validated['amount'] : null;

        try {
            $result = $this->handle($voucher, $contact, $amount);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result);
    }

    /**
     * Validation rules for API endpoint.
     */
    public function rules(): array
    {
        return [
            'mobile' => 'required|string',
            'amount' => 'nullable|numeric|min:1',
        ];
    }

    /**
     * @param  Voucher  $voucher  The divisible voucher
     * @param  Contact  $contact  The contact requesting withdrawal (must match original redeemer)
     * @param  float|null  $amount  Withdrawal amount in major units (required for open mode, ignored for fixed)
     * @return array{success: bool, amount: float, slice_number: int, remaining_slices: int, bank_code: string, account_number: string}
     *
     * @throws \RuntimeException If voucher is not withdrawable
     * @throws \InvalidArgumentException If amount is invalid
     */
    public function handle(Voucher $voucher, Contact $contact, ?float $amount = null): array
    {
        // 1. Validate voucher is divisible
        if (! $voucher->isDivisible()) {
            throw new \RuntimeException('This voucher is not divisible.');
        }

        // 2. Validate voucher can still be withdrawn
        if (! $voucher->canWithdraw()) {
            throw new \RuntimeException('This voucher cannot accept further withdrawals.');
        }

        // 3. Validate contact is the original redeemer
        $originalContact = $voucher->contact;
        if (! $originalContact || $originalContact->id !== $contact->id) {
            throw new \RuntimeException('Only the original redeemer can withdraw from this voucher.');
        }

        // 4. Determine withdrawal amount
        $withdrawAmount = $this->resolveAmount($voucher, $amount);

        // 5. Determine slice number (next slice = consumed + 1)
        $sliceNumber = $voucher->getConsumedSlices() + 1;

        Log::info('[WithdrawFromVoucher] Processing withdrawal', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'amount' => $withdrawAmount,
            'slice_number' => $sliceNumber,
        ]);

        // 6b. Ensure redeemer is loaded on the model instance.
        //     Voucher has a typed property `public ?Redeemer $redeemer = null;`
        //     which shadows the getRedeemerAttribute() accessor. During initial
        //     redemption the pipeline sets this explicitly, but for subsequent
        //     withdrawals (fresh DB load) it remains null.
        $voucher->redeemer = $voucher->redeemers->first();

        // 7. Build disbursement input (handles reference, bank account, rail selection)
        $input = DisburseInputData::fromVoucher($voucher, amount: $withdrawAmount, sliceNumber: $sliceNumber);

        // 8. Validate EMI + PESONET combination
        $rail = SettlementRail::from($input->via);
        if ($rail === SettlementRail::PESONET && $this->bankRegistry->isEMI($input->bank)) {
            $bankName = $this->bankRegistry->getBankName($input->bank);
            throw new RuntimeException(
                "Cannot disburse to {$bankName} via PESONET. E-money institutions require INSTAPAY."
            );
        }

        // 9. Call payment gateway (external I/O — outside DB transaction)
        try {
            $response = $this->gateway->disburse($voucher->cash, $input);

            if ($response === false) {
                throw new RuntimeException('Gateway returned false - disbursement failed');
            }
            if (! $response instanceof DisburseResponseData) {
                throw new RuntimeException('Unexpected response type: '.gettype($response));
            }
        } catch (\Throwable $e) {
            Log::warning('[WithdrawFromVoucher] Gateway disbursement failed — recording pending', [
                'voucher' => $voucher->code,
                'slice' => $sliceNumber,
                'amount' => $withdrawAmount,
                'error' => $e->getMessage(),
            ]);

            $this->recordPendingDisbursement($voucher, $input, $e);
            event(new DisbursementFailed($voucher, $e, $contact->mobile));

            throw new RuntimeException('Disbursement failed: '.$e->getMessage());
        }

        // 10. Gateway succeeded — wallet withdrawal + metadata update in transaction
        return DB::transaction(function () use ($voucher, $contact, $withdrawAmount, $sliceNumber, $input, $response) {
            $bankName = $this->bankRegistry->getBankName($input->bank);
            $gatewayName = config('payment-gateway.default', 'netbank');
            $normalizedStatus = DisbursementStatus::fromGateway($gatewayName, $response->status)->value;
            $rail = SettlementRail::from($input->via);
            $feeAmount = $this->gateway->getRailFee($rail);
            $feeStrategy = $voucher->instructions?->cash?->fee_strategy ?? 'absorb';

            $amountCentavos = (int) ($withdrawAmount * 100);
            $withdrawal = WithdrawCash::run(
                $voucher->cash,
                $response->transaction_id,
                'Disbursed to external bank account',
                [
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->code,
                    'flow' => 'redeem',
                    'counterparty' => $bankName,
                    'reference' => $input->account_number,
                    'idempotency_key' => $response->uuid,
                    'slice_number' => $sliceNumber,
                ],
                $amountCentavos
            );

            // Update voucher disbursement metadata (overwrites previous slice's data)
            $voucher->metadata = array_merge($voucher->metadata ?? [], [
                'disbursement' => [
                    'gateway' => $gatewayName,
                    'transaction_id' => $response->transaction_id,
                    'status' => $normalizedStatus,
                    'amount' => $input->amount,
                    'currency' => 'PHP',
                    'settlement_rail' => $rail->value,
                    'fee_amount' => $feeAmount,
                    'total_cost' => ($input->amount * 100) + $feeAmount,
                    'fee_strategy' => $feeStrategy,
                    'recipient_identifier' => $input->account_number,
                    'disbursed_at' => now()->toIso8601String(),
                    'transaction_uuid' => $response->uuid,
                    'recipient_name' => $bankName,
                    'payment_method' => 'bank_transfer',
                    'cash_withdrawal_uuid' => $withdrawal->uuid,
                    'slice_number' => $sliceNumber,
                    'metadata' => [
                        'bank_code' => $input->bank,
                        'bank_name' => $bankName,
                        'bank_logo' => $this->bankRegistry->getBankLogo($input->bank),
                        'rail' => $input->via,
                        'is_emi' => $this->bankRegistry->isEMI($input->bank),
                    ],
                ],
            ]);
            $voucher->save();

            $voucher->refresh();

            $result = [
                'success' => true,
                'amount' => $withdrawAmount,
                'slice_number' => $sliceNumber,
                'remaining_slices' => $voucher->getRemainingSlices(),
                'remaining_balance' => $voucher->getRemainingBalance(),
                'bank_code' => $input->bank,
                'account_number' => $input->account_number,
            ];

            Log::info('[WithdrawFromVoucher] Withdrawal disbursed successfully', $result + [
                'voucher' => $voucher->code,
                'transaction_id' => $response->transaction_id,
            ]);

            return $result;
        });
    }

    /**
     * Record a pending disbursement on the voucher when the gateway fails.
     */
    private function recordPendingDisbursement(Voucher $voucher, DisburseInputData $input, \Throwable $e): void
    {
        $gatewayName = config('payment-gateway.default', 'netbank');
        $bankName = $this->bankRegistry->getBankName($input->bank);

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
                    'bank_logo' => $this->bankRegistry->getBankLogo($input->bank),
                    'rail' => $input->via,
                    'is_emi' => $this->bankRegistry->isEMI($input->bank),
                ],
            ],
        ]);
        $voucher->save();
    }

    /**
     * Resolve the withdrawal amount based on slice mode.
     */
    protected function resolveAmount(Voucher $voucher, ?float $amount): float
    {
        if ($voucher->getSliceMode() === 'fixed') {
            // Fixed mode: always withdraw exactly one slice amount
            return $voucher->getSliceAmount();
        }

        // Open mode: amount is required
        if ($amount === null) {
            throw new \InvalidArgumentException('Amount is required for open-mode vouchers.');
        }

        $minWithdrawal = $voucher->getMinWithdrawal();
        if ($amount < $minWithdrawal) {
            throw new \InvalidArgumentException(
                "Withdrawal amount ({$amount}) is below minimum ({$minWithdrawal})."
            );
        }

        $remainingBalance = $voucher->getRemainingBalance();
        if ($amount > $remainingBalance) {
            throw new \InvalidArgumentException(
                "Withdrawal amount ({$amount}) exceeds remaining balance ({$remainingBalance})."
            );
        }

        return $amount;
    }
}
