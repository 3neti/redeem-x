<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Phase 0 — Characterization Tests
|--------------------------------------------------------------------------
| These tests lock down existing auth behavior BEFORE any migration changes.
| They must continue passing after every subsequent phase.
| DO NOT modify these tests during the migration.
*/

it('redirects guests to login for authenticated web routes', function () {
    $protectedRoutes = [
        '/dashboard',
        '/vouchers',
        '/settings/profile',
        '/transactions',
        '/contacts',
        '/wallet',
    ];

    foreach ($protectedRoutes as $route) {
        $this->get($route)
            ->assertRedirect('/login');
    }
});

it('allows authenticated users to access dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200);
});

it('allows authenticated users to access settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertStatus(200);
});

it('allows authenticated users to access vouchers index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/vouchers')
        ->assertStatus(200);
});

it('returns 401 for API routes without Sanctum token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

it('returns 200 for API routes with valid Sanctum token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertStatus(200);
});

it('clears session on logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect();

    $this->get('/dashboard')
        ->assertRedirect('/login');
});
