<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    // IP Whitelisting
    public bool $ip_whitelist_enabled;

    public array $ip_whitelist;

    // Request Signing (HMAC-SHA256)
    public bool $signature_enabled;

    public ?string $signature_secret;

    // Rate Limiting
    public string $rate_limit_tier;

    public static function group(): string
    {
        return 'security';
    }
}
