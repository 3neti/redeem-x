<?php

use App\Services\VoucherRedemptionService;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Exceptions\RedemptionException;

describe('VoucherRedemptionService - Inputs Error Messages', function () {
    it('provides detailed error message with specific missing fields', function () {
        // Mock voucher requiring email and name
        $voucher = new class
        {
            public object $instructions;

            public function __construct()
            {
                $this->instructions = new class
                {
                    public object $cash;

                    public object $inputs;

                    public function __construct()
                    {
                        $this->cash = new class
                        {
                            public object $validation;

                            public function __construct()
                            {
                                $this->validation = new class
                                {
                                    public ?string $secret = null;

                                    public ?string $mobile = null;

                                    public ?string $payable = null;
                                };
                            }
                        };

                        $this->inputs = new class
                        {
                            public array $fields = [
                                VoucherInputField::EMAIL,
                                VoucherInputField::NAME,
                            ];
                        };
                    }
                };
            }
        };

        // Context missing 'name'
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                // Missing 'name'
            ]
        );

        $service = new VoucherRedemptionService;

        try {
            $service->validateRedemption($voucher, $context);
            $this->fail('Expected RedemptionException to be thrown');
        } catch (RedemptionException $e) {
            expect($e->getMessage())->toContain('Missing required fields: Name.');
        }
    });

    it('lists multiple missing fields with proper formatting', function () {
        // Mock voucher requiring email, name, and birth_date
        $voucher = new class
        {
            public object $instructions;

            public function __construct()
            {
                $this->instructions = new class
                {
                    public object $cash;

                    public object $inputs;

                    public function __construct()
                    {
                        $this->cash = new class
                        {
                            public object $validation;

                            public function __construct()
                            {
                                $this->validation = new class
                                {
                                    public ?string $secret = null;

                                    public ?string $mobile = null;

                                    public ?string $payable = null;
                                };
                            }
                        };

                        $this->inputs = new class
                        {
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

        // Context with only email
        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                // Missing 'name' and 'birth_date'
            ]
        );

        $service = new VoucherRedemptionService;

        try {
            $service->validateRedemption($voucher, $context);
            $this->fail('Expected RedemptionException to be thrown');
        } catch (RedemptionException $e) {
            $message = $e->getMessage();

            expect($message)->toContain('Missing required fields:')
                ->and($message)->toContain('Name')
                ->and($message)->toContain('Birth Date')
                ->and($message)->toContain(' and '); // Proper list formatting
        }
    });

    it('formats snake_case field names to Title Case', function () {
        $voucher = new class
        {
            public object $instructions;

            public function __construct()
            {
                $this->instructions = new class
                {
                    public object $cash;

                    public object $inputs;

                    public function __construct()
                    {
                        $this->cash = new class
                        {
                            public object $validation;

                            public function __construct()
                            {
                                $this->validation = new class
                                {
                                    public ?string $secret = null;

                                    public ?string $mobile = null;

                                    public ?string $payable = null;
                                };
                            }
                        };

                        $this->inputs = new class
                        {
                            public array $fields = [
                                VoucherInputField::BIRTH_DATE,
                                VoucherInputField::GROSS_MONTHLY_INCOME,
                            ];
                        };
                    }
                };
            }
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: []
        );

        $service = new VoucherRedemptionService;

        try {
            $service->validateRedemption($voucher, $context);
            $this->fail('Expected RedemptionException to be thrown');
        } catch (RedemptionException $e) {
            $message = $e->getMessage();

            // Should format snake_case to Title Case
            expect($message)->toContain('Birth Date')
                ->and($message)->toContain('Gross Monthly Income');
        }
    });
});
