<?php

use App\Models\User;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    $this->actingAs($this->user);
});

it('generates vouchers with metadata', function () {
    $instructions = [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'META',
        'mask' => '****',
    ];

    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->metadata)->toHaveKey('instructions');
    expect($voucher->metadata['instructions'])->toHaveKey('metadata');
    
    $metadata = $voucher->metadata['instructions']['metadata'];
    expect($metadata)->toHaveKey('version');
    expect($metadata)->toHaveKey('system_name');
    expect($metadata)->toHaveKey('copyright');
    expect($metadata)->toHaveKey('licenses');
    expect($metadata)->toHaveKey('issuer_id');
    expect($metadata)->toHaveKey('issuer_name');
    expect($metadata)->toHaveKey('issuer_email');
    expect($metadata)->toHaveKey('redemption_urls');
    expect($metadata)->toHaveKey('primary_url');
    expect($metadata)->toHaveKey('created_at');
    expect($metadata)->toHaveKey('issued_at');
});

it('populates metadata with correct values from config', function () {
    config([
        'voucher.metadata.version' => '1.0.0',
        'voucher.metadata.system_name' => 'Test System',
        'voucher.metadata.copyright' => 'Test Corp',
        'voucher.metadata.licenses' => [
            'BSP' => 'Bangko Sentral ng Pilipinas',
            'SEC' => 'Securities and Exchange Commission',
        ],
    ]);

    $instructions = [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
    ];

    $voucher = GenerateVouchers::run($instructions)->first();
    $metadata = $voucher->metadata['instructions']['metadata'];

    expect($metadata['version'])->toBe('1.0.0');
    expect($metadata['system_name'])->toBe('Test System');
    expect($metadata['copyright'])->toBe('Test Corp');
    expect($metadata['licenses'])->toHaveKey('BSP');
    expect($metadata['licenses'])->toHaveKey('SEC');
    expect($metadata['licenses']['BSP'])->toBe('Bangko Sentral ng Pilipinas');
});

it('includes issuer information in metadata', function () {
    $instructions = [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'ISS',
        'mask' => '****',
    ];

    $voucher = GenerateVouchers::run($instructions)->first();
    $metadata = $voucher->metadata['instructions']['metadata'];

    expect($metadata['issuer_id'])->toBe($this->user->id);
    expect($metadata['issuer_name'])->toBe('Test User');
    expect($metadata['issuer_email'])->toBe('test@example.com');
});

it('includes redemption URLs in metadata', function () {
    $instructions = [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'URL',
        'mask' => '****',
    ];

    $voucher = GenerateVouchers::run($instructions)->first();
    $metadata = $voucher->metadata['instructions']['metadata'];

    expect($metadata['redemption_urls'])->toHaveKey('web');
    expect($metadata['redemption_urls']['web'])->toContain('/redeem');
    expect($metadata['primary_url'])->toContain('/redeem');
});

it('inspect endpoint returns metadata for new vouchers', function () {
    $voucher = GenerateVouchers::run([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'API',
        'mask' => '****',
    ])->first();

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/inspect");

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'code' => $voucher->code,
        'status' => 'active',
    ]);
    
    $response->assertJsonPath('metadata.version', '1.0.0');
    $response->assertJsonPath('metadata.issuer_name', 'Test User');
    $response->assertJsonPath('info.issuer.email', 'test@example.com');
});

it('inspect endpoint handles vouchers without metadata gracefully', function () {
    // Create voucher directly via Vouchers facade (bypassing GenerateVouchers)
    $voucher = \FrittenKeeZ\Vouchers\Facades\Vouchers::withOwner($this->user)
        ->withPrefix('OLD')
        ->withMask('****')
        ->withMetadata([
            'instructions' => [
                'cash' => ['amount' => 100, 'currency' => 'PHP'],
                // No metadata field
            ],
        ])
        ->create();

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/inspect");

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'metadata' => null,
        'info' => [
            'message' => 'This voucher was created before metadata tracking was implemented.',
        ],
    ]);
});

it('inspect endpoint returns 404 for non-existent vouchers', function () {
    $response = $this->getJson('/api/v1/vouchers/INVALID-CODE/inspect');

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'message' => 'Voucher not found',
    ]);
});

it('filters out null values from metadata licenses', function () {
    config([
        'voucher.metadata.licenses' => [
            'BSP' => 'Bangko Sentral',
            'SEC' => null, // Should be filtered out
            'NTC' => '',   // Should be filtered out
        ],
    ]);

    $voucher = GenerateVouchers::run([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'LIC',
        'mask' => '****',
    ])->first();

    $metadata = $voucher->metadata['instructions']['metadata'];
    
    expect($metadata['licenses'])->toHaveKey('BSP');
    expect($metadata['licenses'])->not->toHaveKey('SEC');
    expect($metadata['licenses'])->not->toHaveKey('NTC');
});
