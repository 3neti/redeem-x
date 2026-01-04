<?php

declare(strict_types=1);

namespace App\Actions\Voucher;

use App\Exceptions\VoucherNotProcessedException;
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
    
    private const DEBUG = false;

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

        // Check if voucher has been processed (cash entity created)
        if (!$voucher->processed) {
            Log::warning('[ProcessRedemption] Voucher not yet processed', [
                'voucher' => $voucher->code,
                'created_at' => $voucher->created_at,
                'processed_on' => $voucher->processed_on,
            ]);
            
            throw new VoucherNotProcessedException(
                'This voucher is still being prepared. Please wait a moment and try again.'
            );
        }

        return DB::transaction(function () use ($voucher, $phoneNumber, $inputs, $bankAccount) {
            // Track redemption submission timing
            $voucher->trackRedemptionSubmit();
            
            // Step 1: Get or create contact (needed for KYC validation)
            $contact = Contact::fromPhoneNumber($phoneNumber);
            
            // Step 2: Validate KYC if required
            $this->validateKYC($voucher, $contact);
            
            // Note: Time and Location validation are handled by the Unified Validation Gateway:
            // - TimeLimitSpecification (duration limit validation)
            // - TimeWindowSpecification (time-of-day window validation)
            // - LocationSpecification (GPS proximity validation)

            // Step 3: Prepare metadata for redemption
            $meta = $this->prepareMetadata($inputs, $bankAccount);

            // Step 6: Mark voucher as redeemed (uses package action)
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
     * Validate KYC if KYC input field is required.
     *
     * @param  Voucher  $voucher
     * @param  Contact  $contact
     * @return void
     *
     * @throws \RuntimeException  If KYC is required but not approved
     */
    protected function validateKYC(Voucher $voucher, Contact $contact): void
    {
        // Check if KYC is required
        $kycRequired = in_array('kyc', $voucher->instructions->inputs->fields ?? []);
        
        if (!$kycRequired) {
            if (self::DEBUG) {
                Log::debug('[ProcessRedemption] KYC not required', [
                    'voucher' => $voucher->code,
                ]);
            }
            return;
        }
        
        // Validate contact has approved KYC
        if (!$contact->isKycApproved()) {
            Log::warning('[ProcessRedemption] KYC validation failed', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
                'kyc_status' => $contact->kyc_status,
            ]);
            
            throw new \RuntimeException(
                'Identity verification required. Please complete KYC before redeeming.'
            );
        }
        
        Log::info('[ProcessRedemption] KYC validation passed', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'kyc_status' => $contact->kyc_status,
        ]);
    }

    // Time validation removed - handled by TimeLimitSpecification and TimeWindowSpecification in Unified Validation Gateway
    // Location validation removed - handled by LocationSpecification in Unified Validation Gateway

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
