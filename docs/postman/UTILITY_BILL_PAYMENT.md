# Utility Bill Payment - Postman Collection

This collection demonstrates how utility companies can use Redeem-X to embed QR codes in printed invoices for customer payments.

## Use Case

**Problem:** Utility companies send printed invoices to customers. Customers want to pay via mobile wallets (GCash, Maya, BPI Pay), but utility companies need to track which invoice was paid.

**Solution:** Generate a payable voucher with QR code for each invoice. Customer scans QR → pays via mobile wallet → utility company receives payment notification with invoice details.

## Collection File

`redeem-x-utility-bill-payment.postman_collection.json`

## Prerequisites

### 1. Chrome Interceptor Setup

This collection uses **Chrome Interceptor** for authentication (not API tokens).

**Why?** Redeem-X uses WorkOS for authentication, which doesn't provide a traditional login API endpoint. Instead, we sync browser cookies to Postman.

**Setup Steps:**

1. Install [Postman Interceptor](https://chrome.google.com/webstore/detail/postman-interceptor/aicmkgpgakddgnaphhhpliifpcfhicfo) Chrome extension
2. Enable Interceptor in Postman:
   - Click satellite icon (bottom right)
   - Toggle "Interceptor" to ON
   - Select "Sync cookies" option
3. Log in to `http://redeem-x.test` in Chrome browser
4. Run "00 - Setup / Get CSRF Token" in Postman
5. ✅ You're authenticated! Run other requests.

**Troubleshooting:**
- If you get 401/403: Refresh your browser login at `http://redeem-x.test`
- Verify Interceptor icon shows green (enabled)
- Check you're logged in at the same domain as `{{base_url}}`

### 2. Collection Variables

Set these in Postman:

| Variable | Value | Description |
|----------|-------|-------------|
| `base_url` | `http://redeem-x.test` | Your local domain |
| `voucher_code` | (auto-set) | Populated after voucher generation |

## Collection Structure

### 00 - Setup (Run First)

**Get CSRF Token**
- `GET /sanctum/csrf-cookie`
- Syncs authentication cookies from browser
- **Run this first** before any other requests

### 01 - Generate Payable Voucher

**Generate Payable Voucher with Invoice Data**
- `POST /api/v1/vouchers`
- Creates a payable voucher with invoice metadata
- Returns voucher code for QR generation

**Request Body:**
```json
{
  "voucher_type": "payable",
  "amount": 0,
  "target_amount": 2500.00,
  "count": 1,
  "external_metadata": {
    "invoice_number": "INV-2026-001234",
    "account_number": "ACC-789456",
    "customer_name": "Juan Dela Cruz",
    "customer_address": "123 Main St, Makati City, Metro Manila",
    "invoice_date": "2026-01-15",
    "due_date": "2026-02-15",
    "billing_period": "December 2025",
    "previous_balance": 0,
    "current_charges": 2500.00,
    "service_type": "Electricity"
  },
  "feedback_email": "billing@utility.com",
  "ttl_days": 60,
  "prefix": "BILL",
  "mask": "***-***"
}
```

**Key Fields:**
- `voucher_type: "payable"` - Allows multiple payments up to target amount
- `amount: 0` - Payable vouchers start at zero, customer adds payments
- `target_amount` - Full invoice amount to be collected
- `external_metadata` - Invoice details (customizable)
- `feedback_email` - Email to receive payment notifications
- `prefix` + `mask` - Generates codes like `BILL-ABC-DEF`

**Response:**
```json
{
  "data": {
    "vouchers": [
      {
        "code": "BILL-ABC-DEF",
        "voucher_type": "payable",
        "target_amount": 2500.00,
        "amount": 0,
        "status": "active",
        "metadata": {
          "external": {
            "invoice_number": "INV-2026-001234",
            "account_number": "ACC-789456",
            ...
          }
        }
      }
    ]
  }
}
```

### 02 - Get QR Code

**Generate QR Code for Printing**
- `GET /api/v1/vouchers/{code}/qr`
- Returns base64 data URI for PDF embedding

**Response:**
```json
{
  "data": {
    "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
    "redemption_url": "http://redeem-x.test/pay?code=BILL-ABC-DEF",
    "voucher_code": "BILL-ABC-DEF"
  }
}
```

**Usage for Printing:**
1. Decode base64 string from `qr_code` field
2. Embed image in PDF invoice template
3. Customer scans QR → redirects to `/pay?code=BILL-ABC-DEF`
4. Customer pays via GCash/Maya/BPI Pay

### 03 - Payment Status

**Check Payment Status**
- `GET /api/v1/vouchers/{code}`
- Returns voucher details with current payment status

**Response:**
```json
{
  "data": {
    "voucher": {
      "code": "BILL-ABC-DEF",
      "voucher_type": "payable",
      "target_amount": 2500.00,
      "amount": 1000.00,
      "status": "active",
      "metadata": {
        "external": {
          "invoice_number": "INV-2026-001234",
          ...
        }
      }
    }
  }
}
```

**Use Case:** "Has this invoice been paid? How much is left?"

**Get Payment History**
- `GET /api/v1/vouchers/{code}/payments`
- Returns array of individual payment transactions

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "amount": 500.00,
      "created_at": "2026-01-16T10:30:00Z",
      "reference": "TXN-001"
    },
    {
      "amount": 500.00,
      "created_at": "2026-01-17T14:20:00Z",
      "reference": "TXN-002"
    }
  ],
  "message": "Payment history retrieved successfully"
}
```

**Use Case:** "Show me all payments made on this invoice" (for reconciliation, audit trail)

## Workflow Example

### Utility Company (Monthly Billing)

1. **Generate Invoice:**
   ```bash
   POST /api/v1/vouchers
   {
     "voucher_type": "payable",
     "target_amount": 2500.00,
     "external_metadata": {
       "invoice_number": "INV-2026-001234",
       "account_number": "ACC-789456",
       "customer_name": "Juan Dela Cruz",
       ...
     }
   }
   ```
   
2. **Get QR Code:**
   ```bash
   GET /api/v1/vouchers/BILL-ABC-DEF/qr
   ```
   
3. **Print Invoice:**
   - Embed QR code in PDF invoice
   - Mail to customer
   
4. **Customer Pays:**
   - Scans QR code with phone camera
   - Redirects to payment page: `http://redeem-x.test/pay?code=BILL-ABC-DEF`
   - Pays ₱2500 via GCash
   
