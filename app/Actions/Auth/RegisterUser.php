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

        // Mobile is normalized to E.164 by User::setMobileAttribute mutator
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'],
            'password' => $validated['password'],
            'auth_source' => 'local',
            'status' => 'active',
            'avatar' => '',
        ]);

        Auth::login($user);

        return $user;
    }
}
