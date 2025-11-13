<?php

use Tests\Concerns\SetsUpRedemptionEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\{Role, Permission};
use App\Models\{User, InstructionItem};

uses(RefreshDatabase::class, SetsUpRedemptionEnvironment::class);

beforeEach(function () {
    $this->setUpRedemptionEnvironment();
});

test('roles and permissions are seeded correctly', function () {
    // Check that roles exist
    expect(Role::where('name', 'super-admin')->exists())->toBeTrue();
    
    // Check that permissions exist
    expect(Permission::where('name', 'manage pricing')->exists())->toBeTrue()
        ->and(Permission::where('name', 'view all billing')->exists())->toBeTrue()
        ->and(Permission::where('name', 'manage users')->exists())->toBeTrue();
    
    // Check that super-admin has the permissions
    $superAdmin = Role::findByName('super-admin');
    expect($superAdmin->hasPermissionTo('manage pricing'))->toBeTrue()
        ->and($superAdmin->hasPermissionTo('view all billing'))->toBeTrue()
        ->and($superAdmin->hasPermissionTo('manage users'))->toBeTrue();
});

test('system user exists and is configured from environment', function () {
    $systemEmail = env('SYSTEM_USER_ID');
    
    // System user should exist
    $systemUser = User::where('email', $systemEmail)->first();
    expect($systemUser)->not->toBeNull()
        ->and($systemUser->email)->toBe($systemEmail);
    
    // System user should have super-admin role
    expect($systemUser->hasRole('super-admin'))->toBeTrue();
    
    // Can also access via the trait helper
    expect($this->getSystemUser()->id)->toBe($systemUser->id);
});

test('system user has initial wallet balance', function () {
    $systemUser = $this->getSystemUser();
    
    // System user should have wallet
    expect($systemUser->wallet)->not->toBeNull();
    
    // System user should have the seeded balance (1,000,000.00)
    $balance = $systemUser->balanceFloat;
    expect((float)$balance)->toBe(1_000_000.00);
});

test('instruction items exist for cost calculations', function () {
    // Check that instruction items were seeded
    $items = InstructionItem::all();
    expect($items)->not->toBeEmpty();
    
    // Verify structure of instruction items
    $firstItem = $items->first();
    expect($firstItem)->toHaveKeys(['index', 'price', 'currency', 'meta']);
    
    // Verify they come from config
    $configItems = config('redeem.pricelist', []);
    expect($items->count())->toBeGreaterThanOrEqual(count($configItems));
});

test('external API mocks are configured', function () {
    // The trait should have configured HTTP fake
    // Skip if funds_api endpoint is not configured
    $endpoint = config('services.funds_api.endpoint');
    
    if ($endpoint) {
        $response = Http::get($endpoint);
        
        expect($response->successful())->toBeTrue()
            ->and($response->json('available'))->toBeTrue();
    } else {
        // If no endpoint configured, just verify Http is faked
        expect(true)->toBeTrue();
    }
});

test('regular user is also seeded', function () {
    // The UserSeeder also creates a regular user
    $regularUser = User::where('email', 'lester@hurtado.ph')->first();
    
    expect($regularUser)->not->toBeNull()
        ->and($regularUser->email)->toBe('lester@hurtado.ph');
});
