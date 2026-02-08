#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;

$codes = ['BIO-SYHZ', 'LOCATION-NNP8', 'MEDIA-VLM3', 'KYC-3L9M', 'FULL-QNEZ'];
$driver = app(DriverService::class);

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║  DISBURSE ENDPOINT - ALL SCENARIOS TEST              ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

foreach ($codes as $code) {
    $voucher = Voucher::where('code', $code)->first();
    if (! $voucher) {
        echo "⚠️  Voucher {$code} not found\n\n";

        continue;
    }

    $instructions = $driver->transform($voucher);

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 {$code}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Amount: {$voucher->formatted_amount}\n";
    echo 'Inputs: '.implode(', ', array_map(
        fn ($f) => is_object($f) && isset($f->value) ? $f->value : $f,
        $voucher->instructions->inputs->fields ?? []
    ))."\n";
    echo 'Steps: '.count($instructions->steps)."\n\n";

    foreach ($instructions->steps as $index => $step) {
        $num = $index + 1;
        echo "  Step {$num}: {$step->handler}";
        if (isset($step->config['title'])) {
            echo " - {$step->config['title']}";
        }
        echo "\n";

        if ($step->handler === 'form' && isset($step->config['fields'])) {
            foreach ($step->config['fields'] as $field) {
                $req = ($field['required'] ?? false) ? '*' : '';
                echo "    → {$field['name']} ({$field['type']}){$req}\n";
            }
        }
    }

    echo "\n  🌐 Test URL:\n";
    echo "  http://redeem-x.test/disburse?code={$code}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "  SUMMARY - QUICK REFERENCE\n";
echo "═══════════════════════════════════════════════════════\n\n";

$scenarios = [
    'BIO-SYHZ' => 'Bio (name, email, address, birthdate)',
    'LOCATION-NNP8' => 'Location capture',
    'MEDIA-VLM3' => 'Media (selfie + signature)',
    'KYC-3L9M' => 'KYC verification',
    'FULL-QNEZ' => 'Complete flow (all inputs)',
];

foreach ($scenarios as $code => $description) {
    echo sprintf("%-15s %s\n", $code, $description);
    echo "                http://redeem-x.test/disburse?code={$code}\n\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "  TESTING INSTRUCTIONS\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "1. Open each URL in your browser\n";
echo "2. Complete the form flow:\n";
echo "   - Enter mobile number (wallet step)\n";
echo "   - Complete additional inputs as required\n";
echo "3. Verify:\n";
echo "   ✓ All steps display correctly\n";
echo "   ✓ Form validation works\n";
echo "   ✓ Success page shows voucher details\n";
echo "   ✓ Redemption is recorded in database\n\n";

echo "Note: Set DISBURSE_DISABLE=true to test without actual\n";
echo "      disbursement to payment gateway.\n\n";
