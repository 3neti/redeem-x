<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 3 TDD — Logout Tests
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->withoutVite();
});

it('clears session and redirects on logout', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

it('redirects to login when accessing protected route after logout', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)->post('/logout');

    $this->get('/dashboard')->assertRedirect('/login');
});
