<?php

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('new user gets two default campaigns', function () {
    $user = User::factory()->create();
    
    expect($user->campaigns)->toHaveCount(2)
        ->and($user->campaigns->pluck('name')->toArray())
        ->toContain('Blank Template', 'Standard Campaign');
});

test('vouchers attach to campaign via pivot table', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    // Fund the wallet
    $user->depositFloat(1000);
    
    $response = $this->actingAs($user)->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 2,
        'campaign_id' => $campaign->id,
    ]);
    
    $response->assertStatus(201);
    
    // Check pivot table has entries
    $pivotCount = DB::table('campaign_voucher')
        ->where('campaign_id', $campaign->id)
        ->count();
    
    expect($pivotCount)->toBe(2);
});

test('pivot table stores instructions snapshot', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    $user->depositFloat(1000);
    
    $response = $this->actingAs($user)->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
        'campaign_id' => $campaign->id,
    ]);
    
    $response->assertStatus(201);
    
    $pivot = DB::table('campaign_voucher')
        ->where('campaign_id', $campaign->id)
        ->first();
    
    expect($pivot)->not->toBeNull()
        ->and($pivot->instructions_snapshot)->toBeJson();
    
    $snapshot = json_decode($pivot->instructions_snapshot, true);
    expect($snapshot)->toHaveKey('cash')
        ->and($snapshot)->toHaveKey('inputs');
});

test('campaign shows voucher count', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    $user->depositFloat(1000);
    
    // Generate 3 vouchers
    $this->actingAs($user)->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 3,
        'campaign_id' => $campaign->id,
    ]);
    
    $campaign->refresh();
    
    expect($campaign->vouchers()->count())->toBe(3);
});

test('voucher generation without campaign works', function () {
    $user = User::factory()->create();
    $user->depositFloat(1000);
    
    $response = $this->actingAs($user)->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ]);
    
    $response->assertStatus(201);
    
    // No pivot entries
    $pivotCount = DB::table('campaign_voucher')->count();
    expect($pivotCount)->toBe(0);
});
