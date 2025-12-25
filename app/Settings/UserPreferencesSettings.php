<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class UserPreferencesSettings extends Settings
{
    public array $notifications;
    public string $timezone;
    public string $language;
    public string $currency;
    public string $date_format;

    public static function group(): string
    {
        return 'user_preferences';
    }
}
