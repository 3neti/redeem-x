<?php

use App\Actions\Auth\LoginWithPassword;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Auth Routes
|
| All routes are registered unconditionally. Config checks happen at
| request time so tests can toggle auth_modes via config() in beforeEach.
|--------------------------------------------------------------------------
*/

// ---------- Login ----------
Route::get('login', function (Request $request) {
    // When local login is enabled, render the local login page
    if (config('auth_modes.enable_local_login')) {
        return Inertia::render('auth/Login', [
            'enableWorkos' => config('auth_modes.enable_workos', true),
            'enableMobileLogin' => config('auth_modes.enable_mobile_login', false),
            'enableRegistration' => config('auth_modes.enable_registration', false),
        ]);
    }

    // Otherwise fall back to WorkOS redirect
    return app(\Laravel\WorkOS\Http\Requests\AuthKitLoginRequest::class)->redirect();
})->middleware('guest')->name('login');

Route::post('login', function (Request $request) {
    abort_unless(config('auth_modes.enable_local_login'), 404);

    $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    app(LoginWithPassword::class)->handle(
        $request->input('login'),
        $request->input('password'),
    );

    $request->session()->regenerate();

    $homeRoute = app(\App\Settings\VoucherSettings::class)->default_home_route ?? 'portal';

    return redirect()->intended(route($homeRoute));
})->middleware('guest');

// ---------- Registration ----------
Route::get('register', function () {
    abort_unless(config('auth_modes.enable_registration'), 404);

    return Inertia::render('auth/Register', [
        'enableMobileLogin' => config('auth_modes.enable_mobile_login', false),
    ]);
})->middleware('guest')->name('register');

Route::post('register', function (Request $request) {
    abort_unless(config('auth_modes.enable_registration'), 404);

    app(RegisterUser::class)->handle($request->all());

    $request->session()->regenerate();

    $homeRoute = app(\App\Settings\VoucherSettings::class)->default_home_route ?? 'portal';

    return redirect()->intended(route($homeRoute));
})->middleware('guest');

// ---------- Password Reset ----------
Route::get('forgot-password', function () {
    abort_unless(config('auth_modes.enable_local_login'), 404);

    return Inertia::render('auth/ForgotPassword');
})->middleware('guest')->name('password.request');

Route::post('forgot-password', function (Request $request) {
    abort_unless(config('auth_modes.enable_local_login'), 404);

    $request->validate(['email' => ['required', 'email']]);

    $resetAction = app(ResetPassword::class);
    $status = $resetAction->sendResetLink($request->input('email'));

    if ($status === Password::RESET_LINK_SENT) {
        return back()->with('status', __($status));
    }

    return back()->withErrors(['email' => __($status)]);
})->middleware('guest')->name('password.email');

Route::get('reset-password/{token}', function (string $token, Request $request) {
    abort_unless(config('auth_modes.enable_local_login'), 404);

    return Inertia::render('auth/ResetPassword', [
        'token' => $token,
        'email' => $request->query('email', ''),
    ]);
})->middleware('guest')->name('password.reset');

Route::post('reset-password', function (Request $request) {
    abort_unless(config('auth_modes.enable_local_login'), 404);

    $request->validate([
        'token' => ['required'],
        'email' => ['required', 'email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $resetAction = app(ResetPassword::class);
    $status = $resetAction->resetPassword($request->only(
        'email', 'password', 'password_confirmation', 'token'
    ));

    if ($status === Password::PASSWORD_RESET) {
        return redirect()->route('login')->with('status', __($status));
    }

    return back()->withErrors(['email' => __($status)]);
})->middleware('guest')->name('password.update');

// ---------- WorkOS Callback ----------
Route::get('authenticate', function (\Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest $request) {
    abort_unless(config('auth_modes.enable_workos', true), 404);

    $homeRoute = app(\App\Settings\VoucherSettings::class)->default_home_route ?? 'portal';

    return tap(to_route($homeRoute), fn () => $request->authenticate());
})->middleware(['guest']);

// ---------- Logout (universal) ----------
Route::post('logout', function (Request $request) {
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->middleware(['auth'])->name('logout');
