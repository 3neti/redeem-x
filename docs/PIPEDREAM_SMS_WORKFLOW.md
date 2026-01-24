# Pipedream SMS-to-Voucher Workflow

This document describes the Pipedream workflow for generating vouchers via SMS commands sent to shortcode **22560537**.

## Overview

The workflow receives SMS messages from the Omni Channel API and generates vouchers using the **redeem-x** API (`https://redeem-x.laravel.cloud`).

### Flow

```
User SMS → 22560537 → Omni Channel → Pipedream → redeem-x API → Response → SMS Reply
```

## Files

- `pipedream-generate-voucher.js` - Main workflow script for Pipedream

## Configuration

### Current Settings (Hardcoded for Testing)

The script is currently configured with hardcoded values for initial testing:

- **API Token**: `3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de`
- **API URL**: `https://redeem-x.laravel.cloud/api/v1`
- **Default Amount**: ₱50
- **Default Count**: 1 voucher
- **Test Mobile**: 639173011987

### Environment Variables (Production)

For production use, configure these in Pipedream:

| Variable | Description | Default |
|----------|-------------|---------|
| `REDEEMX_API_URL` | redeem-x API base URL | `https://redeem-x.laravel.cloud/api/v1` |
| `REDEEMX_API_TOKEN` | Sanctum bearer token from redeem-x | *(required)* |

## Usage in Pipedream

### 1. Create New Workflow

1. Go to [Pipedream](https://pipedream.com)
2. Create a new workflow
3. Add a trigger (HTTP webhook or scheduled)

### 2. Add Code Step

1. Add a new step: **Run Node.js Code**
2. Copy the contents of `pipedream-generate-voucher.js`
3. Paste into the code editor

### 3. Test the Workflow

Click "Test" in Pipedream to run the workflow. The script will:

1. Generate an idempotency key based on timestamp
2. Call redeem-x API with hardcoded values
3. Return voucher details or error message

### Expected Output (Success)

```json
{
  "status": "success",
  "message": "✅ Voucher ABC-1234 generated (₱50). Redeem at: https://redeem-x.laravel.cloud/disburse",
  "voucher": {
    "code": "ABC-1234",
    "amount": 50,
    "currency": "PHP",
    "status": "active",
    "redemption_url": "https://redeem-x.laravel.cloud/disburse"
  }
}
```

### Exported Values (for next step)

The script exports these values for use in subsequent Pipedream steps:

- `$.export("status")` - `"success"` or `"error"`
- `$.export("message")` - Formatted SMS reply message
- `$.export("voucher")` - Voucher object (on success)
- `$.export("error")` - Error details (on failure)

## API Details

### Endpoint

```
POST https://redeem-x.laravel.cloud/api/v1/vouchers
```

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
Idempotency-Key: sms-{mobile}-{timestamp}
```

### Request Body

```json
{
  "amount": 50,
  "count": 1,
  "feedback_mobile": "+639173011987"
}
```

### Response (201 Created)

```json
{
  "count": 1,
  "vouchers": [
    {
      "code": "ABC-1234",
      "amount": 50,
      "currency": "PHP",
      "status": "active",
      "expires_at": null,
      "redemption_url": "https://redeem-x.laravel.cloud/disburse"
    }
  ],
  "total_amount": 50,
  "currency": "PHP"
}
```

## Error Handling

The script handles these error scenarios:

| HTTP Status | Error | User Message |
|-------------|-------|--------------|
| 400 | Missing Idempotency-Key | System error (contact support) |
| 401 | Invalid token | System error (contact support) |
| 403 | Insufficient balance | Insufficient wallet balance. Please top up. |
| 422 | Validation error | Invalid request. Check amount format. |
| 429 | Rate limit | Too many requests. Try again later. |
| Other | Network/server error | Failed to generate voucher. Try again. |

## Testing

### Local API Test (with curl)

Test the redeem-x API directly:

```bash
curl -X POST https://redeem-x.laravel.cloud/api/v1/vouchers \
  -H "Authorization: Bearer 3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Idempotency-Key: test-$(date +%s)" \
  -d '{"amount": 50, "count": 1}'
```

### Pipedream Test

1. Click "Test" button in Pipedream workflow
2. Check the execution logs for:
   - Request details
   - Response data
   - Exported values
3. Verify voucher created in redeem-x dashboard

### Idempotency Test

Run the workflow twice with the same idempotency key to verify:
- First call: Creates new voucher (201)
- Second call: Returns cached response (same voucher)

## Next Steps

After successful testing, the following enhancements are planned:

1. **Dynamic Amount Parsing**
   - Parse SMS text to extract amount
   - Handle various formats (₱50, PHP 50, P50, 50)

2. **SMS Integration**
   - Uncomment SMS data extraction lines
   - Connect to actual Omni Channel webhook payload

3. **Reply SMS Step**
   - Add subsequent Pipedream step to send SMS reply
   - Use `steps.generate_voucher.message` as SMS content

4. **Error Notifications**
   - Log failures to monitoring service
   - Alert on repeated failures

5. **Additional Features**
   - Support for custom prefix/mask
   - Expiration days configuration
   - Input field requirements

## Troubleshooting

### Token Issues

If you get 401 errors:
1. Verify token is valid in redeem-x dashboard
2. Check token hasn't expired
3. Ensure token has sufficient permissions

### Insufficient Balance

If you get 403 errors:
1. Check wallet balance in redeem-x dashboard
2. Top up account if needed
3. Verify amount doesn't exceed available balance

### Network Timeouts

If requests timeout:
1. Check redeem-x API status
2. Verify Pipedream can reach external URLs
3. Increase timeout in axios config if needed

## Support

For issues or questions:
1. Check Pipedream execution logs
2. Verify redeem-x API documentation
3. Review error messages in console output
