<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security.ip_whitelist_enabled', false);
        $this->migrator->add('security.ip_whitelist', []);
        $this->migrator->add('security.signature_enabled', false);
        $this->migrator->add('security.signature_secret', null);
        $this->migrator->add('security.rate_limit_tier', 'basic');
    }
};
