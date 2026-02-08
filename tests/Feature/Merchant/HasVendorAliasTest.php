<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Merchant\Models\VendorAlias;

uses(RefreshDatabase::class);

test('user can have vendor aliases', function () {
    $user = User::factory()->create();

    VendorAlias::factory()->create(['owner_user_id' => $user->id, 'alias' => 'ALIAS1']);
    VendorAlias::factory()->create(['owner_user_id' => $user->id, 'alias' => 'ALIAS2', 'status' => 'revoked']);

    expect($user->vendorAliases)->toHaveCount(2);
    expect($user->vendorAliases->pluck('alias')->toArray())->toContain('ALIAS1', 'ALIAS2');
});

test('user has primary active alias', function () {
    $user = User::factory()->create();

    // Create multiple aliases, including revoked one
    $active = VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'alias' => 'PRIMARY',
        'status' => 'active',
        'assigned_at' => now(),
    ]);

    VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'alias' => 'REVOKED',
        'status' => 'revoked',
    ]);

    $primary = $user->primaryVendorAlias;

    expect($primary)->not->toBeNull();
    expect($primary->alias)->toBe('PRIMARY');
    expect($primary->status)->toBe('active');
});

test('primary alias returns most recent active alias', function () {
    $user = User::factory()->create();

    // Create older alias
    VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'alias' => 'OLD',
        'status' => 'active',
        'assigned_at' => now()->subDays(5),
    ]);

    // Create newer alias
    VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'alias' => 'NEW',
        'status' => 'active',
        'assigned_at' => now(),
    ]);

    expect($user->primaryVendorAlias->alias)->toBe('NEW');
});

test('primary alias returns null when no active aliases', function () {
    $user = User::factory()->create();

    // Only revoked alias
    VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'status' => 'revoked',
    ]);

    expect($user->primaryVendorAlias)->toBeNull();
});

test('vendor alias has owner relationship', function () {
    $user = User::factory()->create(['name' => 'Test Merchant']);

    $alias = VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'alias' => 'TESTSHOP',
    ]);

    expect($alias->owner)->not->toBeNull();
    expect($alias->owner->id)->toBe($user->id);
    expect($alias->owner->name)->toBe('Test Merchant');
});

test('vendor alias has assigned_by relationship', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['name' => 'Admin User']);

    $alias = VendorAlias::factory()->create([
        'owner_user_id' => $user->id,
        'assigned_by_user_id' => $admin->id,
    ]);

    expect($alias->assignedBy)->not->toBeNull();
    expect($alias->assignedBy->id)->toBe($admin->id);
    expect($alias->assignedBy->name)->toBe('Admin User');
});
