<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IP Whitelist Configuration
    |--------------------------------------------------------------------------
    |
    | Control access to the API based on IP addresses. Users can configure
    | their own whitelist of allowed IPs (including CIDR notation).
    |
    */
    'ip_whitelist' => [
        'enabled' => env('IP_WHITELIST_ENABLED', false),
        'bypass_local' => env('IP_WHITELIST_BYPASS_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Signature Configuration
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 signature verification for critical endpoints.
    | Prevents request tampering and replay attacks.
    |
    */
    'signature' => [
        'enabled' => env('API_SIGNATURE_REQUIRED', false),
        'tolerance_seconds' => env('API_SIGNATURE_TOLERANCE', 300), // 5 minutes
        'header' => 'X-Signature',
        'timestamp_header' => 'X-Timestamp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Tiered rate limits with burst allowance for different user tiers.
    |
    */
    'rate_limiting' => [
        'enabled' => env('ADVANCED_RATE_LIMITING', true),
        
        'tiers' => [
            'basic' => [
                'requests_per_minute' => 60,
                'burst_allowance' => 10,
                'burst_window_seconds' => 10,
            ],
            'premium' => [
                'requests_per_minute' => 300,
                'burst_allowance' => 50,
                'burst_window_seconds' => 10,
            ],
            'enterprise' => [
                'requests_per_minute' => 1000,
                'burst_allowance' => 200,
                'burst_window_seconds' => 30,
            ],
        ],
        
        // Per-endpoint overrides
        'endpoint_limits' => [
            'POST /api/v1/vouchers' => 10,
            'POST /api/v1/vouchers/*/redeem' => 30,
            'GET /api/v1/reports/*' => 20,
        ],
    ],
];
