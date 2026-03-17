<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Data\MobileVerificationConfigData;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\MobileVerification\Drivers\BasicDriver;
use LBHurtado\Voucher\MobileVerification\Drivers\CountriesDriver;
use LBHurtado\Voucher\MobileVerification\Drivers\ExternalApiDriver;
use LBHurtado\Voucher\MobileVerification\Drivers\ExternalDbDriver;
use LBHurtado\Voucher\MobileVerification\Drivers\WhiteListDriver;
use LBHurtado\Voucher\MobileVerification\MobileVerificationManager;
use LBHurtado\Voucher\MobileVerification\MobileVerificationResult;
use LBHurtado\Voucher\Specifications\MobileVerificationSpecification;

// =============================================================================
// Result Value Object
// =============================================================================

test('MobileVerificationResult::pass creates valid result', function () {
    $result = MobileVerificationResult::pass('+639171234567', ['source' => 'inline']);

    expect($result->valid)->toBeTrue()
        ->and($result->passed())->toBeTrue()
        ->and($result->failed())->toBeFalse()
        ->and($result->normalizedMobile)->toBe('+639171234567')
        ->and($result->reason)->toBeNull()
        ->and($result->meta)->toBe(['source' => 'inline']);
});

test('MobileVerificationResult::fail creates invalid result', function () {
    $result = MobileVerificationResult::fail('Not in list', '+639171234567');

    expect($result->valid)->toBeFalse()
        ->and($result->passed())->toBeFalse()
        ->and($result->failed())->toBeTrue()
        ->and($result->reason)->toBe('Not in list');
});

// =============================================================================
// BasicDriver
// =============================================================================

test('BasicDriver passes valid mobile numbers', function () {
    $driver = new BasicDriver;

    $result = $driver->verify('09171234567');
    expect($result->passed())->toBeTrue()
        ->and($result->normalizedMobile)->toBe('+639171234567');

    $result = $driver->verify('+639171234567');
    expect($result->passed())->toBeTrue();

    $result = $driver->verify('639171234567');
    expect($result->passed())->toBeTrue();
});

test('BasicDriver fails on empty or too-short mobile', function () {
    $driver = new BasicDriver;

    expect($driver->verify('')->failed())->toBeTrue();
    expect($driver->verify('123')->failed())->toBeTrue();
});

// =============================================================================
// CountriesDriver
// =============================================================================

test('CountriesDriver passes PH mobile when PH is allowed', function () {
    $driver = new CountriesDriver;

    $result = $driver->verify('09171234567', ['countries' => ['PH']]);
    expect($result->passed())->toBeTrue()
        ->and($result->meta['detected_country'])->toBe('PH');
});

test('CountriesDriver fails when country is not in allowed list', function () {
    $driver = new CountriesDriver;

    // PH number but only US allowed
    $result = $driver->verify('+639171234567', ['countries' => ['US']]);
    expect($result->failed())->toBeTrue();
});

test('CountriesDriver passes with multiple allowed countries', function () {
    $driver = new CountriesDriver;

    $result = $driver->verify('+639171234567', ['countries' => ['PH', 'US', 'BE']]);
    expect($result->passed())->toBeTrue();
});

// =============================================================================
// WhiteListDriver
// =============================================================================

test('WhiteListDriver passes when mobile is in inline list', function () {
    $driver = new WhiteListDriver;

    $result = $driver->verify('09171234567', [
        'mobiles' => ['+639171234567', '+639181234567'],
    ]);

    expect($result->passed())->toBeTrue();
});

test('WhiteListDriver fails when mobile is not in inline list', function () {
    $driver = new WhiteListDriver;

    $result = $driver->verify('09999999999', [
        'mobiles' => ['+639171234567', '+639181234567'],
    ]);

    expect($result->failed())->toBeTrue()
        ->and($result->reason)->toBe('Mobile number is not in the allowed list.');
});

test('WhiteListDriver normalizes before comparison', function () {
    $driver = new WhiteListDriver;

    // Different formats of the same number
    $result = $driver->verify('09171234567', [
        'mobiles' => ['639171234567'], // without +
    ]);

    expect($result->passed())->toBeTrue();
});

test('WhiteListDriver loads from CSV file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('mobile-lists/test.csv', "mobile_number,name\n+639171234567,Juan\n+639181234567,Maria\n");

    $driver = new WhiteListDriver;

    $result = $driver->verify('09171234567', [
        'file' => 'mobile-lists/test.csv',
        'column' => 'mobile_number',
    ]);

    expect($result->passed())->toBeTrue();
});

