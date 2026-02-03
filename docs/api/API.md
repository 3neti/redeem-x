# API Documentation

Redeem-X Voucher System REST API v1

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

All authenticated endpoints require either:
- **Session-based authentication** (for web UI via Laravel Sanctum)
- **Bearer token authentication** (for API clients)

**Headers:**
```
Authorization: Bearer {your_api_token}
Accept: application/json
Content-Type: application/json
```

## Rate Limits

| Endpoint Type | Limit |
|--------------|-------|
| Authenticated | 60 requests/minute |
| Public Redemption | 10 requests/minute |
| Webhooks | 30 requests/minute |

## Response Format

All responses follow this structure:

**Success Response:**
```json
{
  "data": {
    // Response data
  }
}
```

**Error Response:**
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Endpoints

### Vouchers

#### List Vouchers

```http
GET /vouchers
```

**Query Parameters:**
- `per_page` (integer, optional): Items per page (default: 15)
- `page` (integer, optional): Page number
- `status` (string, optional): Filter by status (`active`, `redeemed`, `expired`)
- `search` (string, optional): Search by voucher code

**Response:**
```json
{
  "data": {
    "data": [
      {
        "code": "ABC123",
        "status": "active",
        "amount": 100,
        "currency": "PHP",
        "created_at": "2025-01-01T00:00:00Z",
        "expires_at": "2025-12-31T23:59:59Z",
        "is_expired": false,
        "is_redeemed": false,
        "can_redeem": true
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7,
      "from": 1,
      "to": 15
    }
  }
}
```

#### Show Voucher

```http
GET /vouchers/{code}
```

**Response:**
```json
{
  "data": {
    "voucher": {
      "code": "ABC123",
      "status": "active",
      "amount": 100,
      "currency": "PHP",
      "created_at": "2025-01-01T00:00:00Z",
      "expires_at": "2025-12-31T23:59:59Z",
      "redeemed_at": null,
      "starts_at": null,
      "is_expired": false,
      "is_redeemed": false,
      "can_redeem": true,
      "owner": {
        "name": "John Doe",
        "email": "john@example.com"
      }
    }
  }
}
```

#### Generate Vouchers

```http
POST /vouchers
```

**Request Body:**
```json
{
  "amount": 100,
  "count": 10,
  "prefix": "PROMO",
  "mask": "####-####",
  "ttl_days": 30,
  "input_fields": ["mobile", "email"],
  "validation_secret": "SECRET123",
  "validation_mobile": "09171234567",
  "feedback_email": "feedback@example.com",
  "feedback_mobile": "09171234567",
  "feedback_webhook": "https://your-domain.com/webhook",
  "rider_message": "Thank you for using our service",
  "rider_url": "https://your-domain.com/success"
}
```

**Response:**
```json
{
  "data": {
    "count": 10,
    "total_amount": 1000,
    "currency": "PHP",
    "vouchers": [
      {
        "code": "PROMO-1234",
        "amount": 100,
        "currency": "PHP",
        "status": "active",
        "created_at": "2025-01-01T00:00:00Z"
      }
    ]
  }
}
```

#### Cancel Voucher

```http
DELETE /vouchers/{code}
```

**Response:**
```json
{
  "data": {
    "message": "Voucher cancelled successfully"
  }
}
```

### Transactions

#### List Transactions

```http
GET /transactions
```

**Query Parameters:**
- `per_page` (integer, optional): Items per page (default: 20)
- `page` (integer, optional): Page number
- `search` (string, optional): Search by voucher code
- `date_from` (string, optional): Filter from date (Y-m-d format)
- `date_to` (string, optional): Filter to date (Y-m-d format)

**Response:**
```json
{
  "data": {
    "data": [
      {
        "code": "ABC123",
        "amount": 100,
        "currency": "PHP",
        "status": "redeemed",
        "redeemed_at": "2025-01-05T10:30:00Z",
        "created_at": "2025-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 50,
      "last_page": 3
    }
  }
}
```

#### Get Transaction Stats

```http
GET /transactions/stats
```

**Query Parameters:**
- `date_from` (string, optional): Start date for stats
- `date_to` (string, optional): End date for stats

**Response:**
```json
{
  "data": {
    "total": 150,
    "total_amount": 15000,
    "today": 5,
    "this_month": 75,
    "currency": "PHP"
  }
}
```

#### Export Transactions

```http
GET /transactions/export
```

**Query Parameters:**
- `search` (string, optional): Filter by voucher code
- `date_from` (string, optional): From date
- `date_to` (string, optional): To date

**Response:**
CSV file download

### Contacts

#### List Contacts

```http
GET /contacts
```

**Query Parameters:**
- `per_page` (integer, optional): Items per page (default: 15)
- `page` (integer, optional): Page number
- `search` (string, optional): Search by name, mobile, or email

**Response:**
```json
{
  "data": {
    "data": [
      {
        "id": 1,
        "mobile": "09171234567",
        "name": "John Doe",
        "email": "john@example.com",
        "country": "PH",
        "updated_at": "2025-01-05T10:00:00Z",
        "created_at": "2025-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 30,
      "last_page": 2
    },
    "stats": {
      "total": 30,
      "withEmail": 25,
      "withName": 28
    }
  }
}
```

#### Show Contact

```http
GET /contacts/{id}
```

