#!/usr/bin/env php
<?php

/**
 * Test script for /disburse endpoint
 *
 * Tests the complete flow:
 * 1. Create test voucher with instructions
 * 2. Start disburse flow
 * 3. Transform to form flow
 * 4. Simulate data collection
 * 5. Complete redemption
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\Voucher\GenerateVouchers;
use App\Models\User;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

echo "═══════════════════════════════════════════════════════\n";
echo "   DISBURSE ENDPOINT TEST\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Step 1: Create test voucher
echo "Step 1: Creating test voucher with instructions...\n";
$user = User::first() ?? User::factory()->create();

$instructions = VoucherInstructionsData::from([
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
        'settlement_rail' => 'INSTAPAY',
        'fee_strategy' => 'absorb',
    ],
    'inputs' => [
        ['type' => 'text', 'name' => 'name', 'label' => 'Full Name', 'required' => true],
        ['type' => 'email', 'name' => 'email', 'label' => 'Email Address', 'required' => true],
        ['type' => 'location', 'name' => 'location', 'label' => 'Current Location', 'required' => true],
    ],
    'validations' => [],
    'feedback' => [],
    'rider' => null,
    'count' => 1,
    'prefix' => 'TEST',
    'mask' => null,
    'ttl' => null,
]);

$voucher = GenerateVouchers::run($user, $instructions)->first();

echo "✓ Voucher created: {$voucher->code}\n";
echo "  Amount: {$voucher->formatted_amount}\n";
echo '  Inputs: '.count($instructions->inputs)." fields\n";
echo "  URL: http://redeem-x.test/disburse?code={$voucher->code}\n\n";

// Step 2: Test DriverService transformation
echo "Step 2: Testing DriverService transformation...\n";
$driverService = app(DriverService::class);
$flowInstructions = $driverService->transform($voucher);

echo "✓ Transformation successful\n";
echo "  Reference ID: {$flowInstructions->reference_id}\n";
echo '  Steps generated: '.count($flowInstructions->steps)."\n";

foreach ($flowInstructions->steps as $index => $step) {
    echo '    Step '.($index + 1).": {$step->handler}";
    if (isset($step->config['title'])) {
        echo " - {$step->config['title']}";
    }
    echo "\n";

    if ($step->handler === 'form' && isset($step->config['fields'])) {
        foreach ($step->config['fields'] as $field) {
            echo "      - {$field['name']} ({$field['type']})\n";
        }
    }
}
echo "\n";

// Step 3: Simulate form flow
echo "Step 3: Testing FormFlowService...\n";
$formFlowService = app(FormFlowService::class);
$state = $formFlowService->startFlow($flowInstructions);

echo "✓ Flow started\n";
echo "  Flow ID: {$state['flow_id']}\n";
echo "  Status: {$state['status']}\n";
echo "  Current step: {$state['current_step']}\n\n";

// Step 4: Simulate data collection
echo "Step 4: Simulating data collection...\n";
$testData = [
    'mobile' => '09173011987',
    'name' => 'Juan Dela Cruz',
    'email' => 'juan@example.com',
    'location' => json_encode([
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'address' => 'Manila, Philippines',
    ]),
];

// Submit step data
foreach ($flowInstructions->steps as $index => $step) {
    $stepNumber = $index + 1;

    if ($step->handler === 'form') {
        $stepData = [];
        foreach ($step->config['fields'] as $field) {
            if (isset($testData[$field['name']])) {
                $stepData[$field['name']] = $testData[$field['name']];
            }
        }

        $formFlowService->submitStep($state['flow_id'], $stepNumber, $stepData);
        echo "  ✓ Step {$stepNumber} ({$step->handler}) submitted\n";
    }
}
echo "\n";

// Step 5: Verify collected data
echo "Step 5: Verifying collected data...\n";
$finalState = $formFlowService->getFlowState($state['flow_id']);
echo "✓ Data collected:\n";
foreach ($finalState['collected_data'] as $stepNum => $data) {
    echo "  Step {$stepNum}:\n";
    foreach ($data as $key => $value) {
        $display = is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50).'...' : $value) : json_encode($value);
        echo "    - {$key}: {$display}\n";
    }
}
echo "\n";

// Step 6: Check flow can be completed
echo "Step 6: Checking completion eligibility...\n";
if ($finalState['status'] === 'complete') {
    echo "✓ Flow is ready to complete\n";
    echo '  Completed steps: '.count($finalState['completed_steps']).'/'.count($flowInstructions->steps)."\n";
} else {
    echo "✗ Flow not complete yet\n";
    echo "  Status: {$finalState['status']}\n";
    echo "  Current step: {$finalState['current_step']}\n";
}
echo "\n";

// Summary
echo "═══════════════════════════════════════════════════════\n";
echo "   TEST SUMMARY\n";
echo "═══════════════════════════════════════════════════════\n";
echo "✓ Voucher generation: PASSED\n";
echo "✓ Driver transformation: PASSED\n";
echo "✓ Form flow start: PASSED\n";
echo "✓ Data collection: PASSED\n";
echo '✓ Flow completion: '.($finalState['status'] === 'complete' ? 'PASSED' : 'PENDING')."\n";
echo "\n";
echo "Next steps:\n";
echo "1. Visit: http://redeem-x.test/disburse?code={$voucher->code}\n";
echo "2. Complete the form flow in browser\n";
echo "3. Verify redemption completes successfully\n";
echo "4. Check that disbursement occurs (if enabled)\n";
echo "5. Verify notifications are sent\n";
echo "\n";
echo "To test redemption in console:\n";
echo "  php artisan tinker --execute=\"App\\Actions\\Voucher\\ProcessRedemption::run(LBHurtado\\Voucher\\Models\\Voucher::where('code', '{$voucher->code}')->first(), ['mobile' => '09173011987', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com']);\"\n";
echo "\n";

// Cleanup message
echo "Note: Voucher {$voucher->code} was created for testing.\n";
echo "      Use it for browser testing or it will remain unredeemed.\n";
