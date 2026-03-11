<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class LoginWithPassword
{
    use AsAction;

    /**
     * Attempt login with email or mobile + password.
     *
     * The 'login' field accepts either an email address or a mobile number.
     */
    public function handle(string $login, string $password): User
    {
        $user = $this->findUser($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => [__('auth.failed')],
            ]);
        }

        Auth::login($user, remember: true);

        $user->update(['last_login_at' => now()]);

        return $user;
    }

    protected function findUser(string $login): ?User
    {
        // Email login
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $login)->first();
        }

        // Mobile login — normalize to E.164 (canonical storage format)
        // Auto-detect country from input, fall back to PH for national format
        try {
            $e164 = phone($login)->formatE164();
        } catch (\Throwable) {
            try {
                $e164 = phone($login, 'PH')->formatE164();
            } catch (\Throwable) {
                return User::where('mobile', $login)->first();
            }
        }

        return User::where('mobile', $e164)->first();
    }
}
