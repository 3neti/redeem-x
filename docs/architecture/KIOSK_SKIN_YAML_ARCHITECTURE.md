# Kiosk Skin YAML Architecture

## Overview

The PWA Kiosk Mode uses a **YAML-only configuration system** for defining custom skins. This document explains the architecture, rationale, and implementation details.

## Design Decision: YAML-Only

**Previous state:**
- Two config files: `config.php` and `kiosk.yaml`
- `kiosk.yaml` was orphaned (not being used)
- Hardcoded defaults in Vue component
- Confusing three-layer fallback system

**Current state:**
- Single source of truth: `kiosk.yaml`
- Automatic placeholder generation
- Three-level override system (YAML → Published YAML → URL params)
- Clean, declarative configuration

## Architecture Components

### 1. SkinConfigLoader Service

**Location:** `packages/pwa-ui/src/Services/SkinConfigLoader.php`

**Responsibilities:**
- Load YAML configuration files
- Apply URL query parameter overrides
- Generate automatic placeholders
- Flatten config for frontend consumption

**Key Methods:**

```php
// Load skin config with overrides
public function load(string $skinName, array $queryParams = []): ?array

// Resolve skin file path (published > package)
protected function resolveSkinPath(string $skinName): ?string

// Process raw YAML with overrides and placeholder generation
protected function processConfig(array $config, array $queryParams): array

// Auto-generate placeholder from label
protected function generatePlaceholder(string $label): string
```

**Search Order:**
1. `config/pwa-skins/{skin}.yaml` (published, highest priority)
2. `packages/pwa-ui/resources/skins/{skin}/kiosk.yaml` (package default)

### 2. YAML File Structure

**Location:** `packages/pwa-ui/resources/skins/philhealth-bst/kiosk.yaml`

**Sections:**

```yaml
# Kiosk Identity
title: string
subtitle: string (optional)
voucher_type: 'redeemable'|'settlement'|'payable'

# Voucher Configuration
config:
  campaign: string (optional)
  driver: string (optional, format: 'id@version')
  amount: number|null
  target_amount: number|null

# Input Fields
fields:
  inputs: string[]          # Required on redemption
  payload: string[]         # Collected at issuance

# Callbacks
callbacks:
  feedback: string|null     # Webhook URL

# UI Labels & Text
ui:
  logo: string|null
  theme_color: string
  amount_label: string
  amount_placeholder: string|null  # Auto-generated if null
  target_label: string
  target_placeholder: string|null  # Auto-generated if null
  button_text: string
  print_button: string
  new_button: string
  retry_button: string
  success_title: string
  success_message: string
  error_title: string
```

### 3. Placeholder Auto-Generation

**Rule:** If a `*_placeholder` field is `null`, generate from the corresponding `*_label`.

**Algorithm:**
```
"Amount" → "Enter amount"
"Reimbursement Amount" → "Enter reimbursement amount"
"BST Code" → "Enter BST code"
```

**Implementation:**
```php
protected function generatePlaceholder(string $label): string
{
    return 'Enter ' . Str::lower($label);
}
```

### 4. Override Hierarchy

**Priority (highest to lowest):**

1. **URL Query Parameters** (temporary, per-session)
   ```
   /pwa/portal?skin=philhealth-bst&button_text=Issue%20Token
   ```

2. **Published YAML** (production customization)
   ```
   config/pwa-skins/philhealth-bst.yaml
   ```

3. **Package YAML** (default)
   ```
   packages/pwa-ui/resources/skins/philhealth-bst/kiosk.yaml
   ```

4. **Hardcoded Defaults** (fallback only)
   - Defined in `KioskView.vue` component
   - Used if YAML key is missing entirely

### 5. Frontend Integration

**Location:** `packages/pwa-ui/resources/js/components/KioskView.vue`

**Config Consumption:**

```typescript
// Labels are pre-processed by SkinConfigLoader
const labels = computed(() => ({
  title: props.config.title || 'Quick Voucher',
  subtitle: props.config.subtitle || '',
  amountLabel: props.config.ui?.amount_label || 'Amount',
  amountPlaceholder: props.config.ui?.amount_placeholder || 'Enter amount',
  // ... etc
}));
```

**Props Interface:**

