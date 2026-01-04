<?php

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Specifications\LocationSpecification;

beforeEach(function () {
    $this->spec = new LocationSpecification();
});

describe('LocationSpecification', function () {
    it('passes when no location validation is configured', function () {
        $voucher = createVoucherWithoutLocationValidation();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when location validation is incomplete (no coordinates)', function () {
        $voucher = createVoucherWithIncompleteLocationValidation();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('fails when location is required but not provided', function () {
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5995,
            lng: 120.9842,
            radius: '1000m'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('fails when location data is invalid (missing coordinates)', function () {
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5995,
            lng: 120.9842,
            radius: '1000m'
        );
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: ['location' => ['address' => 'Makati']] // Missing lat/lng
        );
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('passes when location is within radius (using lat/lng)', function () {
        // Makati City Hall coordinates
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5547,
            lng: 121.0244,
            radius: '1000m'
        );
        
        // User location 500m away
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'lat' => 14.5592,
                    'lng' => 121.0244,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when location is within radius (using latitude/longitude)', function () {
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5547,
            lng: 121.0244,
            radius: '1km'
        );
        
        // User location using 'latitude'/'longitude' keys
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'latitude' => 14.5592,
                    'longitude' => 121.0244,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('fails when location is outside radius', function () {
        // Makati City Hall
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5547,
            lng: 121.0244,
            radius: '500m'
        );
        
        // User location 2km away (Guadalupe)
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'lat' => 14.5723,
                    'lng' => 121.0448,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
    });
    
    it('handles different radius formats (meters)', function () {
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5547,
            lng: 121.0244,
            radius: '1500m'
        );
        
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'lat' => 14.5547,
                    'lng' => 121.0244,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('handles different radius formats (kilometers)', function () {
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5547,
            lng: 121.0244,
            radius: '3km' // Increased to accommodate actual distance
        );
        
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'lat' => 14.5723,
                    'lng' => 121.0448,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('handles radius without unit (assumes meters)', function () {
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5547,
            lng: 121.0244,
            radius: '3000' // No unit = meters, increased to accommodate actual distance
        );
        
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'lat' => 14.5723,
                    'lng' => 121.0448,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('calculates distance accurately using Haversine formula', function () {
        // Known distance between two Manila locations
        // EDSA Shrine (14.5836, 121.0560) to SM Megamall (14.5850, 121.0564)
        // Distance: ~155 meters
        
        $voucher = createVoucherWithLocationValidation(
            lat: 14.5836,
            lng: 121.0560,
            radius: '200m'
        );
        
        $context = new RedemptionContext(
            mobile: '09171234567',
            inputs: [
                'location' => [
                    'lat' => 14.5850,
                    'lng' => 121.0564,
                ]
            ]
        );
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        // Now test with smaller radius
        $strictVoucher = createVoucherWithLocationValidation(
            lat: 14.5836,
            lng: 121.0560,
            radius: '100m' // Too small
        );
        
        expect($this->spec->passes($strictVoucher, $context))->toBeFalse();
    });
});

// Helper functions
function createVoucherWithoutLocationValidation(): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [],
        ],
    ];
}

function createVoucherWithIncompleteLocationValidation(): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [
                'location' => (object) [
                    'radius' => '1000m',
                    // Missing coordinates
                ],
            ],
        ],
    ];
}

function createVoucherWithLocationValidation(float $lat, float $lng, string $radius): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [
                'location' => (object) [
                    'coordinates' => [
                        'lat' => $lat,
                        'lng' => $lng,
                    ],
                    'radius' => $radius,
                ],
            ],
        ],
    ];
}
