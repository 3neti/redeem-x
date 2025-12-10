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
            
            // Step 3: Validate location if required
            $this->validateLocation($voucher, $inputs);

            // Step 4: Validate time if required
            $this->validateTime($voucher);

            // Step 5: Prepare metadata for redemption
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

    /**
     * Validate time if time validation is configured.
     *
     * @param  Voucher  $voucher
     * @return void
     *
     * @throws \RuntimeException  If validation fails
     */
    protected function validateTime(Voucher $voucher): void
    {
        // Check if time validation is configured
        $timeValidation = $voucher->instructions->validation?->time;
        
        if (!$timeValidation) {
            if (self::DEBUG) {
                Log::debug('[ProcessRedemption] No time validation configured', [
                    'voucher' => $voucher->code,
                ]);
            }
            return;
        }

        $withinWindow = true;
        $withinDuration = true;
        $durationSeconds = 0;
        $windowError = null;
        $durationError = null;

        // Validate time window if configured
        if ($timeValidation->hasWindowValidation()) {
            $withinWindow = $timeValidation->isWithinWindow();
            
            if (!$withinWindow) {
                $window = $timeValidation->window;
                $windowError = sprintf(
                    'Redemption is only allowed between %s and %s (%s).',
                    $window->start_time,
                    $window->end_time,
                    $window->timezone
                );
                
                Log::warning('[ProcessRedemption] Time window validation failed', [
                    'voucher' => $voucher->code,
                    'start_time' => $window->start_time,
                    'end_time' => $window->end_time,
                    'timezone' => $window->timezone,
                ]);
            }
        }

        // Validate duration limit if configured
        if ($timeValidation->hasDurationLimit()) {
            $duration = $voucher->getRedemptionDuration();
            
            if ($duration !== null) {
                $durationSeconds = $duration;
                $exceedsLimit = $timeValidation->exceedsDurationLimit($duration);
                
                if ($exceedsLimit) {
                    $withinDuration = false;
                    $limitMinutes = $timeValidation->limit_minutes;
                    $actualMinutes = round($duration / 60, 1);
                    
                    $durationError = sprintf(
                        'Redemption took too long. Maximum allowed: %d minutes. Actual: %.1f minutes.',
                        $limitMinutes,
                        $actualMinutes
                    );
                    
                    Log::warning('[ProcessRedemption] Duration limit exceeded', [
                        'voucher' => $voucher->code,
                        'duration_seconds' => $duration,
                        'limit_minutes' => $limitMinutes,
                    ]);
                }
            }
        }

        // Determine if should block
        $shouldBlock = !$withinWindow || !$withinDuration;

        // Store time validation results
        if ($timeValidation->hasWindowValidation() || $timeValidation->hasDurationLimit()) {
            $timeResult = \LBHurtado\Voucher\Data\TimeValidationResultData::from([
                'within_window' => $withinWindow,
                'within_duration' => $withinDuration,
                'duration_seconds' => $durationSeconds,
                'should_block' => $shouldBlock,
            ]);
            
            $voucher->storeValidationResults(time: $timeResult);
            $voucher->save();

            Log::info('[ProcessRedemption] Time validation result', [
                'voucher' => $voucher->code,
                'within_window' => $withinWindow,
                'within_duration' => $withinDuration,
                'should_block' => $shouldBlock,
            ]);
        }

        // Block redemption if any validation failed
        if (!$withinWindow) {
            throw new \RuntimeException($windowError);
        }
        
        if (!$withinDuration) {
            throw new \RuntimeException($durationError);
        }
    }

    /**
     * Validate location if location validation is configured.
     *
     * @param  Voucher  $voucher
     * @param  array  $inputs
     * @return void
     *
     * @throws \RuntimeException  If validation fails and should block
     */
    protected function validateLocation(Voucher $voucher, array $inputs): void
    {
        // Check if location validation is configured
        $locationValidation = $voucher->instructions->validation?->location;
        
        if (!$locationValidation) {
            if (self::DEBUG) {
                Log::debug('[ProcessRedemption] No location validation configured', [
                    'voucher' => $voucher->code,
                ]);
            }
            return;
        }

        // Check if location data was provided
        if (!isset($inputs['location']) || !is_array($inputs['location'])) {
            Log::warning('[ProcessRedemption] Location validation required but no location data provided', [
                'voucher' => $voucher->code,
            ]);
            throw new \RuntimeException('Location data is required for this voucher.');
        }

        $location = $inputs['location'];
        
        if (!isset($location['latitude']) || !isset($location['longitude'])) {
            Log::warning('[ProcessRedemption] Invalid location data format', [
                'voucher' => $voucher->code,
                'location' => $location,
            ]);
            throw new \RuntimeException('Invalid location data format.');
        }

        // Validate location
        $locationResult = $locationValidation->validateLocation(
            (float) $location['latitude'],
            (float) $location['longitude']
        );

        Log::info('[ProcessRedemption] Location validation result', [
            'voucher' => $voucher->code,
            'validated' => $locationResult->validated,
            'distance_meters' => $locationResult->distance_meters,
            'should_block' => $locationResult->should_block,
        ]);

        // Store validation results on voucher
        $voucher->storeValidationResults(location: $locationResult);
        $voucher->save();

        // Block redemption if validation failed and should block
        if ($locationResult->should_block) {
            $distanceKm = $locationResult->distance_meters / 1000;
            $radiusKm = $locationValidation->radius_meters / 1000;
            
            Log::warning('[ProcessRedemption] Location validation failed - blocking redemption', [
                'voucher' => $voucher->code,
                'distance_meters' => $locationResult->distance_meters,
                'radius_meters' => $locationValidation->radius_meters,
            ]);
            
            throw new \RuntimeException(
                sprintf(
                    'You must be within %.1f km of the designated location. You are %.1f km away.',
                    $radiusKm,
                    $distanceKm
                )
            );
        }
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
