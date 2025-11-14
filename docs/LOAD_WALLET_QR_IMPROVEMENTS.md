# Load Wallet QR Code Improvements

## Overview

This document describes the enhanced Load Wallet page implementation that allows users to generate QR Ph codes via the Omnipay gateway and share them through multiple channels so others can scan and load funds into their wallet.

## Motivation

The original Load Wallet page had limited functionality:
- Basic QR display in a small card
- Single "Regenerate" button
- No sharing capabilities
- Limited user guidance

The improved version provides:
- Professional QR code generation via Omnipay/NetBank gateway
- Multiple sharing options (copy, download, email, SMS, social media)
- Better UX with clear instructions
- Responsive design for mobile and desktop
- Loading and error states
- Support for both dynamic and fixed-amount QR codes

## Architecture

### Backend Components

#### 1. API Endpoint

**Route:** `POST /api/v1/wallet/generate-qr`

**Controller/Action:** `App\Actions\Api\Wallet\GenerateQrCode`

**Request:**
```json
{
  "amount": 100.50,  // Optional: null or 0 for dynamic amount QR
  "force": false     // Optional: true to bypass cache and regenerate
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "qr_code": "data:image/png;base64,...",  // Base64 PNG from gateway
    "qr_url": "https://...",                  // Gateway's shareable URL (if available)
    "qr_id": "QR-ABC123",                     // Gateway QR identifier
    "expires_at": "2025-11-15T00:00:00Z",     // Expiration timestamp (if available)
    "account": "user@example.com",            // User's account identifier
    "amount": 100.50,                         // Fixed amount or null for dynamic
    "shareable_url": "https://app.com/qr/..."// Our app's shareable URL
  },
  "message": "QR code generated successfully"
}
```

**Implementation:**
- Uses `OmnipayPaymentGateway->generate()` method
- Calls `$gateway->generateQr()` with account and amount
- Returns complete QR data with both gateway URL and app URL
- **Caches QR codes** to reduce bank API calls (default: 1 hour TTL)
- Cache key: `qr_code:{user_id}:{amount|dynamic}`
- Force parameter bypasses cache for regeneration
- Handles errors gracefully

#### 2. Omnipay Integration

**Gateway Method:** `OmnipayPaymentGateway::generate(string $account, Money $amount): string`

**Parameters:**
- `$account` - User's account identifier (email or mobile)
- `$amount` - Money object with amount in centavos (0 for dynamic QR)

**Returns:**
- Base64 PNG QR code data

**Additional Gateway Response Data:**
- `getQrUrl()` - Shareable URL from NetBank
- `getQrId()` - QR identifier for tracking
- `getExpiresAt()` - Expiration timestamp

### Frontend Components

#### 1. Page Component

**File:** `resources/js/pages/Wallet/Load.vue`

**Features:**
- Responsive layout (mobile: stacked, desktop: side-by-side)
- QR display on left, share panel on right
- Balance display in header
- Optional amount input for fixed-amount QRs
- Loading and error states

#### 2. QR Display Component

**File:** `resources/js/components/domain/QrDisplay.vue`

**Enhancements:**
- Loading skeleton/spinner
- Error state with retry button
- Proper image sizing and aspect ratio
- No QR code placeholder state

#### 3. QR Share Panel Component

**File:** `resources/js/components/QrSharePanel.vue`

**Features:**
- Copy QR link button
- Copy QR image button
- Download QR button
- Email link button
- SMS link button
- WhatsApp share button
- Facebook Messenger share button (optional)
- Telegram share button (optional)
- Instructions and security notice

#### 4. Composables

**File:** `resources/js/composables/useQrGeneration.ts`

**Purpose:** API integration and state management

**API:**
```typescript
interface UseQrGenerationReturn {
  qrData: Ref<QrCodeData | null>;
  loading: Ref<boolean>;
  error: Ref<string | null>;
  generate: (amount?: number) => Promise<void>;
  regenerate: () => Promise<void>;
}
```

**File:** `resources/js/composables/useQrShare.ts`

**Purpose:** Share utilities and social media integration

**API:**
```typescript
interface UseQrShareReturn {
  copyQrLink: (url: string) => Promise<boolean>;
  copyQrImage: (base64: string) => Promise<boolean>;
  downloadQr: (base64: string, filename: string) => void;
  getEmailLink: (url: string, subject: string) => string;
  getSmsLink: (url: string) => string;
  getWhatsAppLink: (url: string, message: string) => string;
  getFacebookMessengerLink: (url: string) => string;
  getTelegramLink: (url: string, message: string) => string;
  shareNative: (data: ShareData) => Promise<boolean>; // Web Share API
}
```

## User Flow

### 1. Generate QR Code

1. User navigates to Load Wallet page
2. Page automatically generates QR code on mount
3. API calls Omnipay gateway with user's account
4. Gateway returns QR code data
5. QR code is displayed in the UI

### 2. Share QR Code

User has multiple options:

**Copy QR Link:**
- Clicks "Copy Link" button
- Gateway URL or app URL is copied to clipboard
- Toast notification confirms success

