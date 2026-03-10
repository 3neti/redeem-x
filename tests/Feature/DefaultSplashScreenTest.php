<?php

use App\Models\User;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Helper to create a voucher with given instructions overrides.
 */
function createSplashTestVoucher(User $user, array $instructionOverrides = []): Voucher
{
    $user->depositFloat(100000);
    auth()->setUser($user);

    $defaults = [
        'cash' => [
            'amount' => 500,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => ['name', 'email']],
        'feedback' => [],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
        ],
        'count' => 1,
        'prefix' => 'SPL',
        'mask' => '****',
    ];

    $instructions = VoucherInstructionsData::from(array_replace_recursive($defaults, $instructionOverrides));

    return GenerateVouchers::run($instructions)->first();
}

test('default splash screen is generated when no custom splash provided', function () {
    $user = User::factory()->create();
    $voucher = createSplashTestVoucher($user);

    // Transform voucher using driver
    $driverService = new DriverService;
    $formFlow = $driverService->transform($voucher);

    // Find splash step
    $splashStep = collect($formFlow->steps)->first(fn ($s) => $s->handler === 'splash');

    expect($splashStep)->not->toBeNull()
        ->and($splashStep->handler)->toBe('splash')
        ->and($splashStep->config['content'] ?? '')->toBeEmpty(); // Empty content triggers default
});

test('default splash content includes app metadata', function () {
    config(['app.name' => 'Test App']);
    config(['splash.app_author' => 'Test Author']);
    config(['splash.copyright_holder' => 'Test Corp']);
    config(['splash.copyright_year' => '2025']);

    $user = User::factory()->create();
    $voucher = createSplashTestVoucher($user, [
        'cash' => ['amount' => 1000],
        'inputs' => ['fields' => ['name']],
    ]);

    // Transform and get rendered content
    $driverService = new DriverService;
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
})->skip('Inertia Response::$props is protected - cannot access directly in test');

test('custom splash content overrides default', function () {
    $user = User::factory()->create();
    $voucher = createSplashTestVoucher($user, [
        'inputs' => ['fields' => ['name']],
        'rider' => [
            'splash' => '# Custom Splash\nThis is my custom content!',
            'splash_timeout' => 10,
        ],
    ]);

    $driverService = new DriverService;
    $formFlow = $driverService->transform($voucher);

    $splashStep = collect($formFlow->steps)->first(fn ($s) => $s->handler === 'splash');

    expect($splashStep->config['content'])
        ->toBe('# Custom Splash\nThis is my custom content!')
        ->and($splashStep->config['timeout'])
        ->toBe('10');
});

test('splash can be disabled via config', function () {
    config(['splash.enabled' => false]);

    $user = User::factory()->create();
    $voucher = createSplashTestVoucher($user, [
        'inputs' => ['fields' => ['name']],
    ]);

    $driverService = new DriverService;
    $formFlow = $driverService->transform($voucher);

    // Splash step should be filtered out due to condition
    $splashStep = collect($formFlow->steps)->first(fn ($s) => $s->handler === 'splash');

    expect($splashStep)->toBeNull();

    // Reset config for other tests
    config(['splash.enabled' => true]);
});

test('default timeout is used when not specified', function () {
    config(['splash.default_timeout' => 8]);

    $user = User::factory()->create();

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
})->skip('Inertia Response::$props is protected - cannot access directly in test');

test('custom default splash content with variables', function () {
    config([
        'splash.default_content' => '<h1>{app_name}</h1><p>Voucher: {voucher_code}</p>',
        'app.name' => 'My App',
    ]);

    $user = User::factory()->create();
    $voucher = createSplashTestVoucher($user, [
        'inputs' => ['fields' => ['name']],
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
})->skip('Inertia Response::$props is protected - cannot access directly in test');
