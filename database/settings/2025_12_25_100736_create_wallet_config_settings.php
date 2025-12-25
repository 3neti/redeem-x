<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('wallet_config.default_settlement_rail', 'auto');
        $this->migrator->add('wallet_config.default_fee_strategy', 'absorb');
        $this->migrator->add('wallet_config.auto_disburse', true);
        $this->migrator->add('wallet_config.low_balance_threshold', 1000);
        $this->migrator->add('wallet_config.low_balance_notifications', true);
    }
};
