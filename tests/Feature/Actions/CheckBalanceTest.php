<?php

use App\Actions\Wallet\CheckBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Wallet\Enums\WalletType;

uses(RefreshDatabase::class);

test('returns zero balance for user without wallet', function () {
    $user = User::factory()->create();
    
    // User has no wallet yet
    expect($user->getWallet(WalletType::default()->value))->toBeNull();
    
    // CheckBalance should create wallet and return zero balance
    $balance = CheckBalance::run($user);
    
    expect($balance->getAmount()->toFloat())->toBe(0.0);
    expect($balance->getCurrency()->getCurrencyCode())->toBe('PHP');
    
    // Verify wallet was created
    expect($user->getWallet(WalletType::default()->value))->not->toBeNull();
});

test('returns correct balance for user with existing wallet', function () {
    $user = User::factory()->create();
    
    // Deposit some money (creates wallet) - deposit() uses cents
    $user->depositFloat(1000);
    
    $balance = CheckBalance::run($user);
    
    expect($balance->getAmount()->toFloat())->toBe(1000.0);
    expect($balance->getCurrency()->getCurrencyCode())->toBe('PHP');
});

test('works with default wallet type explicitly', function () {
    $user = User::factory()->create();
    
    // Just verify it doesn't crash when explicitly passing default type
    $balance = CheckBalance::run($user, WalletType::default());
    
    expect($balance->getAmount()->toFloat())->toBe(0.0);
    expect($balance->getCurrency()->getCurrencyCode())->toBe('PHP');
});

test('handles null wallet type and uses default', function () {
    $user = User::factory()->create();
    $user->depositFloat(250);
    
    $balance = CheckBalance::run($user, null);
    
    expect($balance->getAmount()->toFloat())->toBe(250.0);
});
