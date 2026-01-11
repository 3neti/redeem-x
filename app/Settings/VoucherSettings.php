<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class VoucherSettings extends Settings
{
    public int $default_amount;
    public ?int $default_expiry_days;
    public ?string $default_rider_url;
    public ?string $default_success_message;
    public string $default_redemption_endpoint;
    public string $default_settlement_endpoint;
    public string $default_portal_endpoint;
    public string $default_home_route;

    public static function group(): string
    {
        return 'voucher';
    }
}