**Response:**
```json
{
  "data": {
    "contact": {
      "id": 1,
      "mobile": "09171234567",
      "name": "John Doe",
      "email": "john@example.com",
      "country": "PH",
      "bank_account": null,
      "updated_at": "2025-01-05T10:00:00Z",
      "created_at": "2025-01-01T00:00:00Z"
    }
  }
}
```

#### Get Contact's Vouchers

```http
GET /contacts/{id}/vouchers
```

**Response:**
```json
{
  "data": {
    "vouchers": [
      {
        "code": "ABC123",
        "amount": 100,
        "currency": "PHP",
        "status": "redeemed",
        "redeemed_at": "2025-01-05T10:30:00Z",
        "created_at": "2025-01-01T00:00:00Z"
      }
    ]
  }
}
```

### Redemption (Public)

#### Validate Voucher

```http
POST /redemption/validate
```

**Request Body:**
```json
{
  "code": "ABC123"
}
```

**Response:**
```json
{
  "data": {
    "valid": true,
    "voucher": {
      "code": "ABC123",
      "amount": 100,
      "currency": "PHP",
      "expires_at": "2025-12-31T23:59:59Z",
      "requires_secret": false,
      "input_fields": ["mobile", "email"]
    }
  }
}
```

#### Redeem Voucher

```http
POST /redemption/redeem
```

**Request Body:**
```json
{
  "code": "ABC123",
  "mobile": "09171234567",
  "email": "user@example.com",
  "secret": "SECRET123",
  "name": "John Doe"
}
```

**Response:**
```json
{
  "data": {
    "success": true,
    "message": "Voucher redeemed successfully!",
    "voucher": {
      "code": "ABC123",
      "amount": 100,
      "currency": "PHP"
    },
    "rider": {
      "message": "Thank you for using our service",
      "url": "https://your-domain.com/success"
    }
  }
}
```

### Settings

#### Get Profile

```http
GET /settings/profile
```

**Response:**
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "email_verified_at": "2025-01-01T00:00:00Z",
      "created_at": "2025-01-01T00:00:00Z"
    }
  }
}
```

#### Update Profile

```http
PATCH /settings/profile
```

**Request Body:**
```json
{
  "name": "New Name"
}
```

**Response:**
```json
{
  "data": {
    "message": "Profile updated successfully"
  }
}
```

#### Delete Account

```http
DELETE /settings/account
```

**Response:**
```json
{
  "data": {
    "message": "Account deleted successfully"
  }
}
```

## Error Codes

| Status Code | Description |
|------------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests (Rate Limited) |
| 500 | Internal Server Error |
| 503 | Service Unavailable |

## Example Usage

### cURL

```bash
# List vouchers
curl -X GET "https://your-domain.com/api/v1/vouchers?per_page=10" \
  -H "Authorization: Bearer your_api_token" \
  -H "Accept: application/json"

# Generate vouchers
curl -X POST "https://your-domain.com/api/v1/vouchers" \
  -H "Authorization: Bearer your_api_token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100,
    "count": 5,
    "prefix": "PROMO",
    "ttl_days": 30
  }'

# Redeem voucher (public)
curl -X POST "https://your-domain.com/api/v1/redemption/redeem" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "ABC123",
    "mobile": "09171234567"
  }'
```

### JavaScript (Axios)

```javascript
import axios from 'axios';

// Configure axios
const api = axios.create({
  baseURL: 'https://your-domain.com/api/v1',
  headers: {
    'Authorization': 'Bearer your_api_token',
    'Accept': 'application/json'
  }
});

// List vouchers
const response = await api.get('/vouchers', {
  params: { per_page: 10, status: 'active' }
});

// Generate vouchers
const result = await api.post('/vouchers', {
  amount: 100,
  count: 5,
  prefix: 'PROMO',
  ttl_days: 30
});

// Redeem voucher
const redemption = await api.post('/redemption/redeem', {
  code: 'ABC123',
  mobile: '09171234567'
});
```

### PHP

```php
use Illuminate\Support\Facades\Http;

// List vouchers
$response = Http::withToken('your_api_token')
    ->get('https://your-domain.com/api/v1/vouchers', [
        'per_page' => 10,
        'status' => 'active'
    ]);

// Generate vouchers
$result = Http::withToken('your_api_token')
    ->post('https://your-domain.com/api/v1/vouchers', [
        'amount' => 100,
        'count' => 5,
        'prefix' => 'PROMO',
        'ttl_days' => 30
    ]);

// Redeem voucher
$redemption = Http::post('https://your-domain.com/api/v1/redemption/redeem', [
    'code' => 'ABC123',
    'mobile' => '09171234567'
]);
```

## Webhooks

Webhooks can be configured when generating vouchers to receive notifications on redemption.

**Webhook Request:**
```json
{
  "event": "voucher.redeemed",
  "voucher": {
    "code": "ABC123",
    "amount": 100,
    "currency": "PHP"
  },
  "contact": {
    "mobile": "09171234567",
    "email": "user@example.com",
    "name": "John Doe"
  },
  "redeemed_at": "2025-01-05T10:30:00Z"
}
```

**Expected Response:**
```json
{
  "received": true
}
```

## Support

For API support or questions:
- Email: support@your-domain.com
- Documentation: https://your-domain.com/docs
- Status Page: https://status.your-domain.com

---

Last updated: 2025-01-09
