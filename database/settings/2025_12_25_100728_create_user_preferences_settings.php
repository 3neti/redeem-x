<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('user_preferences.notifications', [
            'email' => true,
            'sms' => false,
            'push' => false,
        ]);
        $this->migrator->add('user_preferences.timezone', 'Asia/Manila');
        $this->migrator->add('user_preferences.language', 'en');
        $this->migrator->add('user_preferences.currency', 'PHP');
        $this->migrator->add('user_preferences.date_format', 'Y-m-d');
    }
};
