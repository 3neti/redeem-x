<?php

declare(strict_types=1);

namespace App\Actions\Voucher;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Withdraw a slice from a divisible voucher.
 *
 * This action handles subsequent withdrawals after the initial redemption.
 * It validates state, determines amount, reuses bank account from
 * the original redeemer, withdraws from the wallet, and logs the result.
 */
class WithdrawFromVoucher
{
    use AsAction;

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

        // 6. Get bank account from original redeemer's contact
        $bankCode = $contact->bank_code;
        $accountNumber = $contact->account_number;

        Log::info('[WithdrawFromVoucher] Processing withdrawal', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'amount' => $withdrawAmount,
            'slice_number' => $sliceNumber,
            'bank_code' => $bankCode,
        ]);

        return DB::transaction(function () use ($voucher, $contact, $withdrawAmount, $sliceNumber, $bankCode, $accountNumber) {
            // 7. Withdraw from wallet
            $amountCentavos = (int) ($withdrawAmount * 100);
            WithdrawCash::run($voucher->cash, null, null, [
                'flow' => 'redeem',
                'voucher_code' => $voucher->code,
                'slice_number' => $sliceNumber,
            ], $amountCentavos);

            // 8. Refresh voucher to get updated balance/slice counts
            $voucher->refresh();

            $result = [
                'success' => true,
                'amount' => $withdrawAmount,
                'slice_number' => $sliceNumber,
                'remaining_slices' => $voucher->getRemainingSlices(),
                'remaining_balance' => $voucher->getRemainingBalance(),
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
            ];

            Log::info('[WithdrawFromVoucher] Withdrawal completed', $result + [
                'voucher' => $voucher->code,
            ]);

            return $result;
        });
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
