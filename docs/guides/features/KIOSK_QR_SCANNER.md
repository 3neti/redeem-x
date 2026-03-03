# Kiosk QR Code Scanner Integration

## Overview

The kiosk mode supports QR code / barcode scanner input for automatically populating form fields. The scanner behavior is **driver-driven** — the envelope driver YAML is the single source of truth for what gets scanned, how it's parsed, and which fields get populated.

## Architecture

```
Driver YAML (scanner config)
    ↓
DriverService → DriverData.scanner
    ↓
SkinConfigLoader.loadScannerConfig()
    ↓
Portal.vue → kioskConfig.scanner
    ↓
KioskView.vue (handleKeydown / handlePaste / initialScan)
```

**Key principle:** The driver dictates scanner behavior. KioskView is a generic renderer — it doesn't hardcode any field names or formats.

## Driver Scanner Configuration

Add a `scanner` section to the driver YAML stub:

```yaml
# packages/settlement-envelope/resources/stubs/drivers/{driver}/v1.0.0.yaml

scanner:
  enabled: true                       # Enable scanner capture
  format: json                        # json | text
  buffer_timeout_ms: 200              # ms between keystrokes before buffer clears
  field_mapping:                      # QR JSON key → payload field name
    reference: reference              # Direct 1:1 mapping
    ref_no: reference                 # Alias mapping
    claim_reference: reference        # Another alias
  amount_key: null                    # QR key for amount (null = ignore)
  target_amount_key: target_amount    # QR key for target amount
  target_override: true               # Cashier can edit scanned values
```

### Configuration Fields

- **`enabled`** — When `true`, KioskView captures all printable characters (not just digits). When `false` or absent, original digit-only behavior is preserved.
- **`format`** — Expected QR payload format. Currently `json` is supported.
- **`buffer_timeout_ms`** — Milliseconds of silence before the keystroke buffer is cleared. Physical scanners typically send all characters within 50-100ms. Set higher (200ms) for safety.
- **`field_mapping`** — Maps QR JSON keys to payload field names. Multiple QR keys can map to the same payload field (aliases).
- **`amount_key`** — Which QR JSON key contains the voucher amount. Set to `null` to not scan amounts.
- **`target_amount_key`** — Which QR JSON key contains the target amount.
- **`target_override`** — Whether the cashier can edit scanned amount values.

### After Changing Driver YAML

Driver stubs must be installed to the driver disk:

```bash
# Force reinstall (required after editing stubs)
php artisan envelope:install-drivers --force

# Clear cached driver data
php artisan cache:clear
```

## Input Methods

The scanner supports three input methods:

### 1. Physical QR Scanner (keystroke emulation)

Most USB/Bluetooth barcode scanners emulate a keyboard — they type each character rapidly and end with Enter. The `handleKeydown` listener captures these keystrokes into a buffer and processes the complete string on Enter.

- **Scanner enabled:** captures all printable characters
- **Scanner disabled:** captures digits only (original behavior)

### 2. Clipboard Paste

Pasting JSON (Cmd+V / Ctrl+V) is intercepted and processed through the same pipeline. This works:

- When focused on an empty area of the page
- When focused on an input field (JSON paste is intercepted; plain text paste proceeds normally)

### 3. URL Query Parameter (`?scan=`)

Pre-fill fields via URL:

```
/pwa/portal?skin=philhealth-bst&scan={"ref_no":"12345","target_amount":1500}
```

The JSON is URL-encoded automatically by the browser. This is useful for:

- **Testing** — quickly verify field mapping without a physical scanner
- **Deep-linking** — link from external systems with pre-populated data
- **QR codes encoding URLs** — a QR code can contain a URL with the `scan` param

### Processing Priority

For all input methods, the processing order is:

1. **JSON parse** → map fields via `field_mapping` + direct matches → fill amount/targetAmount via reserved keys
2. **Plain string fallback** → fill first empty editable payload field
3. **Numeric fallback** → fill target amount or amount (original behavior)

## Payload Field Visibility

Fields can be hidden from the kiosk UI while still being included in the submitted data:

```yaml
# In skin kiosk.yaml
fields:
  payload:
    - reference                    # Visible, editable
    - name: device
      type: auto_device_id
      editable: false
      hidden: true                 # Not rendered in UI
```

The `hidden: true` property is processed by `SkinConfigLoader.processPayloadFields()` and respected by `KioskView.vue`'s template rendering. Hidden fields still participate in data submission.

## Scan Feedback

A brief toast notification appears when a scan is processed:

- **"✓ QR Scanned"** — JSON scan successfully mapped fields
- **"✓ Scanned"** — Plain text scan filled a field
- **"✓ Pre-filled from URL"** — `?scan=` query param processed on page load

The toast auto-dismisses after 2 seconds.

## Example: PhilHealth BST

### Driver Config (`philhealth-bst/v1.0.0.yaml`)

```yaml
scanner:
  enabled: true
  format: json
  buffer_timeout_ms: 200
  field_mapping:
    reference: reference
    ref_no: reference
    claim_reference: reference
  amount_key: null
  target_amount_key: target_amount
  target_override: true
```

### Expected QR Code Content

```json
{"ref_no": "CLM-2024-001234", "target_amount": 15000}
```

### Result

- `ref_no` → mapped to `reference` field via `field_mapping`
- `target_amount` → fills the target amount keypad value

### Test URL

```
http://redeem-x.test/pwa/portal?skin=philhealth-bst&scan=%7B%22ref_no%22%3A%22CLM-2024-001234%22%2C%22target_amount%22%3A15000%7D
```

## Files Modified

- `packages/settlement-envelope/resources/stubs/drivers/philhealth-bst/v1.0.0.yaml` — Scanner config source of truth
- `packages/settlement-envelope/src/Data/DriverData.php` — `?array $scanner` property
- `packages/settlement-envelope/src/Services/DriverService.php` — Parses/merges scanner section
- `packages/pwa-ui/src/Services/SkinConfigLoader.php` — Loads driver scanner config, processes hidden fields
- `packages/pwa-ui/resources/skins/philhealth-bst/kiosk.yaml` — Hidden device field
- `config/pwa-skins/philhealth-bst.yaml` — Published skin config (hidden device field)
- `resources/js/pages/pwa/Portal.vue` — Passes scanner config + `?scan=` param to KioskView
- `resources/js/components/pwa/KioskView.vue` — Scanner handler (keydown, paste, initialScan), feedback toast, hidden field support

## Backward Compatibility

- Skins without a `scanner` section in the driver behave exactly as before (digit-only capture)
- Skins without `hidden` fields render all fields as before
- The `?scan=` param is ignored when scanner is not enabled
