<?php

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create campaign with location validation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/settings/campaigns', [
        'name' => 'Location Test Campaign',
        'description' => 'Testing location validation',
        'status' => 'active',
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => ['name', 'email'],
            ],
            'feedback' => [
                'email' => 'test@example.com',
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => 'Test message',
                'url' => null,
            ],
            'validation' => [
                'location' => [
                    'required' => true,
                    'target_lat' => 14.5995,
                    'target_lng' => 120.9842,
                    'radius_meters' => 1000,
                    'on_failure' => 'block',
                ],
                'time' => null,
            ],
            'count' => 10,
            'prefix' => 'LOC',
            'mask' => '****-**',
            'ttl' => 'P30D',
        ],
    ]);

    $response->assertRedirect('/settings/campaigns');
    $response->assertSessionHas('success');

    $campaign = Campaign::where('name', 'Location Test Campaign')->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->instructions)->not->toBeNull();

    // Check validation data is stored
    $instructions = $campaign->instructions;
    expect($instructions->validation)->not->toBeNull();
    expect($instructions->validation->location)->not->toBeNull();
    expect($instructions->validation->location->target_lat)->toBe(14.5995);
    expect($instructions->validation->location->target_lng)->toBe(120.9842);
    expect($instructions->validation->location->radius_meters)->toBe(1000);
    expect($instructions->validation->location->on_failure)->toBe('block');
});

test('can create campaign with time validation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/settings/campaigns', [
        'name' => 'Time Test Campaign',
        'description' => 'Testing time validation',
        'status' => 'active',
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
            ],
            'validation' => [
                'location' => null,
                'time' => [
                    'window' => [
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'timezone' => 'Asia/Manila',
                    ],
                    'limit_minutes' => 10,
                    'track_duration' => true,
                ],
            ],
            'count' => 5,
            'prefix' => 'TIME',
            'mask' => '****',
            'ttl' => 'P7D',
        ],
    ]);

    $response->assertRedirect('/settings/campaigns');
    $response->assertSessionHas('success');

    $campaign = Campaign::where('name', 'Time Test Campaign')->first();
    expect($campaign)->not->toBeNull();

    // Check validation data is stored
    $instructions = $campaign->instructions;
    expect($instructions->validation)->not->toBeNull();
    expect($instructions->validation->time)->not->toBeNull();
    expect($instructions->validation->time->window)->not->toBeNull();
    expect($instructions->validation->time->window->start_time)->toBe('09:00');
    expect($instructions->validation->time->window->end_time)->toBe('17:00');
    expect($instructions->validation->time->window->timezone)->toBe('Asia/Manila');
    expect($instructions->validation->time->limit_minutes)->toBe(10);
    expect($instructions->validation->time->track_duration)->toBeTrue();
});

test('can create campaign with both validations', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/settings/campaigns', [
        'name' => 'Combined Validation Campaign',
        'description' => 'Testing both location and time validation',
        'status' => 'draft',
        'instructions' => [
            'cash' => [
                'amount' => 500,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => ['name', 'mobile'],
            ],
            'feedback' => [
                'email' => 'admin@example.com',
                'mobile' => '+639171234567',
                'webhook' => 'https://webhook.site/test',
            ],
            'rider' => [
                'message' => 'Happy Hour Promo',
                'url' => 'https://example.com',
            ],
            'validation' => [
                'location' => [
                    'required' => true,
                    'target_lat' => 14.5995,
                    'target_lng' => 120.9842,
                    'radius_meters' => 500,
                    'on_failure' => 'warn',
                ],
                'time' => [
                    'window' => [
                        'start_time' => '17:00',
                        'end_time' => '19:00',
                        'timezone' => 'Asia/Manila',
                    ],
                    'limit_minutes' => 5,
                    'track_duration' => true,
                ],
            ],
            'count' => 100,
            'prefix' => 'HAPPY',
            'mask' => '****-**',
            'ttl' => 'P1D',
        ],
    ]);

    $response->assertRedirect('/settings/campaigns');

    $campaign = Campaign::where('name', 'Combined Validation Campaign')->first();
    expect($campaign)->not->toBeNull();

    $instructions = $campaign->instructions;

    // Check location validation
    expect($instructions->validation->location)->not->toBeNull();
    expect($instructions->validation->location->radius_meters)->toBe(500);
    expect($instructions->validation->location->on_failure)->toBe('warn');

    // Check time validation
    expect($instructions->validation->time)->not->toBeNull();
    expect($instructions->validation->time->window->start_time)->toBe('17:00');
    expect($instructions->validation->time->window->end_time)->toBe('19:00');
    expect($instructions->validation->time->limit_minutes)->toBe(5);
});

test('can create campaign without validation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/settings/campaigns', [
        'name' => 'No Validation Campaign',
        'description' => 'Campaign without validation',
        'status' => 'active',
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
            ],
            'validation' => null, // No validation
            'count' => 10,
            'prefix' => 'NONE',
            'mask' => '****',
            'ttl' => null,
        ],
    ]);

    $response->assertRedirect('/settings/campaigns');

    $campaign = Campaign::where('name', 'No Validation Campaign')->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->instructions->validation)->toBeNull();
});