test('WhiteListDriver merges inline and CSV sources', function () {
    Storage::fake('local');
    Storage::disk('local')->put('mobile-lists/test.csv', "mobile\n+639181234567\n");

    $driver = new WhiteListDriver;

    // Number is in inline list only
    $result = $driver->verify('09171234567', [
        'mobiles' => ['+639171234567'],
        'file' => 'mobile-lists/test.csv',
    ]);
    expect($result->passed())->toBeTrue();

    // Number is in CSV only
    $result = $driver->verify('09181234567', [
        'mobiles' => ['+639171234567'],
        'file' => 'mobile-lists/test.csv',
    ]);
    expect($result->passed())->toBeTrue();
});

test('WhiteListDriver fails with empty config', function () {
    $driver = new WhiteListDriver;

    $result = $driver->verify('09171234567', []);
    expect($result->failed())->toBeTrue();
});

// =============================================================================
// ExternalApiDriver
// =============================================================================

test('ExternalApiDriver passes when API returns valid=true', function () {
    Http::fake([
        'https://api.example.com/verify' => Http::response(['valid' => true], 200),
    ]);

    $driver = new ExternalApiDriver;
    $result = $driver->verify('09171234567', [
        'url' => 'https://api.example.com/verify',
        'method' => 'POST',
        'mobile_param' => 'mobile',
        'timeout' => 5,
        'headers' => ['Accept' => 'application/json'],
        'extra_params' => [],
        'response_field' => 'valid',
    ]);

    expect($result->passed())->toBeTrue();
});

test('ExternalApiDriver fails when API returns valid=false', function () {
    Http::fake([
        'https://api.example.com/verify' => Http::response(['valid' => false], 200),
    ]);

    $driver = new ExternalApiDriver;
    $result = $driver->verify('09171234567', [
        'url' => 'https://api.example.com/verify',
        'response_field' => 'valid',
    ]);

    expect($result->failed())->toBeTrue();
});

test('ExternalApiDriver handles API errors gracefully', function () {
    Http::fake([
        'https://api.example.com/verify' => Http::response('Server Error', 500),
    ]);

    $driver = new ExternalApiDriver;
    $result = $driver->verify('09171234567', [
        'url' => 'https://api.example.com/verify',
    ]);

    expect($result->failed())->toBeTrue()
        ->and($result->reason)->toContain('HTTP 500');
});

test('ExternalApiDriver fails without URL config', function () {
    $driver = new ExternalApiDriver;
    $result = $driver->verify('09171234567', []);

    expect($result->failed())->toBeTrue()
        ->and($result->reason)->toContain('not configured');
});

// =============================================================================
// ExternalDbDriver
// =============================================================================

test('ExternalDbDriver fails without full config', function () {
    $driver = new ExternalDbDriver;
    $result = $driver->verify('09171234567', []);

    expect($result->failed())->toBeTrue()
        ->and($result->reason)->toContain('not fully configured');
});

// =============================================================================
// MobileVerificationManager
// =============================================================================

test('Manager resolves default driver from config', function () {
    config(['voucher.mobile_verification.default' => 'basic']);
    config(['voucher.mobile_verification.drivers.basic' => [
        'class' => BasicDriver::class,
    ]]);

    $manager = new MobileVerificationManager;
    $result = $manager->verify('09171234567');

    expect($result->passed())->toBeTrue();
});

test('Manager resolves named driver override', function () {
    config(['voucher.mobile_verification.drivers.white_list' => [
        'class' => WhiteListDriver::class,
        'mobiles' => ['+639171234567'],
    ]]);

    $manager = new MobileVerificationManager;

    $result = $manager->verify('09171234567', 'white_list');
    expect($result->passed())->toBeTrue();

    $result = $manager->verify('09999999999', 'white_list');
    expect($result->failed())->toBeTrue();
});

test('Manager throws on unknown driver', function () {
    config(['voucher.mobile_verification.drivers' => []]);

    $manager = new MobileVerificationManager;
    $manager->verify('09171234567', 'nonexistent');
})->throws(InvalidArgumentException::class);

test('Manager resolves enforcement from config and overrides', function () {
    config(['voucher.mobile_verification.enforcement' => 'strict']);

    $manager = new MobileVerificationManager;

    expect($manager->getEnforcement())->toBe('strict');
    expect($manager->getEnforcement('soft'))->toBe('soft');
    expect($manager->getEnforcement(null))->toBe('strict');
});

// =============================================================================
// MobileVerificationConfigData DTO
// =============================================================================

test('DTO fromMixed handles null and false', function () {
    expect(MobileVerificationConfigData::fromMixed(null))->toBeNull();
    expect(MobileVerificationConfigData::fromMixed(false))->toBeNull();
});

test('DTO fromMixed handles true (use defaults)', function () {
    $config = MobileVerificationConfigData::fromMixed(true);

    expect($config)->toBeInstanceOf(MobileVerificationConfigData::class)
        ->and($config->driver)->toBeNull()
        ->and($config->enforcement)->toBeNull();
});

