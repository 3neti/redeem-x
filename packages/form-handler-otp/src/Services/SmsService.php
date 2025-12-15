<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Services;

use LBHurtado\SMS\Facades\SMS;

/**
 * SMS Service
 * 
 * Handles SMS sending for OTP delivery.
 */
class SmsService
{
    public function __construct(
        protected string $provider = 'engagespark',
        protected ?string $senderId = null,
    ) {
        $this->senderId = $senderId ?? config('otp-handler.engagespark.sender_id', 'cashless');
    }
    
    /**
     * Send OTP via SMS
     * 
     * @param string $mobile Mobile number
     * @param string $otp OTP code
     * @param string $appName Application name
     * @return void
     */
    public function sendOtp(string $mobile, string $otp, string $appName): void
    {
        $message = "{$otp} is your authentication code. Do not share.\n- {$appName}";
        
        SMS::channel($this->provider)
            ->from($this->senderId)
            ->to($mobile)
            ->content($message)
            ->send();
    }
}
