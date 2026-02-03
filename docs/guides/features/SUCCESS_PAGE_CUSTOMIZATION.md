# Success Page Customization

## Overview

The Success page displays after successful voucher redemption. It supports rich content customization including HTML, Markdown, SVG, images, and dynamic content - similar to the Splash page feature.

## Key Features

✅ **Preserves current design** - Clean default UI when no custom message  
✅ **Rich content support** - HTML, Markdown, SVG, images, URLs  
✅ **Template variables** - Dynamic content replacement  
✅ **Configurable buttons** - Customize all button labels  
✅ **XSS protection** - DOMPurify sanitization  
✅ **Backward compatible** - Existing vouchers work unchanged  

## Configuration

### Environment Variables

```bash
# Success Page Configuration
SUCCESS_BUTTON_LABEL="Continue Now"
SUCCESS_DASHBOARD_BUTTON_LABEL="Go to Dashboard"
SUCCESS_REDEEM_ANOTHER_LABEL="Redeem Another"

# Optional: Custom default content
# SUCCESS_DEFAULT_CONTENT="<h1>Success!</h1><p>Voucher {voucher_code} redeemed for {amount}</p>"
```

### Config File: `config/success.php`

```php
return [
    'default_content' => env('SUCCESS_DEFAULT_CONTENT', null),
    'button_label' => env('SUCCESS_BUTTON_LABEL', 'Continue Now'),
    'dashboard_button_label' => env('SUCCESS_DASHBOARD_BUTTON_LABEL', 'Go to Dashboard'),
    'redeem_another_label' => env('SUCCESS_REDEEM_ANOTHER_LABEL', 'Redeem Another'),
];
```

## Content Types

The success page auto-detects and renders multiple content formats:

### 1. Plain Text (Default)
```text
Thank you for redeeming your voucher! The funds will be transferred shortly.
```

### 2. HTML
```html
<div class="text-center">
  <h1 class="text-3xl font-bold text-green-600">Success!</h1>
  <p>Voucher {voucher_code} redeemed for {amount}</p>
  <p class="text-sm text-gray-500">Powered by {app_name}</p>
</div>
```

### 3. Markdown
```markdown
# Redemption Successful!

Thank you for using **{app_name}**!

Your voucher `{voucher_code}` has been redeemed for **{amount}**.
```

### 4. SVG
```html
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="150" height="150" class="mx-auto">
  <circle cx="100" cy="100" r="80" fill="#10b981"/>
  <text x="100" y="110" font-size="24" text-anchor="middle" fill="white" font-weight="bold">✓</text>
</svg>
<p class="text-center mt-4">Voucher {voucher_code} redeemed!</p>
```

### 5. External URL (iframe)
```text
https://partner.com/success-page
```

### 6. Dynamic Images
```html
<div class="text-center">
  <img src="https://cataas.com/cat" class="mx-auto rounded-lg shadow-lg mb-4" style="max-width: 400px;"/>
  <h2 class="text-2xl font-bold">Success!</h2>
  <p>Voucher {voucher_code} redeemed</p>
</div>
```

## Template Variables

All content types support variable replacement:

| Variable | Description | Example Output |
|----------|-------------|----------------|
| `{app_name}` | Application name from `APP_NAME` | "Redeem-X" |
| `{voucher_code}` | Redeemed voucher code | "ABC123" |
| `{amount}` | Formatted amount | "₱1,000.00" |
| `{mobile}` | Redeemer mobile number | "+639171234567" |
| `{currency}` | Currency code | "PHP" |
| `{app_author}` | Application author | "3neti R&D OPC" |
| `{copyright_year}` | Current year | "2025" |
| `{copyright_holder}` | Copyright holder | "3neti R&D OPC" |

**Example:**
```html
<h1>{app_name}</h1>
<p>Voucher {voucher_code} redeemed for {amount}</p>
<p class="text-sm">© {copyright_year} {copyright_holder}</p>
```

## Per-Campaign Customization

### In Voucher Generation Form

Set custom success message in the "Rider" section:

```php
RiderInstructionData::from([
    'message' => '<h1>Partner Success!</h1><img src="https://partner.com/logo.png"/>',
    'url' => 'https://partner.com',
    'redirect_timeout' => 10,
])
```

### Priority Order

1. **Campaign-specific message** (if set in `rider.message`)
2. **Default plain text** (if no `rider.message`)

## Dynamic Image Examples

### Random Cats
```html
<div class="text-center">
  <img src="https://cataas.com/cat?width=600&height=400" alt="Success Cat" class="mx-auto rounded-lg shadow-lg mb-4"/>
  <h2 class="text-2xl font-bold">Success!</h2>
  <p>Voucher {voucher_code} redeemed</p>
</div>
```

### Cat with Voucher Code Overlay
```html
<img src="https://cataas.com/cat/says/{voucher_code}?fontSize=40&fontColor=white&width=600&height=400" class="mx-auto rounded-lg shadow-lg"/>
<p class="text-center mt-4 text-xl font-bold">Redemption Complete!</p>
```

### Random Nature Photos
```html
<div class="text-center space-y-4">
  <img src="https://picsum.photos/800/500?random" alt="Success" class="mx-auto rounded-xl shadow-2xl"/>
  <h2 class="text-3xl font-bold text-green-600">Successfully Redeemed!</h2>
  <p class="text-lg">Voucher: <span class="font-mono font-bold">{voucher_code}</span></p>
  <p class="text-gray-600">Amount: {amount}</p>
</div>
```

## Button Customization

### Global Configuration

