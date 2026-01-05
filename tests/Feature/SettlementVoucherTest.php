<?php

use App\Models\User;
use Laravel\Pennant\Feature;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable settlement vouchers feature flag for all tests
    Feature::activate('settlement-vouchers');
    
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    
    // Give user sufficient balance for generating vouchers
    $this->user->depositFloat(1000000); // ₱10,000
});

describe('Voucher Domain Guards', function () {
    it('allows payments on PAYABLE vouchers', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        expect($voucher->canAcceptPayment())->toBeTrue()
            ->and($voucher->canRedeem())->toBeFalse();
    });

    it('allows redemption on REDEEMABLE vouchers', function () {
        $voucher = createSettlementVoucher(VoucherType::REDEEMABLE, 1000.00);
        
        expect($voucher->canRedeem())->toBeTrue()
            ->and($voucher->canAcceptPayment())->toBeFalse();
    });

    it('allows both payments and redemption on SETTLEMENT vouchers', function () {
        $voucher = createSettlementVoucher(VoucherType::SETTLEMENT, 10000.00);
        
        expect($voucher->canAcceptPayment())->toBeTrue()
            ->and($voucher->canRedeem())->toBeTrue();
    });

    it('blocks payments on CLOSED vouchers', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        $voucher->update(['state' => VoucherState::CLOSED, 'closed_at' => now()]);
        
        expect($voucher->canAcceptPayment())->toBeFalse();
    });

    it('blocks payments on LOCKED vouchers', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        $voucher->update(['state' => VoucherState::LOCKED, 'locked_at' => now()]);
        
        expect($voucher->canAcceptPayment())->toBeFalse();
    });

    it('blocks payments on expired vouchers', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        $voucher->update(['expires_at' => now()->subDay()]);
        
        expect($voucher->canAcceptPayment())->toBeFalse();
    });
});

describe('Payment Tracking', function () {
    it('tracks paid total from wallet transactions', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        // Simulate two payments
        $voucher->cash->wallet->deposit(50000, ['flow' => 'pay', 'payment_id' => 'PAY-1']); // ₱500
        $voucher->cash->wallet->deposit(100000, ['flow' => 'pay', 'payment_id' => 'PAY-2']); // ₱1000
        
        $voucher->refresh();
        
        expect($voucher->getPaidTotal())->toBe(1500.00)
            ->and($voucher->getRemaining())->toBe(0.00);
    });

    it('calculates remaining amount correctly', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        $voucher->cash->wallet->deposit(50000, ['flow' => 'pay', 'payment_id' => 'PAY-1']); // ₱500
        
        $voucher->refresh();
        
        expect($voucher->getPaidTotal())->toBe(500.00)
            ->and($voucher->getRemaining())->toBe(1000.00);
    });

    it('tracks redeemed total separately from paid total', function () {
        $voucher = createSettlementVoucher(VoucherType::SETTLEMENT, 10000.00);
        
        // Pre-fund for initial disbursement
        $voucher->cash->wallet->deposit(1000000, ['flow' => 'fund', 'type' => 'initial_fund']);
        
        // Simulate payment from borrower
        $voucher->cash->wallet->deposit(200000, ['flow' => 'pay', 'payment_id' => 'PAY-1']); // ₱2000
        
        // Simulate redemption
        $voucher->cash->wallet->withdraw(500000, ['flow' => 'redeem', 'voucher_code' => $voucher->code]); // ₱5000
        
        $voucher->refresh();
        
        expect($voucher->getPaidTotal())->toBe(2000.00)
            ->and($voucher->getRedeemedTotal())->toBe(5000.00);
    });
});

