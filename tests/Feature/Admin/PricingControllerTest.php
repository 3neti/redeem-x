<?php

use App\Models\InstructionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    
    // Create admin user and add to override list
    $this->admin = User::factory()->create(['email' => 'admin@test.com']);
    $this->admin->assignRole('super-admin');
    
    // Add admin email to override list so they bypass permission checks
    config(['admin.override_emails' => ['admin@test.com']]);
    
    // Create regular user
    $this->user = User::factory()->create();
    
    // Create test instruction items
    $this->item = InstructionItem::create([
        'name' => 'Cash Amount',
        'index' => 'cash.amount',
        'type' => 'cash',
        'price' => 2000,
        'meta' => ['label' => 'Base Fee'],
    ]);
});

test('admin can view pricing index', function () {
    $response = $this->actingAs($this->admin)->get('/admin/pricing');
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pricing/Index')
            ->has('items')
        );
});

test('regular user cannot access pricing index', function () {
    $response = $this->actingAs($this->user)->get('/admin/pricing');
    
    $response->assertForbidden();
});

test('guest cannot access pricing index', function () {
    $response = $this->get('/admin/pricing');
    
    $response->assertRedirect('/login');
});

test('admin can view pricing edit page', function () {
    $response = $this->actingAs($this->admin)->get("/admin/pricing/{$this->item->id}/edit");
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pricing/Edit')
            ->has('item')
            ->has('history')
            ->where('item.id', $this->item->id)
            ->where('item.name', 'Cash Amount')
        );
});

test('admin can update pricing', function () {
    $response = $this->actingAs($this->admin)->patch("/admin/pricing/{$this->item->id}", [
        'price' => 25.00, // ₱25.00 = 2500 centavos
        'reason' => 'Increased base fee for better service quality',
        'label' => 'Updated Base Fee',
        'description' => 'This is the base fee for all vouchers',
    ]);
    
    $response->assertRedirect('/admin/pricing')
        ->assertSessionHas('success');
    
    $this->item->refresh();
    expect($this->item->price)->toBe(2500)
        ->and($this->item->meta['label'])->toBe('Updated Base Fee')
        ->and($this->item->meta['description'])->toBe('This is the base fee for all vouchers');
    
    // Verify price history was created for this update
    // Filter to records with reason (controller updates) to exclude observer-created ones
    $updateHistory = $this->item->priceHistory()->whereNotNull('reason')->get();
    expect($updateHistory)->toHaveCount(1);
    
    $history = $updateHistory->first();
    expect($history->old_price)->toBe(2000)
        ->and($history->new_price)->toBe(2500)
        ->and($history->changed_by)->toBe($this->admin->id)
        ->and($history->reason)->toBe('Increased base fee for better service quality');
});

test('admin cannot update pricing without reason', function () {
    $response = $this->actingAs($this->admin)->patch("/admin/pricing/{$this->item->id}", [
        'price' => 25.00,
    ]);
    
    $response->assertSessionHasErrors('reason');
});

test('admin cannot update pricing with invalid price', function () {
    $response = $this->actingAs($this->admin)->patch("/admin/pricing/{$this->item->id}", [
        'price' => -10.00,
        'reason' => 'Test',
    ]);
    
    $response->assertSessionHasErrors('price');
});

test('regular user cannot update pricing', function () {
    $response = $this->actingAs($this->user)->patch("/admin/pricing/{$this->item->id}", [
        'price' => 25.00,
        'reason' => 'Test',
    ]);
    
    $response->assertForbidden();
});
