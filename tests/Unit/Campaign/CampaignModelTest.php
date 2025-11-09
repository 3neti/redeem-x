<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Tests\TestCase;

class CampaignModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_auto_generates_slug_on_creation()
    {
    $campaign = Campaign::factory()->create([
        'name' => 'Test Campaign',
        'slug' => null,
    ]);
    
    expect($campaign->slug)->toBeString()
        ->and($campaign->slug)->toContain('test-campaign');
    }

    public function test_campaign_belongs_to_user()
    {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create(['user_id' => $user->id]);
    
    expect($campaign->user)->toBeInstanceOf(User::class)
        ->and($campaign->user->id)->toBe($user->id);
    }

    public function test_campaign_instructions_accessor_returns_voucher_instructions_data()
    {
    $campaign = Campaign::factory()->create();
    
    expect($campaign->instructions)->toBeInstanceOf(VoucherInstructionsData::class);
    }

    public function test_campaign_factory_creates_valid_campaign()
    {
    $campaign = Campaign::factory()->create();
    
    expect($campaign)->toBeInstanceOf(Campaign::class)
        ->and($campaign->name)->toBeString()
        ->and($campaign->slug)->toBeString()
        ->and($campaign->status)->toBe('active')
        ->and($campaign->instructions)->toBeInstanceOf(VoucherInstructionsData::class);
    }

    public function test_campaign_factory_draft_state()
    {
    $campaign = Campaign::factory()->draft()->create();
    
    expect($campaign->status)->toBe('draft');
    }

    public function test_campaign_factory_archived_state()
    {
    $campaign = Campaign::factory()->archived()->create();
    
    expect($campaign->status)->toBe('archived');
    }

    public function test_campaign_factory_blank_state()
    {
    $campaign = Campaign::factory()->blank()->create();
    
    expect($campaign->name)->toBe('Blank Template')
        ->and(count($campaign->instructions->inputs->fields))->toBe(0);
    }
}