```typescript
interface KioskConfig {
  title: string;
  subtitle?: string;
  voucher_type?: 'redeemable' | 'payable' | 'settlement';
  campaign?: string;
  driver?: string;
  amount?: number;
  target_amount?: number;
  inputs?: string[];
  payload?: string[];
  feedback?: string;
  ui: {
    logo?: string;
    theme_color?: string;
    amount_label: string;
    amount_placeholder: string;
    target_label: string;
    target_placeholder: string;
    button_text: string;
    success_title: string;
    success_message: string;
    print_button: string;
    new_button: string;
    error_title: string;
    retry_button: string;
  };
}
```

## Usage Examples

### Example 1: Use Package Default

```
/pwa/portal?skin=philhealth-bst
```

Loads: `packages/pwa-ui/resources/skins/philhealth-bst/kiosk.yaml`

### Example 2: Override with URL Parameters

```
/pwa/portal?skin=philhealth-bst&title=Custom%20BST&button_text=Issue%20Now
```

YAML config loaded, then `title` and `button_text` overridden.

### Example 3: Publish and Customize

```bash
# Publish skin bundle
php artisan vendor:publish --tag=pwa-skin-philhealth-bst

# Edit published config
vim config/pwa-skins/philhealth-bst.yaml

# Changes take effect immediately (no rebuild needed)
```

### Example 4: Create New Skin

```bash
# 1. Create new YAML file
mkdir -p packages/pwa-ui/resources/skins/my-skin
vim packages/pwa-ui/resources/skins/my-skin/kiosk.yaml

# 2. Use it
/pwa/portal?skin=my-skin
```

## Benefits

### 1. **Single Source of Truth**
- No confusion between PHP and YAML
- One file to edit for all customizations

### 2. **Declarative Configuration**
- YAML is human-readable
- No PHP knowledge required
- Easy to diff and version control

### 3. **Automatic Placeholders**
- Reduces redundant configuration
- Consistent placeholder format
- DRY principle (Don't Repeat Yourself)

### 4. **Flexible Overrides**
- Test changes via URL (no file edits)
- Publish for production customization
- Package default preserved

### 5. **No Rebuild Required**
- YAML changes take effect immediately
- Faster iteration during development
- Production updates without downtime

## Migration from PHP Config

**Old approach:**
```php
// packages/pwa-ui/resources/skins/philhealth-bst/config.php
return [
    'title' => 'PhilHealth BST',
    'ui' => [
        'button_text' => 'Issue BST',
        'amount_placeholder' => 'Enter deposit amount',
    ],
];
```

**New approach:**
```yaml
# packages/pwa-ui/resources/skins/philhealth-bst/kiosk.yaml
title: PhilHealth BST
ui:
  button_text: Issue BST
  amount_placeholder: null  # Auto-generated
```

**Deleted files:**
- `packages/pwa-ui/resources/skins/philhealth-bst/config.php` ❌
- Service provider no longer merges PHP config

## Testing

### Manual Testing Checklist

- [ ] Load default skin: `/pwa/portal?skin=philhealth-bst`
- [ ] Verify all labels from YAML appear
- [ ] Verify placeholders auto-generate correctly
- [ ] Override via URL: `?button_text=Custom`
- [ ] Publish and edit: `php artisan vendor:publish --tag=pwa-skin-philhealth-bst`
- [ ] Verify published config overrides package default
- [ ] Test missing YAML keys fall back to hardcoded defaults

### X-Ray Debug Panel

Click kiosk header to toggle X-Ray panel showing:
- Current configuration values
- Voucher type detection
- Field visibility logic
- All merged settings

## Future Enhancements

### Potential Additions

1. **Theme Colors**
   - Support `ui.theme_color` in component
   - Apply to buttons, header, success states

2. **Logo Support**
   - Render `ui.logo` in kiosk header
   - Auto-scale to fit

3. **Custom Payload Field Labels**
   - Define labels for payload fields in YAML
   - Instead of auto-generating from field name

4. **Validation Rules**
   - Define input validation in YAML
   - E.g., `payload.reference.pattern: /^\\d{6}$/`

5. **Multi-Language Support**
   - YAML per locale: `kiosk.en.yaml`, `kiosk.fil.yaml`
   - Auto-detect or explicit `?locale=fil`

## Related Documentation

- [PWA Kiosk Mode User Manual](../guides/features/PWA_KIOSK_MODE_USER_MANUAL.md)
- [Settlement Envelope Drivers](SETTLEMENT_ENVELOPE_ARCHITECTURE.md)

---

**Last Updated:** 2026-03-01  
**Version:** 2.0 (YAML-only)
