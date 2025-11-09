<?php

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

uses(RefreshDatabase::class);

test('user can view their campaigns', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    $response = $this->actingAs($user)->get('/settings/campaigns');
    
    $response->assertStatus(200);
});

test('user cannot view another users campaign', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user1->id]);
    
    $response = $this->actingAs($user2)->get("/settings/campaigns/{$campaign->id}");
    
    $response->assertStatus(403);
});

test('user can create campaign', function () {
    $user = User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    
    $response = $this->actingAs($user)->post('/settings/campaigns', [
        'name' => 'Test Campaign',
        'description' => 'Test description',
        'status' => 'active',
        'instructions' => $instructions->toArray(),
    ]);
    
    $response->assertRedirect('/settings/campaigns');
    
    $this->assertDatabaseHas('campaigns', [
        'user_id' => $user->id,
        'name' => 'Test Campaign',
        'status' => 'active',
    ]);
});

test('user can delete their campaign', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    $response = $this->actingAs($user)->delete("/settings/campaigns/{$campaign->id}");
    
    $response->assertRedirect('/settings/campaigns');
    
    $this->assertDatabaseMissing('campaigns', [
        'id' => $campaign->id,
    ]);
});