**Copy QR Image:**
- Clicks "Copy Image" button
- Base64 image is converted to blob and copied
- Toast notification confirms success

**Download QR:**
- Clicks "Download" button
- QR code is downloaded as PNG file
- Filename: `wallet-qr-{timestamp}.png`

**Email Link:**
- Clicks "Email" button
- Opens default email client with pre-filled message
- Subject: "Scan to send me money"
- Body: "Scan this QR code to load my wallet: {url}"

**SMS Link:**
- Clicks "SMS" button
- Opens SMS app (mobile) with pre-filled message
- Body: "Scan this QR: {url}"

**WhatsApp:**
- Clicks "WhatsApp" button
- Opens WhatsApp with shareable message
- URL: `https://wa.me/?text=...`

**Other Social Media:**
- Similar deep links for FB Messenger, Telegram

### 3. Regenerate QR Code

1. User clicks "Regenerate" button
2. New QR code is generated with fresh reference ID
3. Previous QR code is replaced
4. Useful if QR expires or user wants a new one

## Technical Implementation Details

### QR Code Types

**Dynamic Amount QR** (Default)
```typescript
// Request with amount = 0 or null
{ amount: 0 }

// User scanning can enter any amount
```

**Fixed Amount QR**
```typescript
// Request with specific amount
{ amount: 100.50 }

// QR encodes ₱100.50 - scanner cannot change amount
```

### Share Methods

#### Copy to Clipboard

Uses modern Clipboard API:
```typescript
await navigator.clipboard.writeText(text);
// For images:
await navigator.clipboard.write([
  new ClipboardItem({
    'image/png': blob
  })
]);
```

#### Download File

Creates blob and triggers download:
```typescript
const blob = await fetch(base64Data).then(r => r.blob());
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = filename;
a.click();
URL.revokeObjectURL(url);
```

#### Social Media Deep Links

**Email:**
```
mailto:?subject={subject}&body={body}
```

**SMS:**
```
sms:?body={message}
```

**WhatsApp:**
```
https://wa.me/?text={encodedMessage}
```

**Facebook Messenger:**
```
fb-messenger://share?link={encodedUrl}
```

**Telegram:**
```
https://t.me/share/url?url={encodedUrl}&text={encodedText}
```

### Web Share API (Mobile)

For mobile devices that support it:
```typescript
if (navigator.share) {
  await navigator.share({
    title: 'My Wallet QR Code',
    text: 'Scan to send me money',
    url: shareableUrl
  });
}
```

## UI/UX Design

### Layout

**Desktop (≥768px):**
```
┌──────────────────────────────────────────────────────┐
│  Load Your Wallet           Balance: ₱1,234.56       │
├───────────────────┬──────────────────────────────────┤
│                   │  Share This QR Code               │
│                   │  ┌────────────────────────────┐  │
│   [QR Code]       │  │ 📋 Copy QR Link            │  │
│   288x288px       │  │ 🖼️  Copy QR Image          │  │
│                   │  │ ⬇️  Download QR             │  │
│                   │  │ ✉️  Email Link             │  │
│                   │  │ 💬 SMS Link                │  │
│   [Regenerate]    │  │ 📱 WhatsApp                │  │
│                   │  └────────────────────────────┘  │
│                   │                                   │
│                   │  Instructions                     │
│                   │  Share this QR code with anyone   │
│                   │  who wants to send you money.     │
│                   │                                   │
│                   │  Security Notice                  │
│                   │  This QR code is linked to your   │
│                   │  account. Only share with         │
│                   │  trusted contacts.                │
└───────────────────┴──────────────────────────────────┘
```

**Mobile (<768px):**
```
┌────────────────────────────┐
│  Load Your Wallet          │
│  Balance: ₱1,234.56        │
├────────────────────────────┤
│                            │
│      [QR Code]             │
│      256x256px             │
│                            │
│    [Regenerate]            │
│                            │
├────────────────────────────┤
│  Share This QR Code        │
│  ┌──────────────────────┐ │
│  │ 📋 Copy QR Link      │ │
│  │ 🖼️  Copy QR Image    │ │
│  │ ⬇️  Download QR       │ │
│  │ ✉️  Email Link       │ │
│  │ 💬 SMS Link          │ │
│  │ 📱 WhatsApp          │ │
│  └──────────────────────┘ │
│                            │
│  Instructions              │
│  Share this QR code...     │
└────────────────────────────┘
```

### Component Hierarchy

```
Load.vue
├── QrDisplay.vue (QR image with loading/error)
├── QrSharePanel.vue (sharing buttons)
│   ├── Button (Copy Link)
│   ├── Button (Copy Image)
│   ├── Button (Download)
│   ├── Button (Email)
│   ├── Button (SMS)
│   ├── Button (WhatsApp)
│   └── Instructions Card
└── Button (Regenerate)
```

## Error Handling

### Backend Errors

**Gateway Failure:**
- Catch exception from Omnipay gateway
- Return 500 with error message
- Log error details

**Invalid Amount:**
- Validate amount > 0 if provided
- Return 422 with validation error

