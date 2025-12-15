<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Label
    |--------------------------------------------------------------------------
    |
    | The application name shown in SMS messages.
    |
    */
    'label' => env('OTP_LABEL', config('app.name', 'Your App')),
    
    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    |
    | The SMS provider to use for sending OTP codes.
    | Supported: 'engagespark', 'nexmo'
    |
    */
    'sms_provider' => env('OTP_SMS_PROVIDER', 'engagespark'),
    
    /*
    |--------------------------------------------------------------------------
    | EngageSpark Configuration
    |--------------------------------------------------------------------------
    |
    | EngageSpark API credentials for SMS delivery.
    |
    */
    'engagespark' => [
        'api_key' => env('ENGAGESPARK_API_KEY', config('sms.engagespark.api_key')),
        'org_id' => env('ENGAGESPARK_ORG_ID', config('sms.engagespark.org_id')),
        'sender_id' => env('ENGAGESPARK_SENDER_ID', config('sms.engagespark.sender_id', 'cashless')),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OTP Period (TTL)
    |--------------------------------------------------------------------------
    |
    | How long the OTP is valid (in seconds).
    | Default: 600 seconds (10 minutes)
    |
    */
    'period' => env('OTP_PERIOD', 600),
    
    /*
    |--------------------------------------------------------------------------
    | OTP Digits
    |--------------------------------------------------------------------------
    |
    | Number of digits in the OTP code.
    | Default: 4 digits
    |
    */
    'digits' => env('OTP_DIGITS', 4),
    
    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for OTP cache keys to avoid collisions.
    |
    */
    'cache_prefix' => 'otp',
    
    /*
    |--------------------------------------------------------------------------
    | Max Resends
    |--------------------------------------------------------------------------
    |
    | Maximum number of times a user can resend OTP.
    | Default: 3 attempts
    |
    */
    'max_resends' => env('OTP_MAX_RESENDS', 3),
    
    /*
    |--------------------------------------------------------------------------
    | Resend Cooldown
    |--------------------------------------------------------------------------
    |
    | Cooldown period between resend requests (in seconds).
    | Default: 30 seconds
    |
    */
    'resend_cooldown' => env('OTP_RESEND_COOLDOWN', 30),
];
