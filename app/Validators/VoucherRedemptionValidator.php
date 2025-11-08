<?php

declare(strict_types=1);

namespace App\Validators;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Custom validator for voucher redemption business rules.
 *
 * Validates:
 * - Mobile number matches voucher's expected recipient (if configured)
 * - Secret matches voucher's hashed secret (if configured)
 */
class VoucherRedemptionValidator
{
    public function __construct(
        protected Voucher $voucher,
        protected MessageBag $errors = new MessageBag()
    ) {}

    /**
     * Validate that the voucher can be redeemed (not expired, not already redeemed, etc.).
     *
     * @return bool  True if voucher is valid for redemption
     */
    public function validateVoucherStatus(): bool
    {
        // Check if already redeemed
        if ($this->voucher->isRedeemed()) {
            $this->errors->add('code', 'This voucher has already been redeemed.');
            
            Log::warning('[VoucherRedemptionValidator] Voucher already redeemed.', [
                'voucher_code' => $this->voucher->code,
            ]);
            
            return false;
        }
        
        // Check if expired
        if ($this->voucher->isExpired()) {
            $this->errors->add('code', 'This voucher has expired.');
            
            Log::warning('[VoucherRedemptionValidator] Voucher expired.', [
                'voucher_code' => $this->voucher->code,
                'expires_at' => $this->voucher->expires_at,
            ]);
            
            return false;
        }
        
        // Check if not yet active
        if ($this->voucher->starts_at && $this->voucher->starts_at->isFuture()) {
            $this->errors->add('code', 'This voucher is not yet active.');
            
            Log::warning('[VoucherRedemptionValidator] Voucher not yet active.', [
                'voucher_code' => $this->voucher->code,
                'starts_at' => $this->voucher->starts_at,
            ]);
            
            return false;
        }
        
        return true;
    }

    /**
     * Validate that the provided mobile matches the voucher's expected recipient.
     *
     * @param  string|null  $mobile  Mobile number to validate
     * @return bool  True if valid or no validation required
     */
    public function validateMobile(?string $mobile): bool
    {
        $expected = $this->voucher->instructions->cash->validation->mobile ?? null;

        // No validation required if mobile not configured in voucher
        if (empty($expected)) {
            Log::info('[VoucherRedemptionValidator] No mobile validation required.', [
                'voucher_code' => $this->voucher->code,
            ]);

            return true;
        }

        try {
            $expectedPhone = new PhoneNumber($expected, 'PH');
            $actualPhone = new PhoneNumber($mobile, 'PH');

            if (! $expectedPhone->equals($actualPhone)) {
                $this->errors->add('mobile', 'This voucher is not for the provided mobile number.');

                Log::warning('[VoucherRedemptionValidator] Mobile number mismatch.', [
                    'voucher_code' => $this->voucher->code,
                    'expected' => (string) $expectedPhone,
                    'actual' => (string) $actualPhone,
                ]);

                return false;
            }

            Log::info('[VoucherRedemptionValidator] Mobile number matched.', [
                'voucher_code' => $this->voucher->code,
                'mobile' => (string) $actualPhone,
            ]);

            return true;
        } catch (Throwable $e) {
            $this->errors->add('mobile', 'Error validating mobile number. Please check the format.');

            Log::error('[VoucherRedemptionValidator] Exception during mobile validation.', [
                'voucher_code' => $this->voucher->code,
                'error' => $e->getMessage(),
                'mobile' => $mobile,
            ]);

            return false;
        }
    }

    /**
     * Validate that the provided secret matches the voucher's secret.
     *
     * Checks: cash entity (hashed), instructions, or metadata (plain text).
     *
     * @param  string|null  $secret  Secret to validate
     * @return bool  True if valid or no secret required
     */
    public function validateSecret(?string $secret): bool
    {
        // Check if voucher has a secret configured (cash entity, instructions, or metadata)
        $hasCashSecret = $this->voucher->cash?->secret !== null;
        $instructionsSecret = $this->voucher->instructions->cash->validation->secret ?? null;
        $metadataSecret = $this->voucher->metadata['secret'] ?? null;
        
        $hasAnySecret = $hasCashSecret || $instructionsSecret || $metadataSecret;

        // No validation required if secret not configured in voucher
        if (!$hasAnySecret) {
            Log::info('[VoucherRedemptionValidator] No secret configured; skipping validation.', [
                'voucher_code' => $this->voucher->code,
            ]);

            return true;
        }

        // Secret is required but not provided
        if (empty($secret)) {
            $this->errors->add('secret', 'Secret is required for this voucher.');

            Log::warning('[VoucherRedemptionValidator] Secret required but not provided.', [
                'voucher_code' => $this->voucher->code,
            ]);

            return false;
        }

        // Validate against cash entity using its verifySecret method
        if ($hasCashSecret) {
            $isMatch = $this->voucher->cash->verifySecret($secret);
        }
        // Validate against instructions secret (plain text)
        elseif ($instructionsSecret) {
            $isMatch = $secret === $instructionsSecret;
        }
        // Validate against metadata secret (plain text fallback)
        else {
            $isMatch = $secret === $metadataSecret;
        }

        if (! $isMatch) {
            $this->errors->add('secret', 'Invalid secret provided.');

            Log::warning('[VoucherRedemptionValidator] Secret validation failed.', [
                'voucher_code' => $this->voucher->code,
                'secret_provided' => ! empty($secret),
            ]);

            return false;
        }

        Log::info('[VoucherRedemptionValidator] Secret validated successfully.', [
            'voucher_code' => $this->voucher->code,
        ]);

        return true;
    }

    /**
     * Get all validation errors.
     *
     * @return MessageBag
     */
    public function errors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * Check if there are any validation errors.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return $this->errors->isNotEmpty();
    }

    /**
     * Check if validation passed.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return $this->errors->isEmpty();
    }
}
