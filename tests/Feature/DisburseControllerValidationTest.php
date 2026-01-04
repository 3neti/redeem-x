<?php

use App\Models\User;
use App\Models\VendorAlias;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Test DisburseController integration with Unified Validation Gateway.
 * 
 * Verifies that payable validation correctly blocks unauthenticated
 * and wrong-vendor redemptions via the /disburse path.
 */

beforeEach(function () {
    // Create issuer with sufficient balance
    $this->issuer = User::factory()->create(['name' => 'Issuer']);
    $this->issuer->deposit(10000 * 100); // ₱10,000

    // Create merchant with vendor alias "BB"
    $this->merchantBB = User::factory()->create(['name' => 'Merchant BB']);
    VendorAlias::factory()->create([
        'user_id' => $this->merchantBB->id,
        'alias' => 'BB',
        'is_primary' => true,
    ]);

    // Create merchant with different vendor alias
    $this->merchantXYZ = User::factory()->create(['name' => 'Merchant XYZ']);
    VendorAlias::factory()->create([
        'user_id' => $this->merchantXYZ->id,
        'alias' => 'XYZ',
        'is_primary' => true,
    ]);

    // Generate voucher payable to "BB"
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => [
                'payable' => 'BB',
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
    ]);

    $vouchers = GenerateVouchers::run($instructions);
    $this->voucher = $vouchers->first();

    // Wait for processing
    $this->voucher->refresh();
});

test('it blocks unauthenticated redemption of payable voucher', function () {
    // Simulate form flow completion
        $formFlowState = [
            'flow_id' => 'test-flow-123',
            'status' => 'completed',
            'collected_data' => [
                'wallet' => [
                    'mobile' => '+639171234567',
                    'recipient_country' => 'PH',
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09171234567',
                ],
            ],
        ];

        // Mock form flow service
        $this->mock(\LBHurtado\FormFlowManager\Services\FormFlowService::class)
            ->shouldReceive('getFlowStateByReference')
            ->andReturn($formFlowState);

        // Attempt unauthenticated redemption
        $response = $this->post(route('disburse.redeem', ['voucher' => $this->voucher->code]), [
            'reference_id' => 'test-ref-123',
        ]);

    // Should redirect back with error
    $response->assertRedirect(route('disburse.start'));
    $response->assertSessionHasErrors();
    
    $errors = session('errors')->getBag('default')->all();
    expect(implode(' ', $errors))
        ->toContain('payable to merchant "BB"')
        ->toContain('log in');

    // Voucher should NOT be redeemed
    // Voucher should NOT be redeemed
    $this->voucher->refresh();
    expect($this->voucher->isRedeemed())->toBeFalse();
});

test('it blocks wrong vendor alias redemption', function () {
        // Login as merchant XYZ (wrong vendor)
        $this->actingAs($this->merchantXYZ);

        // Simulate form flow completion
        $formFlowState = [
            'flow_id' => 'test-flow-456',
            'status' => 'completed',
            'collected_data' => [
                'wallet' => [
                    'mobile' => '+639171234567',
                    'recipient_country' => 'PH',
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09171234567',
                ],
            ],
        ];

        $this->mock(\LBHurtado\FormFlowManager\Services\FormFlowService::class)
            ->shouldReceive('getFlowStateByReference')
            ->andReturn($formFlowState);

        // Attempt redemption with wrong vendor
        $response = $this->post(route('disburse.redeem', ['voucher' => $this->voucher->code]), [
            'reference_id' => 'test-ref-456',
        ]);

    // Should redirect back with error
    $response->assertRedirect(route('disburse.start'));
    $response->assertSessionHasErrors();
    
    $errors = session('errors')->getBag('default')->all();
    expect(implode(' ', $errors))->toContain('payable to merchant "BB"');

    // Voucher should NOT be redeemed
    $this->voucher->refresh();
    expect($this->voucher->isRedeemed())->toBeFalse();
});

test('it allows correct vendor alias redemption', function () {
        // Login as merchant BB (correct vendor)
        $this->actingAs($this->merchantBB);

        // Simulate form flow completion
        $formFlowState = [
            'flow_id' => 'test-flow-789',
            'status' => 'completed',
            'collected_data' => [
                'wallet' => [
                    'mobile' => '+639171234567',
                    'recipient_country' => 'PH',
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09171234567',
                ],
            ],
        ];

        $this->mock(\LBHurtado\FormFlowManager\Services\FormFlowService::class)
            ->shouldReceive('getFlowStateByReference')
            ->andReturn($formFlowState)
            ->shouldReceive('clearFlow')
            ->andReturn(true);

        // Attempt redemption with correct vendor
        $response = $this->post(route('disburse.redeem', ['voucher' => $this->voucher->code]), [
            'reference_id' => 'test-ref-789',
        ]);

    // Should redirect to success
    $response->assertRedirect(route('disburse.success', ['voucher' => $this->voucher->code]));
    $response->assertSessionHas('success');

    // Voucher SHOULD be redeemed
    $this->voucher->refresh();
    expect($this->voucher->isRedeemed())->toBeTrue();
});

test('it allows unauthenticated redemption of unrestricted voucher', function () {
    // Generate voucher WITHOUT payable restriction
        $instructions = VoucherInstructionsData::from([
            'cash' => [
                'amount' => 50,
                'currency' => 'PHP',
                'validation' => [
                    'payable' => null, // No restriction
                    'country' => 'PH',
                ],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
            'count' => 1,
            'prefix' => 'FREE',
        ]);

        $vouchers = GenerateVouchers::run($instructions);
        $freeVoucher = $vouchers->first();
        $freeVoucher->refresh();

        // Simulate form flow completion
        $formFlowState = [
            'flow_id' => 'test-flow-000',
            'status' => 'completed',
            'collected_data' => [
                'wallet' => [
                    'mobile' => '+639171234567',
                    'recipient_country' => 'PH',
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09171234567',
                ],
            ],
        ];

        $this->mock(\LBHurtado\FormFlowManager\Services\FormFlowService::class)
            ->shouldReceive('getFlowStateByReference')
            ->andReturn($formFlowState)
            ->shouldReceive('clearFlow')
            ->andReturn(true);

        // Attempt unauthenticated redemption (should work!)
        $response = $this->post(route('disburse.redeem', ['voucher' => $freeVoucher->code]), [
            'reference_id' => 'test-ref-000',
        ]);

        // Should redirect to success
        $response->assertRedirect(route('disburse.success', ['voucher' => $freeVoucher->code]));
        $response->assertSessionHas('success');

        // Voucher SHOULD be redeemed
    $freeVoucher->refresh();
    expect($freeVoucher->isRedeemed())->toBeTrue();
});
