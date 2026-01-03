<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LBHurtado\Merchant\Actions\AssignVendorAlias;
use LBHurtado\Merchant\Models\VendorAlias;

uses(RefreshDatabase::class);

test('assigns alias to user', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    
    $alias = AssignVendorAlias::run(
        ownerUserId: $user->id,
        alias: '  gcash  ', // Test normalization
        assignedByUserId: $admin->id,
        notes: 'Test assignment'
    );
    
    expect($alias->alias)->toBe('GCASH');
    expect($alias->owner_user_id)->toBe($user->id);
    expect($alias->assigned_by_user_id)->toBe($admin->id);
    expect($alias->status)->toBe('active');
    expect($alias->notes)->toBe('Test assignment');
    
    // Check database
    $this->assertDatabaseHas('vendor_aliases', [
        'alias' => 'GCASH',
        'owner_user_id' => $user->id,
        'status' => 'active',
    ]);
});

test('prevents duplicate aliases', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $admin = User::factory()->create();
    
    AssignVendorAlias::run($user1->id, 'SHOP', $admin->id);
    
    // Should throw exception
    AssignVendorAlias::run($user2->id, 'SHOP', $admin->id);
})->throws(\RuntimeException::class, 'Alias already exists');

test('prevents reserved alias assignment', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    
    // Seed reserved alias
    DB::table('reserved_vendor_aliases')->insert([
        'alias' => 'ADMIN',
        'reason' => 'System',
        'reserved_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Should throw exception
    AssignVendorAlias::run($user->id, 'ADMIN', $admin->id);
})->throws(\RuntimeException::class, 'reserved');

test('rejects invalid alias format', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    
    // Too short
    AssignVendorAlias::run($user->id, 'AB', $admin->id);
})->throws(\RuntimeException::class, 'Invalid alias format');

test('rejects alias with special characters', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    
    AssignVendorAlias::run($user->id, 'ABC-DEF', $admin->id);
})->throws(\RuntimeException::class, 'Invalid alias format');

test('can assign without assigned_by user', function () {
    $user = User::factory()->create();
    
    $alias = AssignVendorAlias::run(
        ownerUserId: $user->id,
        alias: 'MERCHANT',
        assignedByUserId: null,
        notes: null
    );
    
    expect($alias->alias)->toBe('MERCHANT');
    expect($alias->assigned_by_user_id)->toBeNull();
    expect($alias->notes)->toBeNull();
});
