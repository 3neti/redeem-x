# Notification Templates

This document describes the notification template system for voucher redemption notifications in Redeem-X.

## Overview

Notification templates use the `TemplateProcessor` service to provide dynamic, customizable messages for email and SMS notifications sent when vouchers are redeemed. Templates are stored in Laravel's translation system (`lang/en/notifications.php`) for easy admin-level customization.

## Architecture

```
VoucherData (with inputs collection)
    ↓
VoucherTemplateContextBuilder::build()  → Flat array context
    ↓
__('notifications.key')  → Raw template string
    ↓
TemplateProcessor::process($template, $context)  → Final message
```

## Template Syntax

Templates use **`{{ variable }}`** syntax (NOT Laravel's `:variable` syntax):

```
Hello {{ name }}, your voucher {{ code }} has been redeemed!
```

### Why {{ }} Instead of :?

We use `{{ }}` syntax to:
1. Leverage the existing `TemplateProcessor` service (already built and tested)
2. Support advanced features like dot notation (`{{ user.profile.name }}`)
3. Support recursive search for nested values
4. Keep consistent syntax throughout the application

## Available Variables

### Basic Voucher Information
- `{{ code }}` - Voucher code (e.g., "TEST-123")
- `{{ status }}` - Voucher status (active, redeemed, expired)
- `{{ created_at }}` - Creation timestamp
- `{{ redeemed_at }}` - Redemption timestamp

### Amount and Currency
- `{{ amount }}` - Raw amount as float (e.g., 50.00)
- `{{ formatted_amount }}` - Localized formatted amount (e.g., "₱50.00")
- `{{ currency }}` - Three-letter currency code (e.g., "PHP")

### Contact Information
- `{{ mobile }}` - Contact mobile number
- `{{ contact_name }}` - Contact name (if available)
- `{{ bank_account }}` - Bank account identifier (e.g., "GCASH:09171234567")
- `{{ bank_code }}` - Bank code (e.g., "GXCHPHM2XXX")
- `{{ account_number }}` - Account number (e.g., "09171234567")

### Location
- `{{ formatted_address }}` - Formatted address from location input
- `{{ location }}` - Raw location JSON (if captured)

### Owner Information
- `{{ owner_name }}` - Voucher owner name
- `{{ owner_email }}` - Voucher owner email
- `{{ owner_mobile }}` - Voucher owner mobile

### Dynamic Input Fields
- `{{ signature }}` - Signature data URL (if captured)
- `{{ <custom_field> }}` - Any custom input field by name

## Template Files

Templates are stored in `lang/en/notifications.php`:

```php
return [
    'voucher_redeemed' => [
        'email' => [
            'subject' => 'Voucher Code Redeemed',
            'greeting' => 'Hello,',
            'body' => 'The voucher code **{{ code }}** with amount **{{ formatted_amount }}** has been redeemed...',
            'warning' => 'If you did not authorize this transaction...',
            'salutation' => 'Thank you for using our service!',
        ],
        'sms' => [
            'message' => 'Voucher {{ code }} with amount {{ formatted_amount }} was redeemed by {{ mobile }}.',
            'message_with_address' => 'Voucher {{ code }} redeemed by {{ mobile }} from {{ formatted_address }}.',
        ],
    ],
];
```

## Customization

### Step 1: Edit the Translation File

Edit `lang/en/notifications.php`:

```php
'voucher_redeemed' => [
    'email' => [
        'subject' => 'Alert: Voucher {{ code }} Redeemed',
        'body' => 'Your voucher {{ code }} ({{ formatted_amount }}) was successfully redeemed by {{ mobile }}.',
    ],
],
```

### Step 2: Use Available Variables

Reference any of the available variables listed above. Missing variables will be handled gracefully (replaced with empty string by default).

### Step 3: Test Your Changes

Use `php artisan tinker` to test templates:

```php
use App\Services\VoucherTemplateContextBuilder;
use App\Services\TemplateProcessor;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\VoucherData;

$voucher = Voucher::first();
$voucherData = VoucherData::fromModel($voucher);
$context = VoucherTemplateContextBuilder::build($voucherData);

$template = __('notifications.voucher_redeemed.email.body');
$processed = TemplateProcessor::process($template, $context);

echo $processed;
```

### Step 4: Deploy

Since templates are in configuration files, they are deployed automatically with your code.

## Examples

### Example 1: Basic Email Template

```
Subject: Voucher Redeemed - {{ code }}
Body: Your voucher {{ code }} worth {{ formatted_amount }} has been claimed.
```

**Result:**
```
Subject: Voucher Redeemed - ABC-123
Body: Your voucher ABC-123 worth ₱50.00 has been claimed.
```

### Example 2: SMS with Location

```
Voucher {{ code }} ({{ formatted_amount }}) redeemed by {{ mobile }} at {{ formatted_address }}.
```

**Result:**
```
Voucher ABC-123 (₱50.00) redeemed by +639171234567 at 123 Main St, Manila.
```

### Example 3: Using Custom Input Fields

If your voucher has a custom input field called `rider_name`:

```
Voucher {{ code }} delivered by {{ rider_name }} to {{ mobile }}.
```

**Result:**
```
Voucher ABC-123 delivered by Juan Dela Cruz to +639171234567.
```

## Advanced Features

### Dot Notation

Access nested properties using dot notation:

```
{{ contact.bank_account.account }}
```

(Though for convenience, the context builder flattens most nested structures)

### Recursive Search

The `TemplateProcessor` will automatically search nested structures if a direct match isn't found:

```
{{ bank_code }}  // Will find it even if nested in contact.bank_account.bank_code
```

### Custom Formatters

While not exposed in templates, the `TemplateProcessor` supports custom formatters for advanced use cases (see `TemplateProcessor` docs).

## Implementation Details

### VoucherTemplateContextBuilder

The `VoucherTemplateContextBuilder` service converts `VoucherData` DTOs into flat arrays suitable for templating:

```php
use App\Services\VoucherTemplateContextBuilder;

$context = VoucherTemplateContextBuilder::build($voucherData);
// Returns: ['code' => 'ABC-123', 'amount' => 50.00, 'formatted_amount' => '₱50.00', ...]
```

### TemplateProcessor

The `TemplateProcessor` service handles variable replacement:

```php
use App\Services\TemplateProcessor;

$template = 'Hello {{ name }}, your code is {{ code }}';
$context = ['name' => 'John', 'code' => 'ABC-123'];

$result = TemplateProcessor::process($template, $context);
// Returns: "Hello John, your code is ABC-123"
```

## Best Practices

1. **Test templates** after editing using `php artisan tinker` or feature tests
2. **Keep templates simple** - avoid overly complex logic
3. **Use formatted_amount** instead of raw `amount` for user-facing messages
4. **Handle missing data** - templates gracefully handle null values
5. **Document custom fields** - if you add custom input fields, document them for template editors

## Troubleshooting

### Variable not appearing in output

1. Check the variable name is correct (case-sensitive)
2. Verify the data exists in the voucher (use `VoucherTemplateContextBuilder::getAvailableVariables()`)
3. Check for typos in the template syntax (`{{` not `{`)

### Template not updating

1. Clear Laravel's cache: `php artisan cache:clear`
2. Restart queue workers if notifications are queued

### Testing templates

Run the existing notification tests:

```bash
php artisan test tests/Feature/Redeem/Notification/NotificationContentTest.php
```

## Related Files

- `app/Services/VoucherTemplateContextBuilder.php` - Builds template context
- `app/Services/TemplateProcessor.php` - Processes templates
- `lang/en/notifications.php` - Template storage
- `app/Notifications/SendFeedbacksNotification.php` - Email/SMS notification handler
- `app/Actions/Notification/SendFeedback.php` - Feedback action (stub)
- `tests/Feature/Services/VoucherTemplateContextBuilderTest.php` - Context builder tests
- `tests/Unit/Services/TemplateProcessorTest.php` - Template processor tests
- `tests/Feature/Redeem/Notification/NotificationContentTest.php` - Notification tests

## Future Enhancements

- Multi-language support (add `lang/es/notifications.php`, etc.)
- Per-campaign templates (move from global to campaign-specific)
- Template preview UI in admin panel
- Template validation before save

## Support

For issues or questions about notification templates, consult:
- This documentation
- The `TemplateProcessor` service source code
- Existing test files for examples
