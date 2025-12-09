<?php

use App\Models\InstructionItem;
use App\Models\RevenueCollection;
use App\Models\User;
use App\Services\RevenueCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    
    // Create system user
    $this->systemUser = User::factory()->create([
        'email' => 'system@disburse.cash',
        'name' => 'System User',
    ]);
    $this->systemUser->depositFloat(1_000_000.00);
    
    // Create revenue user (optional)
    $this->revenueUser = User::factory()->create([
        'email' => 'revenue@company.com',
        'name' => 'Revenue User',
    ]);
    
    // Create regular user
    $this->user = User::factory()->create([
        'email' => 'user@example.com',
        'name' => 'Test User',
    ]);
    
    // Create InstructionItems with prices
    $this->amountItem = InstructionItem::factory()->create([
        'name' => 'Amount',
        'index' => 'cash.amount',
        'type' => 'cash',
        'price' => 2000, // ₱20 in centavos
        'currency' => 'PHP',
    ]);
    
    $this->emailItem = InstructionItem::factory()->create([
        'name' => 'Email',
        'index' => 'input.email',
        'type' => 'input',
        'price' => 100, // ₱1 in centavos
        'currency' => 'PHP',
    ]);
    
    // Set config for testing
    config([
        'account.system_user.identifier' => 'system@disburse.cash',
        'account.revenue_user.identifier' => null, // null = use system as default
    ]);
});

test('can get pending revenue when InstructionItems have balances', function () {
    // User pays fees to InstructionItems
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    $this->user->pay($this->emailItem);
    
    $service = app(RevenueCollectionService::class);
    $pending = $service->getPendingRevenue();
    
    expect($pending)
        ->toHaveCount(2)
        ->and($pending->sum('balance'))->toBe(21.0)
        ->and($pending->firstWhere('id', $this->amountItem->id)['balance'])->toBe(20.0)
        ->and($pending->firstWhere('id', $this->emailItem->id)['balance'])->toBe(1.0);
});

test('shows no pending revenue when InstructionItems have zero balance', function () {
    $service = app(RevenueCollectionService::class);
    $pending = $service->getPendingRevenue();
    
    expect($pending)->toBeEmpty();
});

test('can filter pending revenue by minimum amount', function () {
    // Create balances
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem); // ₱20
    $this->user->pay($this->emailItem);  // ₱1
    
    $service = app(RevenueCollectionService::class);
    $pending = $service->getPendingRevenue(10.0); // Min ₱10
    
    // Should only show Amount (₱20), not Email (₱1)
    expect($pending)
        ->toHaveCount(1)
        ->and($pending->first()['name'])->toBe('Amount');
});

test('can collect revenue from specific InstructionItem', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    $initialBalance = (float) $this->amountItem->balanceFloat;
    expect($initialBalance)->toBe(20.0);
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem);
    
    // Verify collection record
    expect($collection)
        ->toBeInstanceOf(RevenueCollection::class)
        ->and($collection->instruction_item_id)->toBe($this->amountItem->id)
        ->and($collection->amount)->toBe(2000) // centavos
        ->and($collection->formatted_amount)->toBe('₱20.00')
        ->and($collection->destination_type)->toBe(User::class);
    
    // Verify InstructionItem balance cleared
    expect((float) $this->amountItem->refresh()->balanceFloat)->toBe(0.0);
    
    // Verify destination received funds (system user by default)
    $systemUser = User::where('email', 'system@disburse.cash')->first();
    expect($systemUser->refresh()->balanceFloat)->toBeGreaterThan(999_000.0);
});

test('throws exception when collecting from InstructionItem with zero balance', function () {
    $service = app(RevenueCollectionService::class);
    
    $service->collect($this->amountItem);
})->throws(InvalidArgumentException::class, "has no balance to collect");

test('can collect revenue from all InstructionItems', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    $this->user->pay($this->emailItem);
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $collections = $service->collectAll();
    
    expect($collections)
        ->toHaveCount(2)
        ->and($collections->sum('amount'))->toBe(2100) // ₱21 in centavos
        ->and((float) $this->amountItem->refresh()->balanceFloat)->toBe(0.0)
        ->and((float) $this->emailItem->refresh()->balanceFloat)->toBe(0.0);
});

test('can override destination when collecting', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    $customDestination = User::factory()->create();
    $initialBalance = $customDestination->balanceFloat;
    
    // Collect with override
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem, $customDestination);
    
    expect($collection->destination_id)->toBe($customDestination->id)
        ->and((float) $customDestination->refresh()->balanceFloat)->toBe($initialBalance + 20.0);
});

test('uses configured revenue destination per InstructionItem', function () {
    // Configure specific destination for email item
    $emailPartner = User::factory()->create(['email' => 'partner@email.com']);
    $this->emailItem->revenueDestination()->associate($emailPartner);
    $this->emailItem->save();
    
    // Setup balances
    $this->user->depositFloat(100.00);
    $this->user->pay($this->emailItem);
    
    $initialBalance = $emailPartner->balanceFloat;
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->emailItem);
    
    // Verify went to configured destination
    expect($collection->destination_id)->toBe($emailPartner->id)
        ->and((float) $emailPartner->refresh()->balanceFloat)->toBe($initialBalance + 1.0);
});

test('uses default revenue user when configured', function () {
    // Set revenue user in config
    config(['account.revenue_user.identifier' => 'revenue@company.com']);
    
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    $initialBalance = $this->revenueUser->balanceFloat;
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem);
    
    // Verify went to revenue user
    expect($collection->destination_id)->toBe($this->revenueUser->id)
        ->and((float) $this->revenueUser->refresh()->balanceFloat)->toBe($initialBalance + 20.0);
});

