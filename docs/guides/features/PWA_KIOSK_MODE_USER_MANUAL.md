# PWA Kiosk Mode User Manual

The PWA Kiosk Mode provides a simplified, full-screen interface for rapid voucher issuance. It's designed for point-of-sale terminals, kiosks, and scenarios where operators need to issue vouchers quickly with minimal UI.

## Activating Kiosk Mode

Add `?skin=pos` to the PWA portal URL:

```
https://your-domain.com/pwa/portal?skin=pos
```

## Configuration via Query Parameters

All kiosk configuration is done through URL query parameters. This allows you to bookmark different configurations or share URLs for specific use cases.

### Basic Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `skin` | **Required.** Activates kiosk mode | `pos` |
| `title` | Header title displayed at top | `PhilHealth BST` |
| `subtitle` | Secondary text below title | `Benefit Support Token` |

**Example:**
```
/pwa/portal?skin=pos&title=PhilHealth%20BST&subtitle=Benefit%20Support%20Token
```

### Voucher Type Parameters

| Parameter | Description | Values |
|-----------|-------------|--------|
| `type` | Type of voucher to issue | `redeemable`, `settlement`, `payable` |
| `amount` | Pre-filled or fixed voucher amount | Any positive number |
| `target_amount` | Target amount for settlement/payable | Any positive number |

**Voucher Types:**
- **redeemable** - Standard cash voucher. Shows amount input only.
- **settlement** - BST-style voucher. Shows both amount and target amount fields.
- **payable** - Invoice/bill payment. Shows target amount only (amount is 0).

**Examples:**
```bash
# Redeemable voucher
/pwa/portal?skin=pos&title=Cash%20Voucher&type=redeemable

# Settlement voucher (BST)
/pwa/portal?skin=pos&title=PhilHealth%20BST&type=settlement

# Payable voucher
/pwa/portal?skin=pos&title=Invoice%20Payment&type=payable

# Fixed amount (user cannot change)
/pwa/portal?skin=pos&title=Promo&type=redeemable&amount=500
```

### Redemption Configuration

| Parameter | Description | Example |
|-----------|-------------|---------|
| `inputs` | Comma-separated list of required fields | `mobile,name,email` |
| `driver` | Settlement envelope driver ID | `philhealth-bst@1.0.0` |
| `feedback` | Webhook URL for notifications | `https://example.com/webhook` |

**Available Input Fields:**
- `mobile` - Mobile number
- `name` - Full name
- `email` - Email address
- `address` - Physical address
- `location` - GPS coordinates
- `selfie` - Photo capture
- `signature` - Digital signature
- `kyc` - Identity verification

**Example:**
```
/pwa/portal?skin=pos&title=KYC%20Voucher&inputs=mobile,name,kyc&driver=philhealth-bst@1.0.0
```

### UI Label Customization

| Parameter | Description | Default |
|-----------|-------------|---------|
| `amount_label` | Label for amount field | `Amount` |
| `button_text` | Submit button text | `Issue Voucher` |
| `success_title` | Success screen title | `Voucher Issued!` |
| `success_message` | Success screen message | `Scan QR code to redeem` |

**Example:**
```
/pwa/portal?skin=pos&title=BST&button_text=Issue%20BST&success_title=BST%20Issued!&success_message=Present%20QR%20to%20cashier
```

## Complete Examples

### 1. Simple Cash Voucher Kiosk
```
/pwa/portal?skin=pos&title=Cash%20Voucher&type=redeemable
```
- Shows amount input
- Issues redeemable voucher
- Default labels

### 2. PhilHealth BST Kiosk
```
/pwa/portal?skin=pos&title=PhilHealth%20BST&subtitle=Benefit%20Support%20Token&type=settlement&driver=philhealth-bst@1.0.0&inputs=mobile,name&button_text=Issue%20BST&success_title=BST%20Issued!&success_message=Present%20QR%20to%20cashier
```
- Shows deposit amount + reimbursement amount fields
- Uses PhilHealth BST envelope driver
- Requires mobile and name on redemption
- Custom button and success messages

### 3. Fixed Amount Promo Voucher
```
/pwa/portal?skin=pos&title=Holiday%20Promo&type=redeemable&amount=500&button_text=Issue%20%E2%82%B1500%20Voucher
```
- Fixed ₱500 amount (no input needed)
- Custom button text
- One-tap issuance

### 4. Invoice Payment Kiosk
```
/pwa/portal?skin=pos&title=Pay%20Invoice&type=payable&inputs=mobile&feedback=https://api.example.com/payment-webhook
```
- Payable voucher type
- Requires mobile on redemption
- Sends webhook on redemption

## Kiosk UI States

The kiosk cycles through four states:

1. **Input** - Collect amount/target amount via numeric keypad
2. **Submitting** - Loading spinner while voucher is created
3. **Issued** - Shows voucher code, QR code, print button
4. **Error** - Shows error message with retry button

