<?php

use App\Services\TemplateProcessor;

it('processes simple variables', function () {
    $context = [
        'name' => 'John Doe',
        'amount' => 100,
    ];

    $result = TemplateProcessor::process('Hello {{ name }}, you have {{ amount }}', $context);

    expect($result)->toBe('Hello John Doe, you have 100');
});

it('processes dot notation', function () {
    $context = [
        'user' => [
            'profile' => [
                'name' => 'Jane Smith',
            ],
        ],
    ];

    $result = TemplateProcessor::process('Welcome {{ user.profile.name }}!', $context);

    expect($result)->toBe('Welcome Jane Smith!');
});

it('recursively searches for nested values', function () {
    $context = [
        'voucher' => [
            'code' => 'ABC-123',
            'contact' => [
                'mobile' => '+639171234567',
                'bank_account' => 'GCASH:09171234567',
            ],
        ],
    ];

    // Should find bank_account without full path
    $result = TemplateProcessor::process('Transfer to {{ bank_account }}', $context);
    expect($result)->toBe('Transfer to GCASH:09171234567');

    // Should find mobile without full path
    $result = TemplateProcessor::process('Mobile: {{ mobile }}', $context);
    expect($result)->toBe('Mobile: +639171234567');

    // Should find code without full path
    $result = TemplateProcessor::process('Code: {{ code }}', $context);
    expect($result)->toBe('Code: ABC-123');
});

it('prefers dot notation over recursive search', function () {
    $context = [
        'name' => 'Top Level',
        'user' => [
            'name' => 'Nested',
        ],
    ];

    // Direct path should return top level
    $result = TemplateProcessor::process('{{ name }}', $context);
    expect($result)->toBe('Top Level');

    // Dot notation should return nested
    $result = TemplateProcessor::process('{{ user.name }}', $context);
    expect($result)->toBe('Nested');
});

it('handles missing variables with fallback', function () {
    $context = ['name' => 'John'];

    $result = TemplateProcessor::process('{{ name }} {{ missing }}', $context, fallback: 'N/A');

    expect($result)->toBe('John N/A');
});

it('applies custom formatters', function () {
    $context = ['amount' => 100];

    $formatters = [
        'amount' => fn ($val) => '₱'.number_format($val, 2),
    ];

    $result = TemplateProcessor::process('Total: {{ amount }}', $context, $formatters);

    expect($result)->toBe('Total: ₱100.00');
});

it('extracts variables from template', function () {
    $template = 'Hello {{ name }}, your balance is {{ account.balance }}';

    $variables = TemplateProcessor::extractVariables($template);

    expect($variables)->toBe(['name', 'account.balance']);
});

it('checks if template has variables', function () {
    expect(TemplateProcessor::hasVariables('Hello {{ name }}'))->toBeTrue();
    expect(TemplateProcessor::hasVariables('Hello World'))->toBeFalse();
});

it('validates resolvability', function () {
    $context = [
        'name' => 'John',
        'age' => 30,
    ];

    expect(TemplateProcessor::canResolve('{{ name }} is {{ age }}', $context))->toBeTrue();
    expect(TemplateProcessor::canResolve('{{ name }} {{ missing }}', $context))->toBeFalse();
});

it('handles arrays in values', function () {
    $context = [
        'tags' => ['laravel', 'php', 'vue'],
    ];

    $result = TemplateProcessor::process('Tags: {{ tags }}', $context);

    expect($result)->toBe('Tags: laravel, php, vue');
});

it('handles whitespace in variable names', function () {
    $context = ['name' => 'John'];

    // With extra spaces
    $result = TemplateProcessor::process('Hello {{  name  }}', $context);
    expect($result)->toBe('Hello John');

    // Without spaces
    $result = TemplateProcessor::process('Hello {{name}}', $context);
    expect($result)->toBe('Hello John');
});

it('handles null values with fallback', function () {
    $context = [
        'name' => 'John',
        'email' => null,
    ];

    $result = TemplateProcessor::process('Name: {{ name }}, Email: {{ email }}', $context, fallback: 'N/A');
    expect($result)->toBe('Name: John, Email: N/A');
});

it('handles zero and false values correctly', function () {
    $context = [
        'count' => 0,
        'active' => false,
    ];

    $result = TemplateProcessor::process('Count: {{ count }}, Active: {{ active }}', $context);
    expect($result)->toBe('Count: 0, Active: false');
});

it('handles deeply nested structures', function () {
    $context = [
        'data' => [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'Deep Value',
                    ],
                ],
            ],
        ],
    ];

    // Dot notation should work
    $result = TemplateProcessor::process('{{ data.level1.level2.level3.value }}', $context);
    expect($result)->toBe('Deep Value');

    // Recursive search should also find it
    $result = TemplateProcessor::process('{{ value }}', $context);
    expect($result)->toBe('Deep Value');
});

it('handles multiple occurrences of same variable', function () {
    $context = ['name' => 'John'];

    $result = TemplateProcessor::process('{{ name }} said hello to {{ name }}', $context);
    expect($result)->toBe('John said hello to John');
});

it('handles empty template', function () {
    $context = ['name' => 'John'];

    $result = TemplateProcessor::process('', $context);
    expect($result)->toBe('');
});

it('handles template with no variables', function () {
    $context = ['name' => 'John'];

    $result = TemplateProcessor::process('Hello World', $context);
    expect($result)->toBe('Hello World');
});

it('throws exception in strict mode for missing variables', function () {
    $context = ['name' => 'John'];

    expect(fn () => TemplateProcessor::process('{{ missing }}', $context, strict: true))
        ->toThrow(Exception::class, 'Template variable not found: missing');
});

it('handles numeric values', function () {
    $context = [
        'integer' => 42,
        'float' => 3.14,
        'negative' => -10,
    ];

    $result = TemplateProcessor::process('Int: {{ integer }}, Float: {{ float }}, Neg: {{ negative }}', $context);
    expect($result)->toBe('Int: 42, Float: 3.14, Neg: -10');
});

it('handles strings with special characters', function () {
    $context = [
        'message' => 'Hello & "Goodbye"',
    ];

    $result = TemplateProcessor::process('Message: {{ message }}', $context);
    expect($result)->toBe('Message: Hello & "Goodbye"');
});

it('works with real voucher data structure', function () {
    $context = [
        'voucher' => [
            'code' => 'TEST-123',
            'amount' => 50.00,
            'currency' => 'PHP',
            'contact' => [
                'mobile' => '+639171234567',
                'bank_code' => 'GXCHPHM2XXX',
                'account_number' => '09171234567',
            ],
        ],
    ];

    // Test exact paths
    $result = TemplateProcessor::process(
        'Code: {{ voucher.code }}, Mobile: {{ voucher.contact.mobile }}',
        $context
    );
    expect($result)->toBe('Code: TEST-123, Mobile: +639171234567');

    // Test recursive search
    $result = TemplateProcessor::process(
        'Code: {{ code }}, Mobile: {{ mobile }}, Bank: {{ bank_code }}',
        $context
    );
    expect($result)->toBe('Code: TEST-123, Mobile: +639171234567, Bank: GXCHPHM2XXX');
});
