<?php

declare(strict_types=1);

namespace App\Services;

class AuthModeResolver
{
    public function defaultMode(): string
    {
        return config('auth_modes.default_auth_mode', 'workos');
    }

    public function isWorkOSEnabled(): bool
    {
        return (bool) config('auth_modes.enable_workos', true);
    }

    public function isLocalLoginEnabled(): bool
    {
        return (bool) config('auth_modes.enable_local_login', false);
    }

    public function isRegistrationEnabled(): bool
    {
        return (bool) config('auth_modes.enable_registration', false);
    }

    public function isPasswordLoginEnabled(): bool
    {
        return (bool) config('auth_modes.enable_password_login', false);
    }

    public function isMobileLoginEnabled(): bool
    {
        return (bool) config('auth_modes.enable_mobile_login', false);
    }

    /**
     * Get all available auth methods for the login page.
     */
    public function availableMethods(): array
    {
        $methods = [];

        if ($this->isWorkOSEnabled()) {
            $methods[] = 'workos';
        }

        if ($this->isPasswordLoginEnabled()) {
            $methods[] = 'password';
        }

        if ($this->isMobileLoginEnabled()) {
            $methods[] = 'mobile';
        }

        return $methods;
    }
}