### Issued State Features
- **Voucher Code** - Large, readable code display
- **QR Code** - Scannable redemption link
- **Print Button** - Opens print dialog (receipt-formatted)
- **Issue Another** - Resets to input state

## Scanner Support

The kiosk supports barcode scanners that emulate keyboard input:

1. Scanner sends rapid digit keystrokes
2. Kiosk buffers digits (100ms timeout)
3. On ENTER key, value is applied to active field
4. Works for both amount and target amount fields

**Note:** Scanner must be configured to send ENTER after scan.

## Print Support

Clicking "Print" opens the browser print dialog with:
- Receipt-optimized layout
- Voucher code (large font)
- QR code
- Hides navigation and buttons

For thermal printers, configure browser to use appropriate paper size (e.g., 80mm receipt).

## Kill Switch

Administrators can disable kiosk mode entirely:

```bash
# In .env
PWA_KIOSK_ENABLED=false
```

When disabled, `?skin=pos` will show the normal portal instead.

## Customizing Skins

### YAML Configuration

All skin configurations are defined in YAML files for easy customization. The config includes:

**Location (package):** `packages/pwa-ui/resources/skins/{skin}/kiosk.yaml`  
**Location (published):** `config/pwa-skins/{skin}.yaml`

**YAML Structure:**
```yaml
# Kiosk Identity
title: PhilHealth BST
subtitle: Benefit Support Token
voucher_type: settlement

# Voucher Configuration
config:
  campaign: philhealth-bst
  driver: philhealth-bst@1.0.0
  amount: 0
  target_amount: null

# Input Fields
fields:
  inputs: [mobile, name]
  payload: [reference, membership_id]

# Callbacks
callbacks:
  feedback: null

# UI Labels & Text
ui:
  logo: null
  theme_color: "#0066cc"
  amount_label: Deposit Amount
  amount_placeholder: null  # Auto-generated: "Enter deposit amount"
  target_label: Reimbursement Amount
  target_placeholder: null  # Auto-generated
  button_text: Issue BST
  print_button: Print Receipt
  new_button: Issue Another BST
  retry_button: Try Again
  success_title: BST Issued!
  success_message: Present QR to cashier
  error_title: Issuance Failed
```

### Automatic Placeholder Generation

If you set a placeholder to `null` in YAML, the system automatically generates it:
- `amount_label: "Deposit Amount"` → `amount_placeholder: "Enter deposit amount"`
- `target_label: "Reimbursement Amount"` → `target_placeholder: "Enter reimbursement amount"`

### Customization Options

**Option 1: Edit Package YAML** (quick testing)
```bash
vim packages/pwa-ui/resources/skins/philhealth-bst/kiosk.yaml
```

**Option 2: Publish and Override** (production)
```bash
php artisan vendor:publish --tag=pwa-skin-philhealth-bst
vim config/pwa-skins/philhealth-bst.yaml
```
Published config overrides package default.

**Option 3: URL Query Parameters** (temporary override)
```
/pwa/portal?skin=philhealth-bst&title=Custom%20Title&button_text=Issue%20Token
```
URL parameters override YAML config.

### Installing Skin Bundles

```bash
# Install PhilHealth BST skin (YAML config, driver, campaign)
php artisan vendor:publish --tag=pwa-skin-philhealth-bst
php artisan migrate
```

This publishes:
- **Kiosk config:** `config/pwa-skins/philhealth-bst.yaml`
- **Envelope driver:** `config/envelope-drivers/philhealth-bst.yaml`
- **Campaign migration:** Creates `philhealth-bst` campaign

## Troubleshooting

### Kiosk not showing
- Verify `?skin=pos` is in the URL
- Check `PWA_KIOSK_ENABLED` is not `false`
- Ensure you're logged in (voucher API requires auth)

### Voucher issuance fails
- Check wallet balance (insufficient funds error)
- Verify driver exists if specified
- Check browser console for API errors

### QR code not scanning
- Ensure adequate screen brightness
- Try increasing QR size (print for better scan)
- Verify redemption URL is correct

### Print layout issues
- Use Chrome/Edge for best print support
- Configure printer paper size
- Test with "Save as PDF" first

## Query Parameter Reference

| Parameter | Required | Description | Default |
|-----------|----------|-------------|---------|
| `skin` | Yes | Must be `pos` | - |
| `title` | No | Kiosk title | `Quick Voucher` |
| `subtitle` | No | Subtitle text | - |
| `type` | No | Voucher type | `settlement` |
| `amount` | No | Pre-filled amount | - |
| `target_amount` | No | Pre-filled target | - |
| `inputs` | No | Required fields (comma-sep) | - |
| `driver` | No | Envelope driver ID | - |
| `feedback` | No | Webhook URL | - |
| `campaign` | No | Campaign slug to load | - |
| `amount_label` | No | Amount field label | `Amount` |
| `button_text` | No | Submit button text | `Issue Voucher` |
| `success_title` | No | Success title | `Voucher Issued!` |
| `success_message` | No | Success message | `Scan QR code to redeem` |
