<?php

declare(strict_types=1);

use App\Services\AuthModeResolver;

/*
|--------------------------------------------------------------------------
| Phase 2 TDD — Auth Mode Resolver Tests
|--------------------------------------------------------------------------
*/

it('returns workos as default mode', function () {
    config(['auth_modes.default_auth_mode' => 'workos']);

    $resolver = new AuthModeResolver;
    expect($resolver->defaultMode())->toBe('workos');
});

it('returns local as default mode when configured', function () {
    config(['auth_modes.default_auth_mode' => 'local']);

    $resolver = new AuthModeResolver;
    expect($resolver->defaultMode())->toBe('local');
});

it('reports local login available when enabled', function () {
    config(['auth_modes.enable_local_login' => true]);

    $resolver = new AuthModeResolver;
    expect($resolver->isLocalLoginEnabled())->toBeTrue();
});

it('reports local login unavailable when disabled', function () {
    config(['auth_modes.enable_local_login' => false]);

    $resolver = new AuthModeResolver;
    expect($resolver->isLocalLoginEnabled())->toBeFalse();
});

it('reports registration available when enabled', function () {
    config(['auth_modes.enable_registration' => true]);

    $resolver = new AuthModeResolver;
    expect($resolver->isRegistrationEnabled())->toBeTrue();
});

it('config flags are independent', function () {
    config([
        'auth_modes.enable_local_login' => true,
        'auth_modes.enable_registration' => false,
    ]);

    $resolver = new AuthModeResolver;
    expect($resolver->isLocalLoginEnabled())->toBeTrue();
    expect($resolver->isRegistrationEnabled())->toBeFalse();
});
