<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class WalletConfigSettings extends Settings
{
    public string $default_settlement_rail;
    public string $default_fee_strategy;
    public bool $auto_disburse;
    public int $low_balance_threshold;
    public bool $low_balance_notifications;

    public static function group(): string
    {
        return 'wallet_config';
    }
}
