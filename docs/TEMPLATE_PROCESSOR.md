# Template Processor Service

**Date**: 2025-11-10

## Overview

A flexible template variable system for replacing `{{ variable }}` placeholders in strings with actual data values. Available in both Laravel (PHP) and Vue (TypeScript).

## Features

- **Dot-notation support**: `{{ voucher.contact.mobile }}`
- **Recursive search**: `{{ mobile }}` automatically finds `voucher.contact.mobile`
- **Custom formatters**: Special formatting for specific fields
- **Fallback values**: Graceful handling of missing data
- **Type-safe**: Handles strings, numbers, arrays, booleans, null

## Usage

### Laravel (PHP)

```php
use App\Services\TemplateProcessor;

$context = [
    'voucher' => [
        'code' => 'ABC-123',
        'amount' => 50.00,
        'contact' => [
            'mobile' => '+639171234567',
            'bank_code' => 'GXCHPHM2XXX',
        ],
    ],
];

// Basic usage
$result = TemplateProcessor::process(
    'Code: {{ code }}, Mobile: {{ mobile }}',
    $context
);
// Result: "Code: ABC-123, Mobile: +639171234567"

// With custom formatter
$formatters = [
    'amount' => fn($val) => '₱' . number_format($val, 2),
];
$result = TemplateProcessor::process(
    'Amount: {{ amount }}',
    $context,
    $formatters
);
// Result: "Amount: ₱50.00"

// With fallback for missing values
$result = TemplateProcessor::process(
    'Name: {{ name }}, Code: {{ code }}',
    $context,
    fallback: 'N/A'
);
// Result: "Name: N/A, Code: ABC-123"

// Strict mode (throws exception on missing)
$result = TemplateProcessor::process(
    '{{ missing }}',
    $context,
    strict: true
);
// Throws: Exception "Template variable not found: missing"
```

### TypeScript (Vue)

```typescript
import { useTemplateProcessor } from '@/composables/useTemplateProcessor';

const props = {
    voucher: {
        code: 'ABC-123',
        amount: 50.00,
        contact: {
            mobile: '+639171234567',
            bank_code: 'GXCHPHM2XXX',
        },
    },
};

// Initialize with context and formatters
const { processTemplate } = useTemplateProcessor(props, {
    formatters: {
        'amount': (val) => new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(val),
    },
    fallback: '',
});

// Process template
const result = processTemplate('Code: {{ code }}, Mobile: {{ mobile }}');
// Result: "Code: ABC-123, Mobile: +639171234567"
```

## Variable Resolution

### Priority Order

1. **Exact path** (if contains dots): `{{ voucher.code }}`
2. **Recursive search** (if no dots): Searches entire object tree for first match

### Examples

```php
$context = [
    'code' => 'TOP-LEVEL',
    'voucher' => [
        'code' => 'NESTED',
        'contact' => [
            'mobile' => '+639171234567',
        ],
    ],
];

// Exact top-level
{{ code }} → 'TOP-LEVEL'

// Exact path
{{ voucher.code }} → 'NESTED'

// Recursive search finds nested value
{{ mobile }} → '+639171234567'
```

## API Reference

### Laravel

#### `TemplateProcessor::process()`

```php
public static function process(
    string $template,
    array|object $context,
    array $formatters = [],
    bool $strict = false,
    string $fallback = ''
): string
```

**Parameters:**
- `$template` - Template string with `{{ variable }}` placeholders
- `$context` - Data context (array or object)
- `$formatters` - Custom formatters `['path' => callable]`
- `$strict` - Throw exception on missing variables
- `$fallback` - Default value for missing variables

#### `TemplateProcessor::hasVariables()`

```php
public static function hasVariables(string $template): bool
```

Check if template contains any `{{ }}` patterns.

#### `TemplateProcessor::extractVariables()`

```php
public static function extractVariables(string $template): array
```

Extract all variable paths from template.

#### `TemplateProcessor::canResolve()`

```php
public static function canResolve(string $template, array|object $context): bool
```

Validate that all variables can be resolved.

### TypeScript

#### `useTemplateProcessor()`

```typescript
const { processTemplate, hasVariables, extractVariables, canResolve } = useTemplateProcessor(
    context: any,
    options?: {
        formatters?: Record<string, (value: any) => string>;
        strict?: boolean;
        fallback?: string;
    }
);
```

## Type Handling

| Type | Behavior | Example |
|------|----------|---------|
| String | Returns as-is | `"Hello"` → `"Hello"` |
| Number | Converts to string | `42` → `"42"` |
| Boolean | `true`/`false` | `true` → `"true"` |
| Array | Joins with comma | `['a','b']` → `"a, b"` |
| Null/Undefined | Uses fallback | `null` → `""` or fallback |
| Object | JSON string | `{a:1}` → `"{\"a\":1}"` |

## Real-World Example (Success Page)

```php
// Config
'footer_note' => 'The {{ amount }} has been transferred to {{ bank_code }}:{{ account_number }}. Code: {{ code }}'

// Context (from voucher)
[
    'voucher' => [
        'code' => 'ABC-123',
        'amount' => 50.00,
        'contact' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ],
]

// Result
"The ₱50.00 has been transferred to GXCHPHM2XXX:09171234567. Code: ABC-123"
```

## Testing

Comprehensive test suite with 21 tests covering:
- ✅ Simple variables
- ✅ Dot notation
- ✅ Recursive search
- ✅ Priority (dot > recursive)
- ✅ Missing variables with fallback
- ✅ Custom formatters
- ✅ Null/zero/false values
- ✅ Deep nesting (4+ levels)
- ✅ Multiple occurrences
- ✅ Empty templates
- ✅ Strict mode exceptions
- ✅ Numeric values
- ✅ Special characters
- ✅ Real voucher data structures

Run tests:
```bash
php artisan test tests/Unit/Services/TemplateProcessorTest.php
```

## Edge Cases

### Whitespace

Both work:
```
{{ variable }}
{{variable}}
{{  variable  }}
```

### Missing Values

```php
// With fallback
{{ missing }} → "" (or custom fallback)

// Strict mode
{{ missing }} → Exception thrown
```

### Zero and False

```php
{{ count }} where count = 0 → "0"
{{ active }} where active = false → "false"
```

### Nested Search Order

Finds first match in depth-first order:
```php
[
    'user' => ['name' => 'John'],
    'admin' => ['name' => 'Jane'],
]
{{ name }} → "John" (found first)
```

## Performance

- **Regex-based**: Fast pattern matching
- **Lazy evaluation**: Only processes when needed
- **Caching**: Computed properties cache results in Vue
- **No overhead**: Zero cost for templates without variables

## Best Practices

1. **Use simple names when possible**: `{{ code }}` instead of `{{ voucher.code }}`
2. **Use exact paths for ambiguity**: If multiple `name` fields exist
3. **Provide fallbacks**: For optional data like contact names
4. **Format at template level**: Use custom formatters for currency, dates
5. **Test templates**: Use `extractVariables()` to validate before deployment

## Related Files

- **Laravel Service**: `app/Services/TemplateProcessor.php`
- **TypeScript Composable**: `resources/js/composables/useTemplateProcessor.ts`
- **Tests**: `tests/Unit/Services/TemplateProcessorTest.php`
- **Success Page**: `resources/js/pages/Redeem/Success.vue`
- **Config**: `config/redeem.php`
