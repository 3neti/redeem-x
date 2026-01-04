<?php

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Specifications\InputsSpecification;

describe('InputsSpecification', function () {
    it('returns true when no input fields are required', function () {
        // Mock voucher with no required inputs
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: []
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns true when all required input fields are present', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [
                                VoucherInputField::EMAIL,
                                VoucherInputField::NAME,
                            ];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                'name' => 'John Doe',
            ]
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns false when required input field is missing', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [
                                VoucherInputField::EMAIL,
                                VoucherInputField::NAME,
                            ];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                // Missing 'name'
            ]
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('returns false when required input field is empty string', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [VoucherInputField::EMAIL];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => '',  // Empty string
            ]
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('returns false when required input field is null', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [VoucherInputField::EMAIL];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => null,  // Null value
            ]
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('skips special fields like kyc and location', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [
                                VoucherInputField::KYC,
                                VoucherInputField::LOCATION,
                            ];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: []  // Empty - should pass because kyc/location are special
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns list of missing fields', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [
                                VoucherInputField::EMAIL,
                                VoucherInputField::NAME,
                                VoucherInputField::BIRTH_DATE,
                            ];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                // Missing 'name' and 'birth_date'
            ]
        );
        
        $specification = new InputsSpecification();
        $missing = $specification->getMissingFields($voucher, $context);
        
        expect($missing)->toBeArray()
            ->and($missing)->toHaveCount(2)
            ->and($missing)->toContain('name')
            ->and($missing)->toContain('birth_date');
    });

    it('handles mixed regular and special fields correctly', function () {
        $voucher = new class {
            public object $instructions;
            
            public function __construct() {
                $this->instructions = new class {
                    public object $inputs;
                    
                    public function __construct() {
                        $this->inputs = new class {
                            public array $fields = [
                                VoucherInputField::EMAIL,
                                VoucherInputField::KYC,  // Special
                                VoucherInputField::NAME,
                            ];
                        };
                    }
                };
            }
        };
        
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                'name' => 'John Doe',
                // KYC not needed here (handled by KycSpecification)
            ]
        );
        
        $specification = new InputsSpecification();
        
        expect($specification->passes($voucher, $context))->toBeTrue();
    });
});
