<?php

use LBHurtado\FormFlowManager\Services\TemplateProcessor;

describe('TemplateProcessor', function () {
    beforeEach(function () {
        $this->processor = new TemplateProcessor();
        $this->context = [
            'voucher' => [
                'code' => 'BIO-AQ2A',
                'instructions' => [
                    'cash' => [
                        'amount' => 500,
                        'currency' => 'PHP',
                    ],
                ],
            ],
            'amount' => 500,
            'currency' => 'PHP',
            'code' => 'BIO-AQ2A',
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ];
    });

    describe('Simple Variable Replacement', function () {
        it('replaces simple variables', function () {
            $template = 'Hello {{ code }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Hello BIO-AQ2A');
        });

        it('replaces multiple variables', function () {
            $template = 'Code: {{ code }}, Amount: {{ amount }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Code: BIO-AQ2A, Amount: 500');
        });

        it('handles variables with spaces', function () {
            $template = 'Hello {{  code  }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Hello BIO-AQ2A');
        });
    });

    describe('Dot Notation', function () {
        it('resolves nested values with dot notation', function () {
            $template = 'Amount: {{ voucher.instructions.cash.amount }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Amount: 500');
        });

        it('resolves multiple nested values', function () {
            $template = 'User: {{ user.name }} ({{ user.email }})';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('User: John Doe (john@example.com)');
        });

        it('handles deep nesting', function () {
            $template = 'Currency: {{ voucher.instructions.cash.currency }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Currency: PHP');
        });
    });

    describe('Filters', function () {
        it('applies format_money filter', function () {
            $template = 'Amount: {{ amount | format_money }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Amount: ₱500.00');
        });

        it('applies upper filter', function () {
            $template = 'Code: {{ code | upper }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Code: BIO-AQ2A'); // Already uppercase
        });

        it('applies lower filter', function () {
            $template = 'Code: {{ code | lower }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Code: bio-aq2a');
        });

        it('applies json filter', function () {
            $context = ['data' => ['foo' => 'bar']];
            $template = 'Data: {{ data | json }}';
            $result = $this->processor->process($template, $context);
            expect($result)->toBe('Data: {"foo":"bar"}');
        });

        it('chains multiple filters', function () {
            $context = ['name' => 'john doe'];
            $template = 'Name: {{ name | upper }}';
            $result = $this->processor->process($template, $context);
            expect($result)->toBe('Name: JOHN DOE');
        });
    });

    describe('Conditionals', function () {
        it('evaluates simple equality conditional', function () {
            $template = '{{ amount == 500 ? "Five hundred" : "Other" }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Five hundred');
        });

        it('evaluates false conditional', function () {
            $template = '{{ amount == 1000 ? "Thousand" : "Not thousand" }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Not thousand');
        });

        it('evaluates greater than conditional', function () {
            $template = '{{ amount > 100 ? "Large" : "Small" }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Large');
        });

        it('evaluates less than conditional', function () {
            $template = '{{ amount < 1000 ? "Below 1k" : "Above 1k" }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Below 1k');
        });

        it('evaluates boolean conditional', function () {
            $context = ['is_active' => true];
            $template = '{{ is_active ? "Active" : "Inactive" }}';
            $result = $this->processor->process($template, $context);
            expect($result)->toBe('Active');
        });
    });

    describe('Edge Cases', function () {
        it('returns empty string for missing variable', function () {
            $template = 'Hello {{ missing_var }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Hello ');
        });

        it('handles null values gracefully', function () {
            $context = ['nullable' => null];
            $template = 'Value: {{ nullable }}';
            $result = $this->processor->process($template, $context);
            expect($result)->toBe('Value: ');
        });

        it('handles empty context', function () {
            $template = 'Hello {{ name }}';
            $result = $this->processor->process($template, []);
            expect($result)->toBe('Hello ');
        });

        it('handles template with no variables', function () {
            $template = 'Static text';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Static text');
        });

        it('handles nested missing keys in dot notation', function () {
            $template = 'Value: {{ voucher.missing.key.here }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Value: ');
        });

        it('handles numeric array indices', function () {
            $context = ['items' => ['first', 'second', 'third']];
            $template = 'Item: {{ items.0 }}';
            $result = $this->processor->process($template, $context);
            expect($result)->toBe('Item: first');
        });
    });

    describe('Complex Scenarios', function () {
        it('processes mixed template with variables, filters, and conditionals', function () {
            $template = 'Voucher {{ code }} is {{ amount > 100 ? "high" : "low" }} value ({{ amount | format_money }})';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Voucher BIO-AQ2A is high value (₱500.00)');
        });

        it('processes array data structures', function () {
            $context = [
                'fields' => [
                    ['name' => 'email', 'required' => true],
                    ['name' => 'phone', 'required' => false],
                ],
            ];
            $template = 'Field: {{ fields.0.name }}, Required: {{ fields.0.required ? "yes" : "no" }}';
            $result = $this->processor->process($template, $context);
            expect($result)->toBe('Field: email, Required: yes');
        });

        it('handles deeply nested objects with filters', function () {
            $template = 'Currency: {{ voucher.instructions.cash.currency | lower }}';
            $result = $this->processor->process($template, $this->context);
            expect($result)->toBe('Currency: php');
        });
    });

    describe('YAML Processing', function () {
        it('processes template in array structure', function () {
            $yamlData = [
                'title' => 'Redeem {{ code }}',
                'description' => 'Amount: {{ amount | format_money }}',
                'fields' => [
                    'name' => 'mobile',
                    'label' => 'Mobile Number for {{ code }}',
                ],
            ];

            $result = $this->processor->processArray($yamlData, $this->context);
            
            expect($result['title'])->toBe('Redeem BIO-AQ2A');
            expect($result['description'])->toBe('Amount: ₱500.00');
            expect($result['fields']['label'])->toBe('Mobile Number for BIO-AQ2A');
        });

        it('processes nested arrays recursively', function () {
            $yamlData = [
                'steps' => [
                    ['title' => 'Step for {{ code }}'],
                    ['title' => 'Amount: {{ amount }}'],
                ],
            ];

            $result = $this->processor->processArray($yamlData, $this->context);
            
            expect($result['steps'][0]['title'])->toBe('Step for BIO-AQ2A');
            expect($result['steps'][1]['title'])->toBe('Amount: 500');
        });

        it('preserves non-string values in arrays', function () {
            $yamlData = [
                'config' => [
                    'required' => true,
                    'maxLength' => 100,
                    'pattern' => '/^09/',
                    'title' => 'For {{ code }}',
                ],
            ];

            $result = $this->processor->processArray($yamlData, $this->context);
            
            expect($result['config']['required'])->toBeTrue();
            expect($result['config']['maxLength'])->toBe(100);
            expect($result['config']['pattern'])->toBe('/^09/');
            expect($result['config']['title'])->toBe('For BIO-AQ2A');
        });
    });
});
