<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;

class GenerateTestVouchers extends Command
{
    protected $signature = 'test:vouchers
                            {--scenario= : Specific scenario to generate (bio|location|media|kyc|full)}';

    protected $description = 'Generate test vouchers with different input combinations for /disburse endpoint';

    public function handle()
    {
        $user = User::first();
        
        if (!$user) {
            $this->error('No users found. Please create a user first.');
            return 1;
        }

        $scenario = $this->option('scenario');
        
        if ($scenario) {
            $voucher = $this->generateScenario($scenario, $user);
            if ($voucher) {
                $this->displayVoucher($voucher, $scenario);
            }
        } else {
            $this->generateAllScenarios($user);
        }

        return 0;
    }

    protected function generateAllScenarios(User $user)
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('   GENERATING TEST VOUCHERS FOR ALL SCENARIOS');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $scenarios = [
            'bio' => 'Bio information (name, email, address, birthdate)',
            'location' => 'Location capture only',
            'media' => 'Media capture (selfie + signature)',
            'kyc' => 'KYC verification only',
            'full' => 'Complete flow (bio + location + media + kyc)',
        ];

        $vouchers = [];
        
        foreach ($scenarios as $key => $description) {
            $voucher = $this->generateScenario($key, $user);
            if ($voucher) {
                $vouchers[$key] = $voucher;
                $this->displayVoucher($voucher, $key);
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('   QUICK REFERENCE');
        $this->info('═══════════════════════════════════════════════════════');
        
        foreach ($vouchers as $key => $voucher) {
            $this->line(sprintf(
                '<fg=cyan>%-12s</> <fg=yellow>%s</> <fg=gray>→</> <fg=green>http://redeem-x.test/disburse?code=%s</>',
                strtoupper($key),
                $voucher->code,
                $voucher->code
            ));
        }
        $this->newLine();
    }

    protected function generateScenario(string $scenario, User $user)
    {
        $instructions = VoucherInstructionsData::generateFromScratch();
        $instructions->cash->amount = 100; // ₱1.00 for testing
        $instructions->cash->currency = 'PHP';
        $instructions->cash->settlement_rail = SettlementRail::INSTAPAY;
        $instructions->count = 1;
        $instructions->prefix = strtoupper($scenario);

        switch ($scenario) {
            case 'bio':
                // Bio information: name, email, address, birthdate
                $instructions->inputs->fields = ['name', 'email', 'address', 'birth_date'];
                break;

            case 'location':
                // Location capture only
                $instructions->inputs->fields = ['location'];
                break;

            case 'media':
                // Media capture: selfie + signature
                $instructions->inputs->fields = ['selfie', 'signature'];
                break;

            case 'kyc':
                // KYC verification only
                $instructions->inputs->fields = ['kyc'];
                break;

            case 'full':
                // Complete flow: everything
                $instructions->inputs->fields = [
                    'name',
                    'email',
                    'address',
                    'birth_date',
                    'location',
                    'selfie',
                    'signature',
                    'kyc',
                ];
                $instructions->prefix = 'FULL';
                break;

            default:
                $this->error("Unknown scenario: {$scenario}");
                return null;
        }

        // Temporarily authenticate as user to generate voucher
        auth()->login($user);
        $voucher = GenerateVouchers::run($instructions)->first();
        auth()->logout();

        return $voucher;
    }

    protected function displayVoucher($voucher, string $scenario)
    {
        $scenarioNames = [
            'bio' => 'Bio Information',
            'location' => 'Location Capture',
            'media' => 'Media Capture',
            'kyc' => 'KYC Verification',
            'full' => 'Complete Flow',
        ];

        $this->info("✓ {$scenarioNames[$scenario]}");
        $this->line("  Code: <fg=yellow>{$voucher->code}</>");
        $this->line("  Amount: <fg=green>{$voucher->formatted_amount}</>");
        
        $fields = $voucher->instructions->inputs->fields ?? [];
        if (is_array($fields) && count($fields) > 0) {
            // Convert enums to strings
            $fieldNames = array_map(fn($f) => is_object($f) ? $f->value : $f, $fields);
            $this->line("  Inputs: <fg=cyan>" . implode(', ', $fieldNames) . "</>");
        }
        
        $this->line("  URL: <fg=blue>http://redeem-x.test/disburse?code={$voucher->code}</>");
        $this->newLine();
    }
}