describe('Webhook Payment Processing', function () {
    it('accepts valid voucher payment webhook', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        $response = $this->postJson('/webhooks/netbank/payment', [
            'reference_no' => $voucher->code,
            'payment_id' => 'NB-12345',
            'payment_status' => 'PAID',
            'amount' => [
                'value' => 500.00,
                'currency' => 'PHP',
            ],
        ]);
        
        $response->assertOk()
            ->assertJson([
                'message' => 'Voucher payment processed successfully',
                'voucher_code' => $voucher->code,
                'paid_total' => 500.00,
                'remaining' => 1000.00,
                'auto_closed' => false,
            ]);
        
        $voucher->refresh();
        expect($voucher->getPaidTotal())->toBe(500.00);
    });

    it('auto-closes voucher when fully paid', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        $response = $this->postJson('/webhooks/netbank/payment', [
            'reference_no' => $voucher->code,
            'payment_id' => 'NB-12345',
            'payment_status' => 'PAID',
            'amount' => [
                'value' => 1500.00,
                'currency' => 'PHP',
            ],
        ]);
        
        $response->assertOk()
            ->assertJson([
                'auto_closed' => true,
            ]);
        
        $voucher->refresh();
        expect($voucher->state)->toBe(VoucherState::CLOSED)
            ->and($voucher->closed_at)->not->toBeNull();
    });

    it('prevents duplicate payment processing', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        $payload = [
            'reference_no' => $voucher->code,
            'payment_id' => 'NB-12345',
            'payment_status' => 'PAID',
            'amount' => [
                'value' => 500.00,
                'currency' => 'PHP',
            ],
        ];
        
        // First payment
        $this->postJson('/webhooks/netbank/payment', $payload)->assertOk();
        
        // Duplicate payment
        $response = $this->postJson('/webhooks/netbank/payment', $payload);
        
        $response->assertOk()
            ->assertJson(['message' => 'Payment already processed']);
        
        $voucher->refresh();
        expect($voucher->getPaidTotal())->toBe(500.00); // Not doubled
    });

    it('rejects payment on non-payable voucher', function () {
        $voucher = createSettlementVoucher(VoucherType::REDEEMABLE, 1000.00);
        
        $response = $this->postJson('/webhooks/netbank/payment', [
            'reference_no' => $voucher->code,
            'payment_id' => 'NB-12345',
            'payment_status' => 'PAID',
            'amount' => [
                'value' => 500.00,
                'currency' => 'PHP',
            ],
        ]);
        
        $response->assertStatus(422)
            ->assertJson(['error' => 'Voucher cannot accept payments']);
    });

    it('rejects payment on closed voucher', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        $voucher->update(['state' => VoucherState::CLOSED, 'closed_at' => now()]);
        
        $response = $this->postJson('/webhooks/netbank/payment', [
            'reference_no' => $voucher->code,
            'payment_id' => 'NB-12345',
            'payment_status' => 'PAID',
            'amount' => [
                'value' => 500.00,
                'currency' => 'PHP',
            ],
        ]);
        
        $response->assertStatus(422)
            ->assertJson(['error' => 'Voucher cannot accept payments']);
    });
});

describe('Pay Page', function () {
    it('returns 404 when feature flag is disabled', function () {
        Feature::deactivate('settlement-vouchers');
        
        $response = $this->get('/pay');
        
        $response->assertNotFound();
    });

    it('shows pay page when feature flag is enabled', function () {
        $response = $this->get('/pay');
        
        $response->assertOk();
    });

    it('returns voucher quote for valid code', function () {
        $voucher = createSettlementVoucher(VoucherType::PAYABLE, 1500.00);
        
        $response = $this->postJson("/pay/quote", ['code' => $voucher->code]);
        
        $response->assertOk()
            ->assertJsonStructure([
                'voucher_code',
                'target_amount',
                'paid_total',
                'remaining',
            ]);
    });

    it('rejects quote for non-payable voucher', function () {
        $voucher = createSettlementVoucher(VoucherType::REDEEMABLE, 1000.00);
        
        $response = $this->postJson("/pay/quote", ['code' => $voucher->code]);
        
        $response->assertStatus(403)
            ->assertJson(['error' => 'This voucher cannot accept payments']);
    });
});

// Helper function to create settlement vouchers
function createSettlementVoucher(VoucherType $type, float $targetAmount): Voucher
{
    // Create instructions data
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: $targetAmount,
            currency: 'PHP',
            validation: new CashValidationRulesData(
                secret: null,
                mobile: null,
                payable: null,
                country: 'PH',
                location: null,
                radius: null
            ),
            settlement_rail: null,
            fee_strategy: 'absorb'
        ),
        inputs: new InputFieldsData([]),
        feedback: new FeedbackInstructionData(
            email: null,
            mobile: null,
            webhook: null
        ),
        rider: new RiderInstructionData(
            message: null,
            url: null,
            redirect_timeout: null,
            splash: null,
            splash_timeout: null
        ),
        count: 1,
        prefix: 'TEST',
        mask: '****',
        ttl: null,
        validation: null,
        metadata: null,
        voucher_type: $type,
        target_amount: $targetAmount,
        rules: null
    );
    
    // Generate voucher using the action (this creates cash entity automatically)
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();
    
    // Set settlement-specific fields directly
    $voucher->voucher_type = $type;
    $voucher->state = VoucherState::ACTIVE;
    $voucher->target_amount = $targetAmount;
    $voucher->save();
    
    return $voucher->fresh();
}