5. **Track Payment:**
   ```bash
   GET /api/v1/vouchers/BILL-ABC-DEF
   # Shows: amount: 2500, status: "closed"
   ```
   
6. **Reconciliation:**
   ```bash
   GET /api/v1/vouchers/BILL-ABC-DEF/payments
   # Shows: [{amount: 2500, created_at: "...", reference: "TXN-001"}]
   ```

### Partial Payments

Customers can pay in installments:

1. **First Payment:**
   - Customer pays ₱1000 on Jan 16
   - `GET /api/v1/vouchers/BILL-ABC-DEF` → `amount: 1000, status: "active"`
   
2. **Second Payment:**
   - Customer pays ₱1500 on Jan 17
   - `GET /api/v1/vouchers/BILL-ABC-DEF` → `amount: 2500, status: "closed"`
   
3. **Payment History:**
   ```json
   [
     {"amount": 1000, "created_at": "2026-01-16"},
     {"amount": 1500, "created_at": "2026-01-17"}
   ]
   ```

## Important Notes

### Idempotency

Voucher generation includes `Idempotency-Key` header to prevent duplicate vouchers:

```http
POST /api/v1/vouchers
Idempotency-Key: {{$randomUUID}}
```

Postman auto-generates a unique UUID per request. Same key = same voucher returned.

### Mask Validation

Laravel validates mask must contain **max 6 asterisks**:

✅ Valid: `***-***` (6 asterisks)  
❌ Invalid: `****-****` (8 asterisks)

### Authentication

All endpoints (except CSRF token) require authentication via:
- **Development:** Chrome Interceptor (browser cookies)
- **Production:** Laravel Sanctum tokens

### Rate Limiting

Public endpoints (QR, payments) are rate-limited:
- `/api/v1/vouchers/{code}/qr`: 20 requests/minute
- `/api/v1/vouchers/{code}/payments`: 60 requests/minute

## Testing Checklist

- [ ] Run "00 - Setup / Get CSRF Token" first
- [ ] Generate voucher → Check `voucher_code` variable is set
- [ ] Get QR code → Verify base64 data URI returned
- [ ] Check payment status → Confirm `target_amount` and `amount` fields
- [ ] Get payment history → Should return empty array `[]` (no payments yet)

## Production Deployment

### 1. API Token Authentication

Replace Chrome Interceptor with Sanctum tokens:

```http
POST /api/v1/vouchers
Authorization: Bearer {YOUR_API_TOKEN}
Idempotency-Key: {UNIQUE_UUID}
```

### 2. Webhook Notifications

Configure webhook URL to receive payment notifications:

```json
{
  "feedback_email": "billing@utility.com",
  "feedback_webhook": "https://utility.com/api/webhooks/payment-received"
}
```

### 3. Bulk Generation

For monthly billing cycles (millions of invoices):

```bash
POST /api/v1/vouchers/bulk-create
[
  {
    "voucher_type": "payable",
    "target_amount": 2500.00,
    "external_metadata": {...},
    ...
  },
  ...
]
```

### 4. QR Code Caching

Cache QR codes to avoid regenerating on every request:

```php
// Cache for 60 days (TTL of voucher)
Cache::remember("qr:BILL-ABC-DEF", 60 * 24 * 60, function() {
    return Http::get("$baseUrl/api/v1/vouchers/BILL-ABC-DEF/qr");
});
```

## Support

For questions or issues, refer to:
- Main documentation: `docs/postman/README.md`
- API routes: `routes/api/vouchers.php`
- Voucher generation: `app/Actions/Api/Vouchers/GenerateVouchers.php`
