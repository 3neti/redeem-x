<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Rules\UniqueMobile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterUser
{
    use AsAction;

    public function handle(array $data): User
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20', new UniqueMobile],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        // Normalize mobile to national format (09173011987) for consistent storage
        $mobile = $validated['mobile'];
        try {
            $mobile = phone($mobile, 'PH')->formatForMobileDialingInCountry('PH');
        } catch (\Throwable) {
            // Keep raw value if parsing fails
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $mobile,
            'password' => $validated['password'],
            'auth_source' => 'local',
            'status' => 'active',
            'avatar' => '',
        ]);

        Auth::login($user);

        return $user;
    }
}
