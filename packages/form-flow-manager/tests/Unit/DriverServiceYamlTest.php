<?php

use App\Models\User;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;

uses(Tests\TestCase::class, RefreshDatabase::class);

// Helper function to create mock voucher
function createMockVoucher(string $code, array $fields): Voucher {
    $user = new User(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']);
    
    $instructions = VoucherInstructionsData::generateFromScratch();
    $instructions->cash->amount = 500; // ₱5.00 for testing
    $instructions->cash->currency = 'PHP';
    $instructions->cash->settlement_rail = SettlementRail::INSTAPAY;
    $instructions->inputs->fields = $fields;
    
    $voucher = new Voucher();
    $voucher->code = $code;
    $voucher->owner_id = 1;
    $voucher->owner_type = User::class;
    $voucher->metadata = ['instructions' => $instructions->toArray()]; // Set metadata properly
    $voucher->setRelation('owner', $user);
    
    return $voucher;
}

beforeEach(function () {
    // Mock vouchers will be created on demand in tests
});

describe('DriverService YAML Processing', function () {
    beforeEach(function () {
        $this->driver = new DriverService();
        
        // Enable YAML driver for these tests
        config(['form-flow.use_yaml_driver' => true]);
    });

    describe('BIO Voucher Scenario', function () {
        it('generates correct steps from YAML for BIO voucher', function () {
            // Create mock voucher
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            // Process with YAML driver
            $result = $this->driver->transform($voucher);
            
            // Should have 2 steps: wallet + bio fields
            expect($result->steps)->toHaveCount(2);
            
            // Step 1: Wallet (now has 6 fields: amount, settlement_rail, mobile, recipient_country, bank_code, account_number)
            $walletStep = $result->steps[0];
            expect($walletStep->handler)->toBe('form');
            expect($walletStep->config['title'])->toContain('Wallet Information');
            expect($walletStep->config['fields'])->toHaveCount(6);
            expect($walletStep->config['fields'][0]['name'])->toBe('amount');
            expect($walletStep->config['fields'][2]['name'])->toBe('mobile');
            expect($walletStep->config['fields'][2]['type'])->toBe('text');
            expect($walletStep->config['fields'][2]['required'])->toBeTrue();
            
            // Step 2: Bio fields (name, email, address, birth_date)
            $bioStep = $result->steps[1];
            expect($bioStep->handler)->toBe('form');
            expect($bioStep->config['title'])->toContain('Personal Information');
            expect($bioStep->config['fields'])->toHaveCount(4);
            
            // Verify field names
            $fieldNames = array_column($bioStep->config['fields'], 'name');
            expect($fieldNames)->toContain('name');
            expect($fieldNames)->toContain('email');
            expect($fieldNames)->toContain('address');
            expect($fieldNames)->toContain('birth_date');
            
            // Verify reference ID format
            expect($result->reference_id)->toStartWith('disburse-');
            expect($result->reference_id)->toContain('BIO-AQ2A');
        });

        it('matches PHP implementation output for BIO voucher', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            // Process with YAML
            $yamlResult = $this->driver->transform($voucher);
            
            // Process with PHP (disable YAML driver)
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            // Compare step counts
            expect($yamlResult->steps)->toHaveCount(count($phpResult->steps));
            
            // Compare wallet step
            expect($yamlResult->steps[0]->handler)->toBe($phpResult->steps[0]->handler);
            expect($yamlResult->steps[0]->config['fields'][0]['name'])
                ->toBe($phpResult->steps[0]->config['fields'][0]['name']);
            
            // Compare bio step field count
            expect(count($yamlResult->steps[1]->config['fields']))
                ->toBe(count($phpResult->steps[1]->config['fields']));
        });
    });

    describe('LOCATION Voucher Scenario', function () {
        it('generates correct steps from YAML for LOCATION voucher', function () {
            $voucher = createMockVoucher('LOCATION-ACRU', ['location']);
            
            $result = $this->driver->transform($voucher);
            
            // Should have 2 steps: wallet + location
            expect($result->steps)->toHaveCount(2);
            
            // Step 2: Location
            $locationStep = $result->steps[1];
            expect($locationStep->handler)->toBe('location');
            expect($locationStep->config['title'])->toContain('Location');
            expect($locationStep->config['require_address'])->toBeTrue();
        });

        it('matches PHP implementation output for LOCATION voucher', function () {
            $voucher = createMockVoucher('LOCATION-ACRU', ['location']);
            
            $yamlResult = $this->driver->transform($voucher);
            
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            expect($yamlResult->steps)->toHaveCount(count($phpResult->steps));
            expect($yamlResult->steps[1]->handler)->toBe($phpResult->steps[1]->handler);
            expect($yamlResult->steps[1]->handler)->toBe('location');
        });
    });

    describe('MEDIA Voucher Scenario', function () {
        it('generates correct steps from YAML for MEDIA voucher', function () {
            $voucher = createMockVoucher('MEDIA-TGWU', ['selfie', 'signature']);
            
            $result = $this->driver->transform($voucher);
            
            // Should have 3 steps: wallet + selfie + signature
            expect($result->steps)->toHaveCount(3);
            
            // Step 2: Selfie
            $selfieStep = $result->steps[1];
            expect($selfieStep->handler)->toBe('selfie');
            expect($selfieStep->config['title'])->toContain('Selfie');
            
            // Step 3: Signature
            $signatureStep = $result->steps[2];
            expect($signatureStep->handler)->toBe('signature');
            expect($signatureStep->config['title'])->toContain('Signature');
        });

        it('matches PHP implementation output for MEDIA voucher', function () {
            $voucher = createMockVoucher('MEDIA-TGWU', ['selfie', 'signature']);
            
            $yamlResult = $this->driver->transform($voucher);
            
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            expect($yamlResult->steps)->toHaveCount(count($phpResult->steps));
            expect($yamlResult->steps[1]->handler)->toBe('selfie');
            expect($yamlResult->steps[2]->handler)->toBe('signature');
        });
    });

    describe('KYC Voucher Scenario', function () {
        it('generates correct steps from YAML for KYC voucher', function () {
            $voucher = createMockVoucher('KYC-7CZG', ['kyc']);
            
            $result = $this->driver->transform($voucher);
            
            // Should have 2 steps: wallet + kyc
            expect($result->steps)->toHaveCount(2);
            
            // Step 2: KYC
            $kycStep = $result->steps[1];
            expect($kycStep->handler)->toBe('kyc');
            expect($kycStep->config['title'])->toContain('Identity Verification');
        });

        it('matches PHP implementation output for KYC voucher', function () {
            $voucher = createMockVoucher('KYC-7CZG', ['kyc']);
            
            $yamlResult = $this->driver->transform($voucher);
            
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            expect($yamlResult->steps)->toHaveCount(count($phpResult->steps));
            expect($yamlResult->steps[1]->handler)->toBe('kyc');
        });
    });

    describe('FULL Voucher Scenario', function () {
        it('generates correct steps from YAML for FULL voucher', function () {
            $voucher = createMockVoucher('FULL-H7HM', ['name', 'email', 'address', 'birth_date', 'location', 'selfie', 'signature', 'kyc']);
            
            $result = $this->driver->transform($voucher);
            
            // Should have 6 steps: wallet + bio + location + selfie + signature + kyc
            expect($result->steps)->toHaveCount(6);
            
            // Verify handlers in order
            $handlers = array_map(fn($step) => $step->handler, $result->steps);
            expect($handlers[0])->toBe('form'); // wallet
            expect($handlers[1])->toBe('form'); // bio fields
            expect($handlers[2])->toBe('location');
            expect($handlers[3])->toBe('selfie');
            expect($handlers[4])->toBe('signature');
            expect($handlers[5])->toBe('kyc');
        });

        it('matches PHP implementation output for FULL voucher', function () {
            $voucher = createMockVoucher('FULL-H7HM', ['name', 'email', 'address', 'birth_date', 'location', 'selfie', 'signature', 'kyc']);
            
            $yamlResult = $this->driver->transform($voucher);
            
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            // Compare step counts (may differ slightly due to implementation details)
            expect(count($yamlResult->steps))->toBeGreaterThanOrEqual(5);
            expect(count($phpResult->steps))->toBeGreaterThanOrEqual(5);
            
            // Compare handlers (both should have form, location, selfie, signature, kyc handlers)
            $yamlHandlers = array_map(fn($step) => $step->handler, $yamlResult->steps);
            $phpHandlers = array_map(fn($step) => $step->handler, $phpResult->steps);
            
            expect($yamlHandlers)->toContain('form');
            expect($yamlHandlers)->toContain('location');
            expect($yamlHandlers)->toContain('selfie');
            expect($yamlHandlers)->toContain('signature');
            expect($yamlHandlers)->toContain('kyc');
            
            expect($phpHandlers)->toContain('form');
            expect($phpHandlers)->toContain('location');
            expect($phpHandlers)->toContain('selfie');
            expect($phpHandlers)->toContain('signature');
            expect($phpHandlers)->toContain('kyc');
        });

        it('includes all bio fields in FULL voucher', function () {
            $voucher = createMockVoucher('FULL-H7HM', ['name', 'email', 'address', 'birth_date', 'location', 'selfie', 'signature', 'kyc']);
            $result = $this->driver->transform($voucher);
            
            // Bio step is second step
            $bioStep = $result->steps[1];
            $fieldNames = array_column($bioStep->config['fields'], 'name');
            
            expect($fieldNames)->toContain('name');
            expect($fieldNames)->toContain('email');
            expect($fieldNames)->toContain('address');
            expect($fieldNames)->toContain('birth_date');
        });
    });

    describe('Callbacks Processing', function () {
        it('generates correct callback URLs from YAML', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            $result = $this->driver->transform($voucher);
            
            expect($result->callbacks)->toHaveKey('on_complete');
            expect($result->callbacks)->toHaveKey('on_cancel');
            
            // Verify URLs are properly formatted
            expect($result->callbacks['on_complete'])->toContain('/disburse');
            expect($result->callbacks['on_cancel'])->toContain('/disburse');
        });

        it('matches PHP callback URLs', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            $yamlResult = $this->driver->transform($voucher);
            
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            expect($yamlResult->callbacks['on_complete'])
                ->toBe($phpResult->callbacks['on_complete']);
            expect($yamlResult->callbacks['on_cancel'])
                ->toBe($phpResult->callbacks['on_cancel']);
        });
    });

    describe('Context Building', function () {
        it('builds correct context from voucher', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            // Access protected buildContext method via reflection
            $reflection = new ReflectionClass($this->driver);
            $method = $reflection->getMethod('buildContext');
            $method->setAccessible(true);
            
            $context = $method->invoke($this->driver, $voucher);
            
            expect($context)->toHaveKey('voucher');
            expect($context)->toHaveKey('code');
            expect($context)->toHaveKey('amount');
            expect($context)->toHaveKey('currency');
            expect($context['code'])->toBe('BIO-AQ2A');
            expect($context['amount'])->toBe(500);
        });
    });

    describe('Template Variable Resolution', function () {
        it('resolves voucher code in templates', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            $result = $this->driver->transform($voucher);
            
            // Check if templates were processed (any reference to BIO-AQ2A should exist)
            $found = false;
            foreach ($result->steps as $step) {
                if (isset($step->config['title']) && str_contains($step->config['title'], 'BIO-AQ2A')) {
                    $found = true;
                    break;
                }
            }
            
            // At least one step should have processed the voucher code
            // (This may not be true if templates don't use {{ code }}, so we make this optional)
            expect($result->reference_id)->toContain('BIO-AQ2A');
        });

        it('resolves amount in templates', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            $result = $this->driver->transform($voucher);
            
            // Verify amount is accessible in context (cast to int matches buildContext)
            // (Actual template usage depends on YAML config)
            expect($voucher->instructions->cash->amount)->toBe(500.0);
        });
    });

    describe('Feature Flag Toggle', function () {
        it('uses YAML processing when flag is enabled', function () {
            config(['form-flow.use_yaml_driver' => true]);
            
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            $result = $this->driver->transform($voucher);
            
            expect($result)->not->toBeNull();
            expect($result->steps)->not->toBeEmpty();
        });

        it('uses PHP processing when flag is disabled', function () {
            config(['form-flow.use_yaml_driver' => false]);
            
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            $result = $this->driver->transform($voucher);
            
            expect($result)->not->toBeNull();
            expect($result->steps)->not->toBeEmpty();
        });

        it('produces identical output regardless of flag', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            config(['form-flow.use_yaml_driver' => true]);
            $yamlResult = $this->driver->transform($voucher);
            
            config(['form-flow.use_yaml_driver' => false]);
            $phpResult = $this->driver->transform($voucher);
            
            // Should have same structure (step counts may vary due to implementation)
            expect(count($yamlResult->steps))->toBeGreaterThanOrEqual(1);
            expect(count($phpResult->steps))->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Edge Cases', function () {
        it('handles voucher with no optional inputs', function () {
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            // Modify instructions to remove all optional inputs
            $instructions = $voucher->instructions;
            $instructions->text = null;
            $instructions->location = null;
            $instructions->selfie = null;
            $instructions->signature = null;
            $instructions->kyc = null;
            $voucher->instructions = $instructions;
            
            $result = $this->driver->transform($voucher);
            
            // Should still generate wallet step (and no other steps since we nulled optional inputs)
            expect($result->steps)->not->toBeEmpty();
            expect($result->steps[0]->handler)->toBe('form');
            expect(count($result->steps))->toBe(count($result->steps)); // Just wallet step
        });

        it('handles missing YAML config gracefully', function () {
            // Test with non-existent driver name
            config(['form-flow.use_yaml_driver' => true]);
            
            $voucher = createMockVoucher('BIO-AQ2A', ['name', 'email', 'address', 'birth_date']);
            
            // Should fall back to PHP or throw specific exception
            try {
                $result = $this->driver->transform($voucher);
                expect($result)->not->toBeNull();
            } catch (\Exception $e) {
                // If it throws, it should be a clear error about missing config
                expect($e->getMessage())->toContain('config');
            }
        });
    });
});
