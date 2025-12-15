<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Actions;

use Illuminate\Support\Facades\Cache;
use OTPHP\TOTP;

/**
 * Generate OTP Action
 * 
 * Generates a time-based one-time password and caches it for validation.
 */
class GenerateOtp
{
    public function __construct(
        protected string $cachePrefix = 'otp',
        protected int $period = 600,
        protected int $digits = 4,
    ) {}
    
    /**
     * Generate OTP for a reference ID
     * 
     * @param string $referenceId Unique reference (e.g., voucher code, flow ID)
     * @param string $mobile Mobile number (for label)
     * @return array ['code' => string, 'expires_at' => string]
     */
    public function execute(string $referenceId, string $mobile): array
    {
        // Create TOTP instance using generate method
        $totp = TOTP::generate();
        
        $totp->setLabel($mobile);
        $totp->setPeriod($this->period);
        $totp->setDigits($this->digits);
        
        // Generate current OTP
        $code = $totp->now();
        
        // Cache the TOTP secret for validation
        $cacheKey = "{$this->cachePrefix}.{$referenceId}";
        $ttl = now()->addSeconds($this->period);
        
        Cache::put($cacheKey, $totp->getSecret(), $ttl);
        
        return [
            'code' => $code,
            'expires_at' => $ttl->toIso8601String(),
        ];
    }
}
