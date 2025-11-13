<?php

declare(strict_types=1);

namespace App\Actions\Voucher;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Process voucher redemption.
 *
 * This action marks the voucher as redeemed, which triggers the
 * post-redemption pipeline (configured in config/voucher-pipeline.php):
 * 1. ValidateRedeemerAndCash
 * 2. PersistInputs
 * 3. DisburseCash (if DISBURSE_DISABLE=false)
 * 4. SendFeedbacks
 *
 * All wrapped in a database transaction for safety.
 */
class ProcessRedemption
{
    use AsAction;

    /**
     * Process voucher redemption.
     *
     * @param  Voucher  $voucher  The voucher to redeem
     * @param  PhoneNumber  $phoneNumber  Redeemer's phone number
     * @param  array  $inputs  Collected inputs from redemption flow
     * @param  array  $bankAccount  Bank account details ['bank_code' => string, 'account_number' => string]
     * @return bool  True if successful
     *
     * @throws \Throwable
     */
    public function handle(
        Voucher $voucher,
        PhoneNumber $phoneNumber,
        array $inputs = [],
        array $bankAccount = []
    ): bool {
        Log::info('[ProcessRedemption] Starting redemption', [
            'voucher' => $voucher->code,
            'mobile' => $phoneNumber->formatE164(),
            'inputs_count' => count($inputs),
            'has_bank_account' => ! empty($bankAccount),
        ]);

        return DB::transaction(function () use ($voucher, $phoneNumber, $inputs, $bankAccount) {
            // Step 1: Get or create contact
            $contact = Contact::fromPhoneNumber($phoneNumber);

            // Step 2: Prepare metadata for redemption
            $meta = $this->prepareMetadata($inputs, $bankAccount);

            // Step 3: Mark voucher as redeemed (uses package action)
            $redeemed = RedeemVoucher::run($contact, $voucher->code, $meta);

            if (! $redeemed) {
                Log::error('[ProcessRedemption] Failed to redeem voucher', [
                    'voucher' => $voucher->code,
                    'contact_id' => $contact->id,
                ]);

                throw new \RuntimeException('Failed to redeem voucher.');
            }

            Log::info('[ProcessRedemption] Voucher redeemed successfully', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
            ]);

            // Note: The post-redemption pipeline now handles:
            // - PersistInputs: Saves inputs to voucher
            // - DisburseCash: Disburses payment (if DISBURSE_DISABLE=false)
            // - SendFeedbacks: Sends email/SMS/webhook notifications
            // These are triggered automatically by the VoucherObserver after redemption.

            return true;
        });
    }

    /**
     * Prepare metadata for redemption.
     *
     * @param  array  $inputs  User inputs
     * @param  array  $bankAccount  Bank account details
     * @return array
     */
    protected function prepareMetadata(array $inputs, array $bankAccount): array
    {
        $meta = [];

        if (! empty($inputs)) {
            $meta['inputs'] = $inputs;
        }

        if (! empty($bankAccount['bank_code']) && ! empty($bankAccount['account_number'])) {
            $meta['bank_account'] = "{$bankAccount['bank_code']}:{$bankAccount['account_number']}";
        }

        return $meta;
    }
}
