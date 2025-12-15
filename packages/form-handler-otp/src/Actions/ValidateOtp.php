<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Actions;

use Illuminate\Support\Facades\Cache;
use OTPHP\TOTP;

/**
 * Validate OTP Action
 * 
 * Validates a submitted OTP against the cached secret.
 */
class ValidateOtp
{
    public function __construct(
        protected string $cachePrefix = 'otp',
        protected int $period = 600,
        protected int $digits = 4,
        protected int $window = 1,
    ) {}
    
    /**
     * Validate OTP for a reference ID
     * 
     * @param string $referenceId Unique reference (e.g., voucher code, flow ID)
     * @param string $submittedCode OTP code submitted by user
     * @return bool True if valid, false otherwise
     */
    public function execute(string $referenceId, string $submittedCode): bool
    {
        $cacheKey = "{$this->cachePrefix}.{$referenceId}";
        
        // Retrieve cached secret
        $secret = Cache::get($cacheKey);
        
        if (!$secret) {
            return false;
        }
        
        // Create TOTP instance from secret
        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod($this->period);
        $totp->setDigits($this->digits);
        
        // Verify with timing window (allows ±1 period for clock skew)
        $isValid = $totp->verify($submittedCode, null, $this->window);
        
        // Clear cache on successful validation
        if ($isValid) {
            Cache::forget($cacheKey);
        }
        
        return $isValid;
    }
}
