<?php

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Specifications\KycSpecification;

beforeEach(function () {
    $this->spec = new KycSpecification();
});

describe('KycSpecification', function () {
    it('passes when KYC is not required', function () {
        $voucher = createVoucherWithoutKyc();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when inputs.fields is empty', function () {
        $voucher = createVoucherWithEmptyInputs();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when KYC field is not in inputs.fields', function () {
        $voucher = createVoucherWithInputsButNoKyc();
        $context = new RedemptionContext(mobile: '09171234567', inputs: [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('fails when KYC is required but not provided', function () {
        $voucher = createVoucherWithKycRequired();
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: []
        );
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('fails when KYC status is not approved', function () {
        $voucher = createVoucherWithKycRequired();
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'pending',
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('passes when KYC status is approved', function () {
        $voucher = createVoucherWithKycRequired();
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'approved',
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('fails when KYC is rejected', function () {
        $voucher = createVoucherWithKycRequired();
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'rejected',
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('fails when KYC is needs_review', function () {
        $voucher = createVoucherWithKycRequired();
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'needs_review',
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('handles KYC status as string (directly)', function () {
        $voucher = createVoucherWithKycRequired();
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'approved', // Direct string
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('is case-sensitive for status', function () {
        $voucher = createVoucherWithKycRequired();
        
        // Uppercase should fail
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'APPROVED',
            ]
        );
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        // Mixed case should fail
        $context2 = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'kyc_status' => 'Approved',
            ]
        );
        expect($this->spec->passes($voucher, $context2))->toBeFalse();
    });
});

// Helper functions
function createVoucherWithoutKyc(): object
{
    return (object) [
        'instructions' => (object) [
            'inputs' => (object) [
                'fields' => [],
            ],
        ],
    ];
}

function createVoucherWithEmptyInputs(): object
{
    return (object) [
        'instructions' => (object) [
            'inputs' => (object) [
                'fields' => [],
            ],
        ],
    ];
}

function createVoucherWithInputsButNoKyc(): object
{
    return (object) [
        'instructions' => (object) [
            'inputs' => (object) [
                'fields' => ['name', 'email'],
            ],
        ],
    ];
}

function createVoucherWithKycRequired(): object
{
    return (object) [
        'instructions' => (object) [
            'inputs' => (object) [
                'fields' => ['kyc', 'name', 'email'],
            ],
        ],
    ];
}