```bash
# .env
SUCCESS_BUTTON_LABEL="Proceed to Partner"
SUCCESS_DASHBOARD_BUTTON_LABEL="Return Home"
SUCCESS_REDEEM_ANOTHER_LABEL="Redeem More Vouchers"
```

### Buttons Displayed

**When `rider.url` is set:**
- Continue button (links to `rider.url`)
- Countdown timer with auto-redirect

**When no `rider.url`:**
- Dashboard button (links to `/`)
- Redeem Another button (links to `/disburse`)

## Security

### XSS Protection

All HTML/SVG/Markdown content is sanitized using **DOMPurify** on the frontend:

✅ **Preserved:**
- HTML tags (`<h1>`, `<p>`, `<div>`, etc.)
- SVG markup
- Image tags (`<img>`)
- Iframe embeds (from HTTPS URLs)

❌ **Stripped:**
- `<script>` tags
- `onclick` and other event handlers
- `javascript:` URLs
- Other potentially malicious content

### Safe Examples

**Safe (allowed):**
```html
<h1>Success!</h1>
<img src="https://example.com/image.png" alt="Success"/>
<svg>...</svg>
```

**Unsafe (sanitized automatically):**
```html
<script>alert('XSS')</script>  <!-- Removed -->
<img src="x" onerror="alert('XSS')"/>  <!-- onerror removed -->
<a href="javascript:alert('XSS')">Click</a>  <!-- javascript: removed -->
```

## Usage Examples

### Simple Success Message
```text
Thank you! Your voucher has been redeemed successfully.
```

### Partner Branding
```html
<div class="text-center space-y-4">
  <img src="https://partner.com/logo.png" class="mx-auto" width="200"/>
  <h2 class="text-2xl font-bold">Thank You!</h2>
  <p>Your {amount} voucher has been redeemed</p>
  <p class="text-sm text-gray-500">Powered by {app_name}</p>
</div>
```

### Promotional Content
```html
<div class="bg-gradient-to-r from-green-500 to-blue-500 text-white p-8 rounded-lg text-center">
  <h1 class="text-4xl font-bold mb-4">🎉 Success!</h1>
  <p class="text-2xl font-semibold mb-2">{amount} Redeemed</p>
  <p class="text-xl">Voucher Code: {voucher_code}</p>
  <div class="mt-6 bg-white text-green-600 py-3 px-6 rounded-lg inline-block">
    <p class="font-bold">Get 20% off your next purchase!</p>
    <p class="text-sm">Use code: SAVE20</p>
  </div>
</div>
```

### Markdown with Formatting
```markdown
# 🎉 Redemption Successful!

Thank you for using **{app_name}**!

## Details:
- **Voucher Code:** `{voucher_code}`
- **Amount:** {amount}
- **Status:** ✅ Confirmed

The funds will be transferred to your account within 1-2 business days.
```

## Technical Architecture

### Backend Flow

1. User redeems voucher
2. `DisburseController::success()` called
3. `SuccessContentService` processes `rider.message`
   - Replaces template variables
   - Detects content type
   - Returns processed content array
4. Inertia renders `disburse/Success.vue` with:
   - Processed content
   - Button labels from config
   - Voucher details

### Frontend Flow

1. `Success.vue` receives props
2. If `processed_content` exists:
   - Checks content type
   - Renders with DOMPurify sanitization
   - Uses `marked` for Markdown
3. If no `processed_content`:
   - Shows default plain text message
4. Applies button labels from config

## Backward Compatibility

✅ **Fully backward compatible:**

- Existing vouchers with plain text `rider.message` work unchanged
- If no `rider.message`, default message displays
- Current clean UI preserved
- No database migration needed
- No changes to voucher generation form needed

## Troubleshooting

### Content not rendering

**Check:**
- Voucher has `rider.message` set
- Content passes through `SuccessContentService`
- DOMPurify not stripping content

**Solution:**
- Verify content in database
- Check browser console for errors
- Test with simple HTML first

### Variables not replacing

**Check:**
- Variable syntax: `{variable_name}` (curly braces)
- Variable spelling matches exactly
- Context passed to service

**Solution:**
- Review `SuccessContentService::replaceVariables()`
- Add custom variables if needed

### Images not loading

**Check:**
- URL is HTTPS (not HTTP)
- CORS policy allows image
- URL is publicly accessible

**Solution:**
- Test URL in browser directly
- Use CDN or public image host
- Check network tab in dev tools

## Comparison with Splash Page

| Feature | Splash Page | Success Page |
|---------|-------------|--------------|
| **When** | Before redemption | After redemption |
| **Field** | `rider.splash` | `rider.message` |
| **Timeout** | `rider.splash_timeout` | `rider.redirect_timeout` |
| **Content** | HTML/Markdown/SVG/URL | HTML/Markdown/SVG/URL |
| **Variables** | ✅ Yes | ✅ Yes |
| **Default** | Auto-generated | Plain text |
| **Config** | `config/splash.php` | `config/success.php` |

## Future Enhancements

- Extract common rendering logic to shared service
- Add success page analytics
- QR code generation on success
- Social sharing buttons
- Receipt generation (PDF download)
- Transaction tracking link

## Support

**Test files:**
- `tests/Feature/SuccessPageCustomizationTest.php`

**Source files:**
- `config/success.php` - Configuration
- `app/Services/SuccessContentService.php` - Content processing
- `app/Http/Controllers/Disburse/DisburseController.php` - Controller
- `resources/js/pages/disburse/Success.vue` - Frontend component
