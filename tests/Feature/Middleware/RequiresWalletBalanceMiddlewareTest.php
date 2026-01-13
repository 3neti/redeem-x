<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class)->group('middleware');

it('allows users with positive balance to continue', function () {
    // Arrange: Create user with mobile and positive balance
    $user = User::factory()->withMobile()->create();
    $user->depositFloat(100); // Add ₱100 to wallet

    // Act: Visit protected route
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should reach the page
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('vouchers/generate/CreateV2'));
});

it('redirects users with zero balance to wallet qr', function () {
    // Arrange: User with mobile but zero balance
    $user = User::factory()->withMobile()->create();
    // Don't add any balance (default is 0)

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should redirect to wallet QR page
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)
        ->toContain('wallet/qr')
        ->toContain('reason=insufficient_balance');
    // Check for URL-encoded path in return_to parameter
    expect(urldecode($location))->toContain('/vouchers/generate');
});

it('includes flash message for insufficient balance', function () {
    // Arrange
    $user = User::factory()->withMobile()->create();

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert
    $response->assertSessionHas('flash.type', 'warning');
    $response->assertSessionHas('flash.message', 'Please add funds to your wallet to generate vouchers.');
});

it('blocks when balance is exactly zero', function () {
    // Arrange
    $user = User::factory()->withMobile()->create();
    $user->depositFloat(100);
    $user->withdrawFloat(100); // Balance = 0

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('wallet/qr');
});

it('allows even small positive balance', function () {
    // Arrange
    $user = User::factory()->withMobile()->create();
    $user->depositFloat(0.05); // Minimum voucher cost

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should allow (balance > 0)
    $response->assertStatus(200);
});

it('blocks negative balance', function () {
    // Arrange: User with negative balance (edge case)
    $user = User::factory()->withMobile()->create();
    $user->forceWithdrawFloat(10); // Force negative balance

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should redirect (balance <= 0)
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('wallet/qr');
});

it('blocks bulk generation with zero balance', function () {
    // Arrange
    $user = User::factory()->withMobile()->create();

    // Act
    $response = actingAs($user)->get('/vouchers/generate/bulk');

    // Assert
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)
        ->toContain('wallet/qr')
        ->toContain('reason=insufficient_balance');
    // Check for URL-encoded path in return_to parameter
    expect(urldecode($location))->toContain('/vouchers/generate/bulk');
});

it('preserves return url with query params', function () {
    // Arrange
    $user = User::factory()->withMobile()->create();

    // Act: Visit with query params
    $response = actingAs($user)->get('/vouchers/generate?campaign=123');

    // Assert: return_to includes full URL
    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('return_to=')
        ->toContain('campaign%3D123');
});

it('does not apply to wallet qr routes', function () {
    // Arrange: User with mobile but zero balance
    $user = User::factory()->withMobile()->create();

    // Act: Visit wallet QR page (should NOT be blocked by balance middleware)
    $response = actingAs($user)->get('/wallet/qr');

    // Assert: Should reach wallet QR page (not redirected by balance check)
    $response->assertStatus(200);
});

it('runs after mobile check in middleware chain', function () {
    // Arrange: User without mobile and zero balance
    $user = User::factory()->create();
    // No mobile, no balance

    // Act
    $response = actingAs($user)->get('/vouchers/generate');

    // Assert: Should redirect to profile (mobile check first)
    // NOT to topup (balance check)
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('profile');
});
