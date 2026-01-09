<?php

use App\Listeners\UpdateContactKycStatus;
use Carbon\CarbonInterval;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Events\DisbursementRequested;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set config for contact package
    Config::set('contact.default.country', 'PH');
    Config::set('contact.default.bank_code', 'GXCHPHM2XXX');
    
    // Create a contact
    $this->contact = Contact::factory()->create([
        'mobile' => '09173011987',
        'country' => 'PH',
    ]);
    
    // Helper to create a voucher
    $this->createVoucher = function () {
        return Vouchers::withPrefix('TEST')
            ->withMask('***-***')
            ->withMetadata([
                'type' => 'test',
                'instructions' => [
                    'cash' => [
                        'amount' => 100,
                        'currency' => 'PHP',
                        'validation' => [
                            'secret' => null,
                            'mobile' => null,
                            'payable' => null,
                            'country' => null,
                            'location' => null,
                            'radius' => null,
                        ],
                        'settlement_rail' => null,
                        'fee_strategy' => 'absorb',
                    ],
                    'inputs' => [
                        'fields' => [],
                    ],
                    'feedback' => [
                        'email' => null,
                        'mobile' => null,
                        'webhook' => null,
                    ],
                    'rider' => [
                        'message' => null,
                        'url' => null,
                        'redirect_timeout' => null,
                        'splash' => null,
                        'splash_timeout' => null,
                    ],
                    'count' => 1,
                    'prefix' => 'TEST',
                    'mask' => '***-***',
                    'ttl' => 'PT1H',
                ],
            ])
            ->withExpireTimeIn(CarbonInterval::hours(1))
            ->create(1)
            ->first();
    };
});

test('updates contact KYC status when voucher has approved KYC data', function () {
    // Create a voucher and redeem it with contact
    $voucher = ($this->createVoucher)();
    Vouchers::redeem($voucher->code, $this->contact);
    $voucher->refresh();
    
    // Add KYC inputs to voucher
    $voucher->inputs()->create([
        'name' => 'transaction_id',
        'value' => 'formflow-flow-123-456',
    ]);
    
    $voucher->inputs()->create([
        'name' => 'status',
        'value' => 'approved',
    ]);
    
    // Dispatch event
    $event = new DisbursementRequested($voucher);
    $listener = new UpdateContactKycStatus();
    $listener->handle($event);
    
    // Assert contact was updated
    $this->contact->refresh();
    expect($this->contact->kyc_status)->toBe('approved')
        ->and($this->contact->kyc_transaction_id)->toBe('formflow-flow-123-456')
        ->and($this->contact->kyc_completed_at)->not->toBeNull();
});

test('does not update contact KYC status when status is not approved', function () {
    $voucher = ($this->createVoucher)();
    Vouchers::redeem($voucher->code, $this->contact);
    $voucher->refresh();
    
    $voucher->inputs()->create([
        'name' => 'transaction_id',
        'value' => 'formflow-flow-123-456',
    ]);
    
    $voucher->inputs()->create([
        'name' => 'status',
        'value' => 'pending',
    ]);
    
    $event = new DisbursementRequested($voucher);
    $listener = new UpdateContactKycStatus();
    $listener->handle($event);
    
    $this->contact->refresh();
    expect($this->contact->kyc_status)->toBeNull();
});

test('does nothing when voucher has no KYC data', function () {
    $voucher = ($this->createVoucher)();
    Vouchers::redeem($voucher->code, $this->contact);
    $voucher->refresh();
    
    // No KYC inputs
    $voucher->inputs()->create([
        'name' => 'name',
        'value' => 'John Doe',
    ]);
    
    $event = new DisbursementRequested($voucher);
    $listener = new UpdateContactKycStatus();
    $listener->handle($event);
    
    $this->contact->refresh();
    expect($this->contact->kyc_status)->toBeNull();
});

test('does nothing when voucher has no contact', function () {
    $voucher = ($this->createVoucher)();
    
    $voucher->inputs()->create([
        'name' => 'transaction_id',
        'value' => 'formflow-flow-123-456',
    ]);
    
    $voucher->inputs()->create([
        'name' => 'status',
        'value' => 'approved',
    ]);
    
    $event = new DisbursementRequested($voucher);
    $listener = new UpdateContactKycStatus();
    
    // Should not throw exception
    $listener->handle($event);
    
    expect(true)->toBeTrue();
});

test('handles exception gracefully when contact update fails', function () {
    $voucher = ($this->createVoucher)();
    Vouchers::redeem($voucher->code, $this->contact);
    $voucher->refresh();
    
    $voucher->inputs()->create([
        'name' => 'transaction_id',
        'value' => 'formflow-flow-123-456',
    ]);
    
    $voucher->inputs()->create([
        'name' => 'status',
        'value' => 'approved',
    ]);
    
    // Mock contact to throw exception on save
    $mockContact = Mockery::mock(Contact::class)->makePartial();
    $mockContact->shouldReceive('save')->andThrow(new \Exception('Database error'));
    
    $voucher->setRelation('contact', $mockContact);
    
    $event = new DisbursementRequested($voucher);
    $listener = new UpdateContactKycStatus();
    
    // Should not throw exception (logged as warning)
    $listener->handle($event);
    
    expect(true)->toBeTrue();
});

test('only processes KYC data with valid transaction ID format', function () {
    $voucher = ($this->createVoucher)();
    Vouchers::redeem($voucher->code, $this->contact);
    $voucher->refresh();
    
    // Invalid transaction ID format
    $voucher->inputs()->create([
        'name' => 'transaction_id',
        'value' => 'invalid-id',
    ]);
    
    $voucher->inputs()->create([
        'name' => 'status',
        'value' => 'approved',
    ]);
    
    $event = new DisbursementRequested($voucher);
    $listener = new UpdateContactKycStatus();
    $listener->handle($event);
    
    $this->contact->refresh();
    expect($this->contact->kyc_status)->toBeNull();
});
