<?php

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\InputInstructionData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Enums\InputFieldEnum;
use LBHurtado\FormFlowManager\Services\DriverService;

test('default splash screen is generated when no custom splash provided', function () {
    // Create user and voucher without splash data
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create([
        'owner_type' => User::class,
        'owner_id' => $user->id,
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                ]),
                'inputs' => InputInstructionData::from([
                    'fields' => [InputFieldEnum::NAME, InputFieldEnum::EMAIL],
                ]),
                'rider' => RiderInstructionData::from([
                    // No splash/splash_timeout fields
                    'message' => null,
                    'url' => null,
                    'redirect_timeout' => null,
                ]),
            ])->toArray(),
        ],
    ]);
    
    // Transform voucher using driver
    $driverService = new DriverService();
    $formFlow = $driverService->transform($voucher);
    
    // Find splash step
    $splashStep = collect($formFlow->steps)->firstWhere('handler', 'splash');
    
    expect($splashStep)->not->toBeNull()
        ->and($splashStep['handler'])->toBe('splash')
        ->and($splashStep['config']['content'] ?? '')->toBeEmpty(); // Empty content triggers default
});

test('default splash content includes app metadata', function () {
    config(['app.name' => 'Test App']);
    config(['splash.app_author' => 'Test Author']);
    config(['splash.copyright_holder' => 'Test Corp']);
    config(['splash.copyright_year' => '2025']);
    
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create([
        'owner_type' => User::class,
        'owner_id' => $user->id,
        'code' => 'TEST123',
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 1000,
                    'currency' => 'PHP',
                ]),
                'inputs' => InputInstructionData::from([
                    'fields' => [InputFieldEnum::NAME],
                ]),
                'rider' => RiderInstructionData::from([]),
            ])->toArray(),
        ],
    ]);
    
    // Transform and get rendered content
    $driverService = new DriverService();
    $formFlow = $driverService->transform($voucher);
    
    // Render splash step to get actual content
    $handler = app(\LBHurtado\FormFlowManager\Handlers\SplashHandler::class);
    $splashStep = collect($formFlow->steps)->firstWhere('handler', 'splash');
    
    $stepData = \LBHurtado\FormFlowManager\Data\FormFlowStepData::from([
        'handler' => 'splash',
        'config' => $splashStep['config'],
    ]);
    
    $response = $handler->render($stepData, [
        'flow_id' => 'test-flow',
        'step_index' => 0,
        'voucher_code' => 'TEST123',
        'code' => 'TEST123',
    ]);
    
    $props = $response->props;
    $content = $props['content'];
    
    expect($content)
        ->toContain('Test App')
        ->toContain('TEST123')
        ->toContain('Test Author')
        ->toContain('Test Corp')
        ->toContain('2025');
});

test('custom splash content overrides default', function () {
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create([
        'owner_type' => User::class,
        'owner_id' => $user->id,
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                ]),
                'inputs' => InputInstructionData::from([
                    'fields' => [InputFieldEnum::NAME],
                ]),
                'rider' => RiderInstructionData::from([
                    'splash' => '# Custom Splash\nThis is my custom content!',
                    'splash_timeout' => 10,
                ]),
            ])->toArray(),
        ],
    ]);
    
    $driverService = new DriverService();
    $formFlow = $driverService->transform($voucher);
    
    $splashStep = collect($formFlow->steps)->firstWhere('handler', 'splash');
    
    expect($splashStep['config']['content'])
        ->toBe('# Custom Splash\nThis is my custom content!')
        ->and($splashStep['config']['timeout'])
        ->toBe('10');
});

test('splash can be disabled via config', function () {
    config(['splash.enabled' => false]);
    
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create([
        'owner_type' => User::class,
        'owner_id' => $user->id,
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                ]),
                'inputs' => InputInstructionData::from([
                    'fields' => [InputFieldEnum::NAME],
                ]),
                'rider' => RiderInstructionData::from([]),
            ])->toArray(),
        ],
    ]);
    
    $driverService = new DriverService();
    $formFlow = $driverService->transform($voucher);
    
    // Splash step should be filtered out due to condition
    $splashStep = collect($formFlow->steps)->firstWhere('handler', 'splash');
    
    expect($splashStep)->toBeNull();
    
    // Reset config for other tests
    config(['splash.enabled' => true]);
});

test('default timeout is used when not specified', function () {
    config(['splash.default_timeout' => 8]);
    
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create([
        'owner_type' => User::class,
        'owner_id' => $user->id,
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                ]),
                'inputs' => InputInstructionData::from([
                    'fields' => [InputFieldEnum::NAME],
                ]),
                'rider' => RiderInstructionData::from([
                    // No splash_timeout specified
                ]),
            ])->toArray(),
        ],
    ]);
    
    $handler = app(\LBHurtado\FormFlowManager\Handlers\SplashHandler::class);
    
    $stepData = \LBHurtado\FormFlowManager\Data\FormFlowStepData::from([
        'handler' => 'splash',
        'config' => [
            'content' => '',
            // No timeout specified
        ],
    ]);
    
    $response = $handler->render($stepData, [
        'flow_id' => 'test-flow',
        'step_index' => 0,
    ]);
    
    expect($response->props['timeout'])->toBe(8);
    
    // Reset config
    config(['splash.default_timeout' => 5]);
});

test('custom default splash content with variables', function () {
    config([
        'splash.default_content' => '<h1>{app_name}</h1><p>Voucher: {voucher_code}</p>',
        'app.name' => 'My App',
    ]);
    
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create([
        'owner_type' => User::class,
        'owner_id' => $user->id,
        'code' => 'ABC123',
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                ]),
                'inputs' => InputInstructionData::from([
                    'fields' => [InputFieldEnum::NAME],
                ]),
                'rider' => RiderInstructionData::from([]),
            ])->toArray(),
        ],
    ]);
    
    $handler = app(\LBHurtado\FormFlowManager\Handlers\SplashHandler::class);
    
    $stepData = \LBHurtado\FormFlowManager\Data\FormFlowStepData::from([
        'handler' => 'splash',
        'config' => [
            'content' => '', // Empty triggers default
        ],
    ]);
    
    $response = $handler->render($stepData, [
        'flow_id' => 'test-flow',
        'step_index' => 0,
        'voucher_code' => 'ABC123',
        'code' => 'ABC123',
    ]);
    
    $content = $response->props['content'];
    
    expect($content)
        ->toContain('My App')
        ->toContain('ABC123');
    
    // Reset config
    config(['splash.default_content' => null]);
});