test('DTO fromMixed handles array with overrides', function () {
    $config = MobileVerificationConfigData::fromMixed([
        'driver' => 'countries',
        'enforcement' => 'soft',
    ]);

    expect($config->driver)->toBe('countries')
        ->and($config->enforcement)->toBe('soft');
});

test('DTO fromMixed handles partial array', function () {
    $config = MobileVerificationConfigData::fromMixed(['driver' => 'white_list']);

    expect($config->driver)->toBe('white_list')
        ->and($config->enforcement)->toBeNull();
});

// =============================================================================
// MobileVerificationSpecification
// =============================================================================

test('Specification passes when no mobile_verification configured', function () {
    $manager = new MobileVerificationManager;
    $spec = new MobileVerificationSpecification($manager);

    // Mock voucher with no mobile_verification
    $voucher = (object) [
        'code' => 'TEST-1234',
        'instructions' => (object) [
            'cash' => (object) [
                'validation' => (object) [
                    'mobile_verification' => null,
                ],
            ],
        ],
    ];

    $context = new RedemptionContext(mobile: '09171234567');

    expect($spec->passes($voucher, $context))->toBeTrue();
});

test('Specification blocks with strict enforcement on failure', function () {
    config(['voucher.mobile_verification.default' => 'white_list']);
    config(['voucher.mobile_verification.enforcement' => 'strict']);
    config(['voucher.mobile_verification.drivers.white_list' => [
        'class' => WhiteListDriver::class,
        'mobiles' => ['+639181234567'], // Different from context mobile
    ]]);

    $manager = new MobileVerificationManager;
    $spec = new MobileVerificationSpecification($manager);

    $voucher = (object) [
        'code' => 'TEST-1234',
        'instructions' => (object) [
            'cash' => (object) [
                'validation' => (object) [
                    'mobile_verification' => new MobileVerificationConfigData,
                ],
            ],
        ],
    ];

    $context = new RedemptionContext(mobile: '09171234567');

    expect($spec->passes($voucher, $context))->toBeFalse();
});

test('Specification passes with soft enforcement on failure', function () {
    config(['voucher.mobile_verification.default' => 'white_list']);
    config(['voucher.mobile_verification.enforcement' => 'soft']);
    config(['voucher.mobile_verification.drivers.white_list' => [
        'class' => WhiteListDriver::class,
        'mobiles' => ['+639181234567'], // Different from context mobile
    ]]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'soft enforcement'));

    $manager = new MobileVerificationManager;
    $spec = new MobileVerificationSpecification($manager);

    $voucher = (object) [
        'code' => 'TEST-1234',
        'instructions' => (object) [
            'cash' => (object) [
                'validation' => (object) [
                    'mobile_verification' => new MobileVerificationConfigData(enforcement: 'soft'),
                ],
            ],
        ],
    ];

    $context = new RedemptionContext(mobile: '09171234567');

    expect($spec->passes($voucher, $context))->toBeTrue();
});

test('Specification uses voucher driver override', function () {
    config(['voucher.mobile_verification.default' => 'basic']);
    config(['voucher.mobile_verification.enforcement' => 'strict']);
    config(['voucher.mobile_verification.drivers.basic' => [
        'class' => BasicDriver::class,
    ]]);
    config(['voucher.mobile_verification.drivers.white_list' => [
        'class' => WhiteListDriver::class,
        'mobiles' => ['+639181234567'],
    ]]);

    $manager = new MobileVerificationManager;
    $spec = new MobileVerificationSpecification($manager);

    // Voucher overrides to white_list — context mobile NOT in list
    $voucher = (object) [
        'code' => 'TEST-1234',
        'instructions' => (object) [
            'cash' => (object) [
                'validation' => (object) [
                    'mobile_verification' => new MobileVerificationConfigData(driver: 'white_list'),
                ],
            ],
        ],
    ];

    $context = new RedemptionContext(mobile: '09171234567');

    // Would pass with basic, but fails with white_list override
    expect($spec->passes($voucher, $context))->toBeFalse();
});

test('Specification passes when mobile matches whitelist', function () {
    config(['voucher.mobile_verification.default' => 'white_list']);
    config(['voucher.mobile_verification.enforcement' => 'strict']);
    config(['voucher.mobile_verification.drivers.white_list' => [
        'class' => WhiteListDriver::class,
        'mobiles' => ['+639171234567'],
    ]]);

    $manager = new MobileVerificationManager;
    $spec = new MobileVerificationSpecification($manager);

    $voucher = (object) [
        'code' => 'TEST-1234',
        'instructions' => (object) [
            'cash' => (object) [
                'validation' => (object) [
                    'mobile_verification' => new MobileVerificationConfigData,
                ],
            ],
        ],
    ];

    $context = new RedemptionContext(mobile: '09171234567');

    expect($spec->passes($voucher, $context))->toBeTrue();
});