**User Not Found:**
- Return 404 if user not authenticated
- Redirect to login

### Frontend Errors

**API Failure:**
- Display error message in QR display area
- Show "Retry" button
- Toast notification with error

**Clipboard API Not Supported:**
- Fallback to manual copy instructions
- Show alert with text to copy

**Share API Not Supported:**
- Hide native share button if not available
- Provide alternative share methods

## Caching Strategy

### Why Cache QR Codes?

1. **Reduce Bank API Calls:** Each QR generation hits the NetBank API - caching saves costs
2. **Faster Response:** Cached QRs return instantly without network latency
3. **Better UX:** Users get immediate results on page load
4. **Reliability:** Less dependent on bank API availability

### Cache Implementation

**Cache Key Format:**
```
qr_code:{user_id}:{amount|dynamic}
```

**Examples:**
- `qr_code:123:dynamic` - Dynamic amount QR for user 123
- `qr_code:123:100.50` - Fixed ₱100.50 QR for user 123

**Cache TTL:**
- Configurable via `PAYMENT_GATEWAY_QR_CACHE_TTL` (default: 3600 seconds / 1 hour)
- Set to `0` to disable caching
- QR codes are cached per user and amount combination

**Cache Invalidation:**
- Automatic: Cache expires after TTL
- Manual: Clicking "Regenerate" sends `force=true` to bypass cache
- Programmatic: `Cache::forget("qr_code:{user_id}:{amount}")`

**Configuration:**

In `.env`:
```bash
# Cache QR codes for 2 hours (7200 seconds)
PAYMENT_GATEWAY_QR_CACHE_TTL=7200

# Disable caching (always fetch from bank)
PAYMENT_GATEWAY_QR_CACHE_TTL=0
```

In `config/payment-gateway.php`:
```php
'qr_cache_ttl' => env('PAYMENT_GATEWAY_QR_CACHE_TTL', 3600),
```

## Security Considerations

1. **Authentication Required:** All endpoints require authenticated user
2. **Rate Limiting:** Limit QR generation requests (e.g., 10/minute)
3. **QR Expiration:** Gateway QR codes expire after set time (check `expires_at`)
4. **Account Validation:** Only generate QR for authenticated user's account
5. **HTTPS Only:** QR URLs must use HTTPS
6. **Sharing Guidance:** Warn users to only share with trusted contacts
7. **Cache Security:** QR codes in cache are user-specific (can't access others' QRs)

## Testing

### Manual Testing

1. **Generate QR:**
   - Visit `/wallet/load`
   - Verify QR displays correctly
   - Check balance is shown

2. **Copy Link:**
   - Click "Copy Link" button
   - Paste into browser - verify it's valid URL

3. **Download QR:**
   - Click "Download" button
   - Verify PNG file downloads with correct name

4. **Social Shares:**
   - Click email/SMS/WhatsApp buttons
   - Verify correct app opens with pre-filled message

5. **Regenerate:**
   - Click "Regenerate" button
   - Verify new QR appears
   - Verify it's different from previous

6. **Responsive:**
   - Test on mobile (stacked layout)
   - Test on tablet (balanced layout)
   - Test on desktop (side-by-side layout)

### Automated Testing

**Backend (Pest):**
```php
it('generates QR code successfully')
it('validates amount parameter')
it('requires authentication')
it('handles gateway errors')
it('rate limits requests')
```

**Frontend (Vitest/Cypress):**
```typescript
describe('Load Wallet Page', () => {
  it('displays QR code on mount')
  it('shows loading state during generation')
  it('handles generation errors')
  it('copies link to clipboard')
  it('downloads QR image')
  it('opens email client with correct data')
  it('regenerates QR on button click')
})
```

## Future Enhancements

1. **QR Code History:** Show last 5 generated QR codes
2. **Custom Amount UI:** Toggle between dynamic and fixed amount
3. **QR Analytics:** Track how many times QR was scanned/shared
4. **Public QR Page:** `/qr/{id}` for viewing QR without login
5. **QR Templates:** Save favorite QR configurations
6. **Bulk QR Generation:** Generate multiple QRs for different amounts
7. **Print View:** Optimized layout for printing QR codes
8. **Webhook Integration:** Get notified when QR is scanned

## References

- [Omnipay Documentation](../packages/payment-gateway/docs/OMNIPAY_INTEGRATION_PLAN.md)
- [NetBank Gateway](../packages/payment-gateway/src/Omnipay/Netbank/Gateway.php)
- [Web Share API](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/share)
- [Clipboard API](https://developer.mozilla.org/en-US/docs/Web/API/Clipboard_API)

## Changelog

### 2025-11-14 - QR Code Caching
- Added QR code caching to reduce bank API calls
- Configurable cache TTL (default: 1 hour)
- Force regenerate bypasses cache
- Cache key per user and amount combination
- Improved performance and reduced costs

### 2025-11-14 - Initial Implementation
- Created comprehensive QR sharing system
- Integrated Omnipay gateway for QR generation
- Added multiple share methods (copy, download, social media)
- Implemented responsive UI
- Added proper error handling and loading states
