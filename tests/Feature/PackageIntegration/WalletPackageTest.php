<?php

use App\Models\User;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('wallet package is loaded and autoloaded', function () {
    expect(trait_exists(\LBHurtado\Wallet\Traits\HasPlatformWallets::class))->toBeTrue();
});

test('user has wallet traits', function () {
    $user = User::factory()->create();
    
    expect(method_exists($user, 'wallet'))->toBeTrue()
        ->and(method_exists($user, 'wallets'))->toBeTrue()
        ->and(method_exists($user, 'getWalletByType'))->toBeTrue()
        ->and(method_exists($user, 'getOrCreateWalletByType'))->toBeTrue();
});

test('user can get default wallet', function () {
    $user = User::factory()->create();
    
    $wallet = $user->wallet;
    
    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->holder_id)->toBe($user->id)
        ->and($wallet->holder_type)->toBe(User::class);
});

test('user can have multiple wallets', function () {
    $user = User::factory()->create();
    
    // Get default wallet
    $defaultWallet = $user->wallet;
    
    // Create a typed wallet (will create new if default has no slug)
    $bonusWallet = $user->getOrCreateWalletByType('reward');
    $savingsWallet = $user->getOrCreateWalletByType('savings');
    
    // Verify we have multiple distinct wallets
    expect($user->wallets()->count())->toBeGreaterThanOrEqual(2)
        ->and($bonusWallet->slug)->toBe('reward')
        ->and($savingsWallet->slug)->toBe('savings');
});

test('user can get or create wallet by type', function () {
    $user = User::factory()->create();
    
    $wallet = $user->getOrCreateWalletByType('bonus');
    
    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->slug)->toBe('bonus')
        ->and($wallet->name)->toBe('Bonus Wallet');
    
    // Should return same wallet on second call
    $sameWallet = $user->getOrCreateWalletByType('bonus');
    expect($sameWallet->id)->toBe($wallet->id);
});

test('user can deposit to wallet', function () {
    $user = User::factory()->create();
    $wallet = $user->wallet;
    
    $transaction = $wallet->deposit(100);
    $wallet->refresh();
    
    expect((int)$wallet->balance)->toBe(100)
        ->and((int)$transaction->amount)->toBe(100);
});

test('user can withdraw from wallet', function () {
    $user = User::factory()->create();
    $wallet = $user->wallet;
    
    $wallet->deposit(200);
    $transaction = $wallet->withdraw(50);
    $wallet->refresh();
    
    expect((int)$wallet->balance)->toBe(150)
        ->and(abs((int)$transaction->amount))->toBe(50); // Withdrawals are negative
});

test('user can check wallet balance', function () {
    $user = User::factory()->create();
    $wallet = $user->wallet;
    
    $wallet->deposit(500);
    $wallet->refresh();
    
    expect((int)$wallet->balance)->toBe(500)
        ->and($wallet->balanceInt)->toBe(500);
});

test('wallet tables exist in database', function () {
    expect(\Schema::hasTable('wallets'))->toBeTrue()
        ->and(\Schema::hasTable('transactions'))->toBeTrue()
        ->and(\Schema::hasTable('transfers'))->toBeTrue();
});
