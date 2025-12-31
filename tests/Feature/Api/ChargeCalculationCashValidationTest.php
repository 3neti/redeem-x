<?php

declare(strict_types=1);

use App\Models\User;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed instruction pricing items
    $this->artisan('db:seed', ['--class' => 'InstructionItemSeeder']);
    
    $this->user = User::factory()->create();
});

it('includes cash.validation.secret charge when secret is provided', function () {
    $payload = [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'test-secret',
                'mobile' => null,
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'validation' => ['location' => null, 'time' => null],
        'count' => 1,
        'ttl' => 'P30D',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/calculate-charges', $payload);

    $response->assertOk();
    
    $data = $response->json();
    
    // Debug output
    dump('Response:', $data);
    dump('Breakdown items:', collect($data['breakdown'] ?? [])->pluck('index')->toArray());
    
    // Should include cash.validation.secret charge (120 centavos = ₱1.20)
    expect($data['breakdown'])
        ->toBeArray()
        ->and(collect($data['breakdown'])->pluck('index')->toArray())
        ->toContain('cash.validation.secret');
});

it('includes cash.validation.mobile charge when mobile is provided', function () {
    $payload = [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => '09171234567',
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'validation' => ['location' => null, 'time' => null],
        'count' => 1,
        'ttl' => 'P30D',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/calculate-charges', $payload);

    $response->assertOk();
    
    $data = $response->json();
    
    // Debug output
    dump('Response:', $data);
    dump('Breakdown items:', collect($data['breakdown'] ?? [])->pluck('index')->toArray());
    
    // Should include cash.validation.mobile charge (130 centavos = ₱1.30)
    expect($data['breakdown'])
        ->toBeArray()
        ->and(collect($data['breakdown'])->pluck('index')->toArray())
        ->toContain('cash.validation.mobile');
});

it('includes both cash.validation charges when both are provided', function () {
    $payload = [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'test-secret',
                'mobile' => '09171234567',
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'validation' => ['location' => null, 'time' => null],
        'count' => 1,
        'ttl' => 'P30D',
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/calculate-charges', $payload);

    $response->assertOk();
    
    $data = $response->json();
    
    // Debug output
    dump('Response:', $data);
    dump('Breakdown items:', collect($data['breakdown'] ?? [])->pluck('index')->toArray());
    
    $breakdown = collect($data['breakdown']);
    $indices = $breakdown->pluck('index')->toArray();
    
    // Should include both charges
    expect($indices)
        ->toContain('cash.validation.secret')
        ->toContain('cash.validation.mobile');
    
    // Check total includes both charges (120 + 130 = 250 centavos)
    $secretCharge = $breakdown->firstWhere('index', 'cash.validation.secret');
    $mobileCharge = $breakdown->firstWhere('index', 'cash.validation.mobile');
    
    expect($secretCharge)->not->toBeNull()
        ->and($secretCharge['price'])->toBe(120);
    
    expect($mobileCharge)->not->toBeNull()
        ->and($mobileCharge['price'])->toBe(130);
});

it('converts request to VoucherInstructionsData correctly preserving cash.validation', function () {
    $payload = [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'test-secret',
                'mobile' => '09171234567',
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'validation' => ['location' => null, 'time' => null],
        'count' => 1,
        'ttl' => 'P30D',
    ];

    // Test direct Data object creation
    $instructions = VoucherInstructionsData::from($payload);
    
    dump('Instructions cash.validation:', $instructions->cash->validation);
    
    expect($instructions->cash->validation->secret)->toBe('test-secret')
        ->and($instructions->cash->validation->mobile)->toBe('09171234567')
        ->and($instructions->cash->validation->country)->toBe('PH');
});
