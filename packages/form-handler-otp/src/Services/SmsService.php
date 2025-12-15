<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Services;

use Illuminate\Notifications\AnonymousNotifiable;
use LBHurtado\FormHandlerOtp\Notifications\SendOtpNotification;

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
        // Get sender ID from provider-specific config
        $this->senderId = $senderId ?? config("otp-handler.{$provider}.sender_id", 'cashless');
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
        // Use Laravel Notifications with EngageSpark channel (like x-change)
        (new AnonymousNotifiable)->notify(
            new SendOtpNotification($mobile, $otp, $appName)
        );
    }
}