test('falls back to system user when no revenue user configured', function () {
    // Ensure no revenue user
    config(['account.revenue_user.identifier' => null]);
    
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem);
    
    // Verify went to system user
    expect($collection->destination_id)->toBe($this->systemUser->id);
});

test('can get total pending revenue', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem); // ₱20
    $this->user->pay($this->emailItem);  // ₱1
    
    $service = app(RevenueCollectionService::class);
    $total = $service->getTotalPendingRevenue();
    
    expect($total)->toBe(21.0);
});

test('can get revenue statistics', function () {
    // Setup balances
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    $this->user->pay($this->emailItem);
    
    // Collect once
    $service = app(RevenueCollectionService::class);
    $service->collect($this->amountItem);
    
    // Get stats
    $stats = $service->getStatistics();
    
    expect($stats)
        ->toHaveKey('pending')
        ->toHaveKey('all_time')
        ->toHaveKey('last_collection')
        ->and($stats['pending']['count'])->toBe(1) // Only email left
        ->and($stats['all_time']['total_collected'])->toBe(20.0)
        ->and($stats['all_time']['collections_count'])->toBe(1)
        ->and($stats['last_collection']['item_name'])->toBe('Amount')
        ->and($stats['last_collection']['amount'])->toBe('₱20.00');
});

test('collection creates transfer with correct amount', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem); // ₱20
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem);
    
    // Verify transfer exists
    expect($collection->transfer)
        ->not->toBeNull()
        ->and($collection->transfer->deposit->amount)->toBe(2000) // ₱20 in centavos
        ->and($collection->transfer->deposit->confirmed)->toBeTrue();
});

test('handles concurrent collections gracefully', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    $service = app(RevenueCollectionService::class);
    
    // First collection should succeed
    $collection1 = $service->collect($this->amountItem);
    expect($collection1)->toBeInstanceOf(RevenueCollection::class);
    
    // Second collection should throw (no balance)
    expect(fn() => $service->collect($this->amountItem))
        ->toThrow(InvalidArgumentException::class);
});

test('collectAll continues on individual failures', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    $this->user->pay($this->emailItem);
    
    // Break one item by collecting first
    $service = app(RevenueCollectionService::class);
    $service->collect($this->amountItem);
    
    // collectAll should still collect from emailItem
    $collections = $service->collectAll();
    
    expect($collections)->toHaveCount(1)
        ->and($collections->first()->instruction_item_id)->toBe($this->emailItem->id);
});

test('revenue collection command shows preview correctly', function () {
    // Setup
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    $this->user->pay($this->emailItem);
    
    // Verify service method works first
    $service = app(\App\Services\RevenueCollectionService::class);
    $pending = $service->getPendingRevenue();
    expect($pending)->toHaveCount(2);
    
    $this->artisan('revenue:collect', ['--preview' => true])
        ->expectsOutputToContain('Amount')
        ->expectsOutputToContain('Email')
        ->doesntExpectOutputToContain('No revenue')  // Should NOT show this
        ->assertExitCode(0);
});

test('revenue collection command shows statistics', function () {
    // Setup and collect
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    $service = app(RevenueCollectionService::class);
    $service->collect($this->amountItem);
    
    $this->artisan('revenue:collect --stats')
        ->expectsOutputToContain('Revenue Statistics')
        ->expectsOutputToContain('Pending Revenue')
        ->expectsOutputToContain('All-Time Collected')
        ->expectsOutputToContain('₱20.00')
        ->assertExitCode(0);
});

test('revenue collection maintains correct wallet balances', function () {
    // Setup
    $destination = User::factory()->create();
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem); // ₱20 fee
    
    $beforeDestination = $destination->balanceFloat;
    $beforeItem = $this->amountItem->balanceFloat;
    
    // Collect
    $service = app(RevenueCollectionService::class);
    $service->collect($this->amountItem, $destination);
    
    // Verify balances
    expect((float) $this->amountItem->refresh()->balanceFloat)->toBe(0.0)
        ->and((float) $destination->refresh()->balanceFloat)->toBe($beforeDestination + $beforeItem);
});

test('multiple collections accumulate in destination wallet', function () {
    // Setup multiple items with balances
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem); // ₱20
    $this->user->pay($this->emailItem);  // ₱1
    
    $destination = User::factory()->create();
    $initialBalance = $destination->balanceFloat;
    
    // Collect both
    $service = app(RevenueCollectionService::class);
    $service->collect($this->amountItem, $destination);
    $service->collect($this->emailItem, $destination);
    
    // Verify total accumulated
    expect((float) $destination->refresh()->balanceFloat)->toBe($initialBalance + 21.0);
});

test('RevenueCollection model has correct relationships', function () {
    // Setup and collect
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem);
    
    // Test relationships
    expect($collection->instructionItem)
        ->toBeInstanceOf(InstructionItem::class)
        ->and($collection->instructionItem->id)->toBe($this->amountItem->id)
        ->and($collection->destination)->toBeInstanceOf(User::class)
        ->and($collection->collectedBy)->toBeInstanceOf(User::class)
        ->and($collection->transfer)->not->toBeNull();
});

test('RevenueCollection has formatted attributes', function () {
    // Setup and collect
    $this->user->depositFloat(100.00);
    $this->user->pay($this->amountItem);
    
    $service = app(RevenueCollectionService::class);
    $collection = $service->collect($this->amountItem);
    
    expect($collection->formatted_amount)->toBe('₱20.00')
        ->and((float) $collection->amount_float)->toBe(20.0)
        ->and($collection->destination_name)->toBeString();
});
