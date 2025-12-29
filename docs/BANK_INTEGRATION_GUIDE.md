# Bank Integration Guide

**Version**: 1.0.0  
**Last Updated**: 2025-12-29  
**Target Audience**: Bank Integration Teams, Technical Architects

## Table of Contents
1. [Overview](#overview)
2. [Authentication](#authentication)
3. [IP Whitelisting](#ip-whitelisting)
4. [API Endpoints](#api-endpoints)
5. [Rate Limiting](#rate-limiting)
6. [Idempotency](#idempotency)
7. [Error Handling](#error-handling)
8. [Webhooks](#webhooks)
9. [Reconciliation](#reconciliation)
10. [Testing & Sandbox](#testing--sandbox)
11. [Production Checklist](#production-checklist)

## Overview

Redeem-X provides a bank-grade RESTful API for voucher generation, redemption, and disbursement management. The API follows industry best practices with:

- **OpenAPI 3.0 specification** available at `/docs/api`
- **JSON responses** for all endpoints
- **ISO 8601 timestamps** for all date fields
- **Centavo precision** for all monetary amounts
- **Comprehensive audit trail** for all financial transactions

### Base URLs
- **Production**: `https://redeem-x.com/api/v1`
- **Sandbox**: `https://sandbox.redeem-x.com/api/v1`
- **API Documentation**: `https://redeem-x.com/docs/api`
- **OpenAPI Spec**: `https://redeem-x.com/api.json`

### SLA Commitments
- **Uptime**: 99.9% monthly uptime guarantee
- **Response Time**: <200ms for 95% of authenticated requests
- **Support**: 24/7 monitoring with 1-hour critical issue response time

## Authentication

### Laravel Sanctum Token-Based Authentication

All API requests require authentication via **Bearer token** in the `Authorization` header.

#### Obtaining API Tokens

**1. Via Web Dashboard** (Recommended):
```
1. Login to Redeem-X dashboard
2. Navigate to Settings → API Tokens
3. Click "Generate New Token"
4. Copy token immediately (shown only once)
5. Store securely in your environment
```

**2. Via API** (Programmatic):
```bash
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "your-email@bank.com",
  "password": "your-secure-password"
}

Response:
{
  "token": "1|abcdefgh...",
  "expires_at": "2025-12-29T15:00:00Z"
}
```

#### Using Tokens

Include the token in all API requests:

```bash
GET /api/v1/vouchers
Authorization: Bearer 1|abcdefgh...
```

#### Token Expiration
- **Default TTL**: 12 months (configurable per token)
- **Refresh**: Generate new token before expiration
- **Revocation**: Instant via dashboard or API

#### Token Permissions
Tokens can be scoped to specific actions:
- `vouchers:create` - Generate vouchers
- `vouchers:read` - List and view vouchers
- `vouchers:redeem` - Redeem vouchers
- `transactions:read` - View transactions
- `reports:read` - Access reconciliation reports

## IP Whitelisting

### Overview

For banks with **fixed IP addresses**, Redeem-X offers optional IP whitelisting to restrict API access to known IP addresses only. This adds an additional security layer beyond token-based authentication.

**Features**:
- Per-user opt-in (disabled by default)
- Supports individual IPs and CIDR ranges
- Handles proxy/load balancer scenarios
- Returns 403 for non-whitelisted IPs

### Configuration

**Enable IP Whitelisting** via Settings Dashboard:
```
1. Login to Redeem-X dashboard
2. Navigate to Settings → Security → IP Whitelist
3. Toggle "Enable IP Whitelist"
4. Add your IP addresses or CIDR ranges
5. Save changes
```

**Example IP Whitelist**:
```json
[
  "203.0.113.50",           // Single IP address
  "198.51.100.0/24",        // CIDR range (198.51.100.0 - 198.51.100.255)
  "2001:db8::/32"           // IPv6 CIDR range
]
```

### Supported Formats

| Format | Example | Description |
|--------|---------|-------------|
| IPv4 | `203.0.113.50` | Single IPv4 address |
| IPv4 CIDR | `198.51.100.0/24` | IPv4 range (256 addresses) |
| IPv6 | `2001:db8::1` | Single IPv6 address |
| IPv6 CIDR | `2001:db8::/32` | IPv6 range |

### Error Response

When accessing from a non-whitelisted IP:

```bash
GET /api/v1/vouchers
Authorization: Bearer {token}

Response: 403 Forbidden
{
  "error": "ip_not_whitelisted",
  "message": "Your IP address is not authorized to access this resource."
}
```

### Best Practices

1. **Always include your current IP** before enabling whitelist (prevents lockout)
2. **Use CIDR ranges** for offices with dynamic IPs within a range
3. **Document all whitelisted IPs** for security audits
4. **Test thoroughly** before enabling in production
5. **Monitor logs** for blocked IP attempts

### Proxy Considerations

If your requests go through a **reverse proxy or load balancer**, ensure the proxy forwards the original client IP via standard headers:

- `X-Forwarded-For` (most common)
- `X-Real-IP` (nginx)

Redeem-X automatically extracts the original client IP from these headers.

### Security Logging

All blocked IP attempts are logged for security monitoring:

```json
{
  "level": "warning",
  "message": "IP whitelist violation",
  "user_id": 123,
  "client_ip": "10.0.0.1",
  "whitelist": ["203.0.113.50", "198.51.100.0/24"],
  "uri": "/api/v1/vouchers",
  "timestamp": "2025-12-29T15:00:00Z"
}
```

### Disabling IP Whitelist

To disable:

```
1. Login to dashboard
2. Settings → Security → IP Whitelist
3. Toggle off "Enable IP Whitelist"
4. Whitelist configuration is preserved (not deleted)
```

**Note**: Disabling does **not** delete your configured IPs. You can re-enable anytime without reconfiguring.

## API Endpoints

### Core Operations

#### 1. Generate Vouchers
```bash
POST /api/v1/vouchers
Authorization: Bearer {token}
Idempotency-Key: {unique-uuid}
Content-Type: application/json

{
  "count": 10,
  "prefix": "BANK",
  "mask": "####",
  "amount": 500.00,
  "currency": "PHP",
  "ttl_hours": 720,
  "settlement_rail": "INSTAPAY",
  "fee_strategy": "absorb"
}

Response: 201 Created
{
  "data": {
    "vouchers": [
      {
        "code": "BANK-1234",
        "amount": 500.00,
        "currency": "PHP",
        "status": "active",
        "expires_at": "2025-01-28T15:00:00Z"
      }
    ]
  }
}
```

#### 2. Redeem Voucher
```bash
POST /api/v1/vouchers/{code}/redeem
Authorization: Bearer {token}
Idempotency-Key: {unique-uuid}
Content-Type: application/json

{
  "mobile": "09171234567",
  "email": "customer@example.com",
  "inputs": {
    "full_name": "Juan Dela Cruz",
    "location": {
      "latitude": 14.5995,
      "longitude": 120.9842
    }
  }
}

Response: 200 OK
{
  "success": true,
  "message": "Voucher redeemed successfully",
  "data": {
    "contact": {...},
    "disbursement": {
      "reference_id": "DISB-ABC123",
      "amount": 500.00,
      "status": "pending",
      "gateway_transaction_id": "GW-789"
    }
  }
}
```

#### 3. List Vouchers
```bash
GET /api/v1/vouchers?status=active&per_page=50
Authorization: Bearer {token}

Response: 200 OK
{
  "data": [...],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 1500,
      "last_page": 30
    }
  }
}
```

#### 4. Transaction History
```bash
GET /api/v1/transactions?date_from=2025-01-01&date_to=2025-01-31
Authorization: Bearer {token}

Response: 200 OK
{
  "data": {
    "disbursements": [...],
    "summary": {
      "total_count": 150,
      "total_amount": 75000.00,
      "success_count": 145
    }
  }
}
```

### Reconciliation Reports

#### Daily Settlement Report
```bash
GET /api/v1/reports/disbursements?from_date=2025-01-28&to_date=2025-01-28
Authorization: Bearer {token}
Accept: application/json  # or text/csv for Excel export

Response: 200 OK
{
  "data": {
    "disbursements": [...],
    "summary": {
      "total_count": 150,
      "success_count": 145,
      "failed_count": 5,
      "total_amount": 75000.00,
      "success_amount": 72500.00
    }
  }
}
```

#### Failed Disbursements
```bash
GET /api/v1/reports/disbursements/failed?from_date=2025-01-28&to_date=2025-01-28
Authorization: Bearer {token}

Response: 200 OK
{
  "data": {
    "failed_disbursements": [...],
    "error_breakdown": {
      "network_timeout": 2,
      "insufficient_funds": 1,
      "gateway_error": 2
    }
  }
}
```

## Rate Limiting

Rate limits protect API stability and ensure fair usage.

### Advanced Rate Limiting (Token Bucket)

Redeem-X uses a **Token Bucket algorithm** for advanced rate limiting with burst allowance support. This provides better UX than simple request counting by allowing short bursts of traffic while maintaining average rate control.

#### Service Tiers

| Tier | Requests/Min | Burst Allowance | Typical Use Case |
|------|-------------|----------------|------------------|
| **Basic** | 60 | 10 tokens | Small integrations, testing |
| **Premium** | 300 | 50 tokens | Medium-volume production use |
| **Enterprise** | 1000 | 200 tokens | High-volume bank integrations |

**Tier Assignment**: Contact your account manager to upgrade tiers based on your integration needs.

#### How Token Bucket Works

1. **Initial State**: Your bucket starts full with your tier's burst allowance (e.g., 10 tokens for Basic)
2. **Request Consumption**: Each API request consumes 1 token
3. **Token Refill**: Tokens refill continuously at your tier's rate (e.g., Basic = 1 token/second)
4. **Burst Support**: You can make burst_allowance requests instantly, then sustain requests_per_minute/60 thereafter
5. **Rate Limiting**: When bucket is empty (0 tokens), requests return 429 until tokens refill

**Example (Basic Tier)**:
```
1. Start with 10 tokens (burst allowance)
2. Make 10 requests instantly → All succeed (0 tokens left)
3. 11th request → 429 Too Many Requests
4. Wait 1 second → 1 token refills
5. 12th request → Succeeds (0 tokens left again)
6. Continue at 1 request/second sustainably
```

#### Rate Limit Headers

All API responses include comprehensive rate limit information:

```http
X-RateLimit-Limit: 60           # Requests per minute for your tier
X-RateLimit-Remaining: 7         # Tokens remaining in bucket
X-RateLimit-Reset: 1767020008    # Unix timestamp when bucket fully refills
X-RateLimit-Tier: basic          # Your assigned tier
```

**Using Headers Effectively**:
```javascript
// Check headers before making next request
if (response.headers['X-RateLimit-Remaining'] < 5) {
  const resetTime = response.headers['X-RateLimit-Reset'];
  const waitSeconds = resetTime - Math.floor(Date.now() / 1000);
  console.log(`Low on tokens, ${waitSeconds}s until refill`);
}
```

#### Exceeding Rate Limits

**429 Too Many Requests** response when bucket is empty:
```json
{
  "error": "rate_limit_exceeded",
  "message": "Too many requests. Please retry after 5 seconds.",
  "retry_after": 5
}
```

**Response headers on 429**:
```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1767020013
X-RateLimit-Tier: basic

{
  "error": "rate_limit_exceeded",
  "message": "Too many requests. Please retry after 5 seconds.",
  "retry_after": 5
}
```

#### Best Practices

**1. Monitor Token Levels**:
```python
if remaining_tokens < 10:
    # Slow down request rate
    time.sleep(0.5)
```

**2. Implement Exponential Backoff**:
```python
retries = 0
while retries < 5:
    response = make_api_request()
    if response.status_code == 429:
        retry_after = response.json()['retry_after']
        time.sleep(min(retry_after * (2 ** retries), 60))  # Cap at 60s
        retries += 1
    else:
        break
```

**3. Batch Operations**:
```bash
# Instead of 10 separate requests
POST /api/v1/vouchers { "count": 1 }  # x10

# Use batch generation
POST /api/v1/vouchers { "count": 10 }  # 1 request
```

**4. Cache Non-Time-Sensitive Data**:
```javascript
// Cache voucher list for 5 minutes
const cachedVouchers = cache.get('vouchers');
if (!cachedVouchers) {
  const response = await api.get('/vouchers');
  cache.set('vouchers', response.data, 300); // 5 min TTL
}
```

**5. Respect Reset Times**:
```bash
# When rate limited, wait until reset_at instead of retrying immediately
reset_at=$(curl -s -H "Authorization: Bearer $TOKEN" \
  https://api.redeem-x.com/health | \
  jq -r '.headers["X-RateLimit-Reset"]')

wait_seconds=$((reset_at - $(date +%s)))
sleep $wait_seconds
```

#### Configuration

**Disable rate limiting** (for testing only - not recommended for production):
```bash
# .env
RATE_LIMITING_ENABLED=false
```

**Per-endpoint overrides** are available for expensive operations - contact support if needed.

#### Upgrading Tiers

To request a tier upgrade:

1. Email support@redeem-x.com with:
   - Current tier and limits
   - Expected request volume (avg/peak)
   - Use case description
   - Business justification

2. Review period: 2-3 business days

3. Tier changes take effect immediately upon approval

#### Monitoring

**Track your rate limit usage**:
```bash
GET /api/v1/auth/rate-limit-status
Authorization: Bearer {token}

Response:
{
  "tier": "premium",
  "limits": {
    "requests_per_minute": 300,
    "burst_allowance": 50
  },
  "current": {
    "tokens_remaining": 42,
    "reset_at": 1767020100
  },
  "usage_30d": {
    "total_requests": 450000,
    "rate_limited_requests": 12,
    "rate_limited_percentage": 0.003
  }
}
```

**Alerting**: Set up monitoring to alert when `rate_limited_percentage` exceeds acceptable threshold (e.g., >1%).

## Idempotency

### Why Idempotency Matters

Financial operations must be **safe to retry**. Network issues or timeouts should not result in duplicate vouchers or double disbursements.

### Idempotency-Key Header

**Required for**:
- `POST /api/v1/vouchers` (voucher generation)
- `POST /api/v1/vouchers/{code}/redeem` (redemption)
- `POST /api/v1/topup` (wallet top-up)

**Format**: UUID v4 (recommended) or any unique string (max 255 chars)

**Example**:
```bash
POST /api/v1/vouchers
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

### Behavior

| Scenario | Response |
|----------|----------|
| First request with key | Process normally, return 201 |
| Duplicate request (within 24h) | Return cached response (201) |
| Duplicate request (after 24h) | Treat as new request |
| Missing key on protected endpoint | Return 400 Bad Request |

### Implementation Example (PHP)
```php
use Illuminate\Support\Str;

$idempotencyKey = Str::uuid()->toString();

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Idempotency-Key' => $idempotencyKey,
])->post('https://api.redeem-x.com/api/v1/vouchers', $data);
```

## Error Handling

### Standard Error Response Format

All errors follow a consistent structure:

```json
{
  "success": false,
  "error": "error_code",
  "message": "Human-readable description",
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | Success | Redemption successful |
| 201 | Created | Voucher generated |
| 400 | Bad Request | Missing idempotency key |
| 401 | Unauthorized | Invalid/missing token |
| 403 | Forbidden | Token lacks required scope |
| 404 | Not Found | Voucher code doesn't exist |
| 422 | Validation Error | Invalid amount format |
| 429 | Rate Limited | Too many requests |
| 500 | Server Error | Internal error (rare) |
| 503 | Service Unavailable | Maintenance mode |

### Common Error Codes

- `invalid_credentials` - Authentication failed
- `rate_limit_exceeded` - Too many requests
- `voucher_not_found` - Voucher doesn't exist
- `voucher_already_redeemed` - Cannot redeem twice
- `voucher_expired` - Past expiration date
- `insufficient_balance` - Wallet balance too low
- `validation_failed` - Request validation errors
- `gateway_error` - Payment gateway issue
- `network_timeout` - Gateway timeout

## Webhooks

### Webhook Events

Redeem-X can notify your system of important events:

| Event | Trigger |
|-------|---------|
| `voucher.redeemed` | Voucher successfully redeemed |
| `disbursement.completed` | Funds disbursed successfully |
| `disbursement.failed` | Disbursement failed |
| `wallet.topped_up` | Wallet credited |

### Webhook Configuration

Configure webhook URLs in dashboard:
```
Settings → Webhooks → Add Endpoint
```

### Webhook Payload

```json
{
  "id": "evt_abc123",
  "event": "disbursement.completed",
  "timestamp": "2025-12-29T15:00:00Z",
  "data": {
    "reference_id": "DISB-ABC123",
    "voucher_code": "BANK-1234",
    "amount": 500.00,
    "currency": "PHP",
    "mobile": "09171234567",
    "gateway_transaction_id": "GW-789",
    "status": "success"
  }
}
```

### Webhook Security

**HMAC-SHA256 Signature Verification**:

Each webhook includes a signature in the `X-Signature` header:

```
X-Signature: sha256=abc123...
```

**Verification** (PHP example):
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'];
$secret = env('WEBHOOK_SECRET');

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}
```

**Best Practices**:
- Always verify signatures before processing
- Respond with 200 OK immediately (process async)
- Implement idempotency on your side (use `event.id`)
- Retry failed webhooks with exponential backoff

## Reconciliation

### Daily Reconciliation Flow

Banks should reconcile daily using these endpoints:

**1. Morning Settlement Report** (9 AM)
```bash
GET /api/v1/reports/settlements?from_date=2025-01-28&to_date=2025-01-28
Accept: text/csv

# Downloads: settlements_20250128_20250128.csv
```

**2. Failed Transactions Review** (Continuous)
```bash
GET /api/v1/reports/disbursements/failed?from_date=2025-01-28&to_date=2025-01-28

# Investigate and retry/refund as needed
```

**3. End-of-Day Summary** (6 PM)
```bash
GET /api/v1/reports/disbursements/summary?from_date=2025-01-28&to_date=2025-01-28

# Verify totals match bank settlement
```

### Reconciliation Fields

All disbursement records include:
- `reference_id` - Unique Redeem-X identifier
- `gateway_transaction_id` - Bank's transaction ID
- `voucher_code` - Original voucher
- `amount` & `currency` - Transaction amount
- `mobile` - Recipient mobile number
- `bank_code` - BIC/SWIFT code (e.g., GXCHPHM2XXX)
- `settlement_rail` - INSTAPAY or PESONET
- `attempted_at` - When disbursement initiated
- `completed_at` - When gateway confirmed
- `status` - success/failed/pending

## Testing & Sandbox

### Sandbox Environment

**URL**: `https://sandbox.redeem-x.com/api/v1`

**Features**:
- Identical API to production
- No real money movement
- Separate database from production
- Test payment gateway (auto-approves after 5 seconds)

### Sandbox Test Cards

Use these test accounts for GCash/PayMaya:

| Mobile | Outcome |
|--------|---------|
| 09171111111 | Always succeeds |
| 09172222222 | Always fails (insufficient funds) |
| 09173333333 | Timeout (30s delay) |

### Integration Testing Checklist

- [ ] Generate vouchers with idempotency
- [ ] Redeem voucher successfully
- [ ] Handle voucher already redeemed (422)
- [ ] Handle expired voucher (422)
- [ ] Test rate limiting (429)
- [ ] Verify webhook signature
- [ ] Download CSV reconciliation report
- [ ] Test retry logic for failed disbursements
- [ ] Validate token refresh before expiration

## Production Checklist

### Security
- [ ] Store API tokens in secure vault (not code)
- [ ] Implement webhook signature verification
- [ ] Use HTTPS for all requests
- [ ] Rotate tokens every 90 days
- [ ] Restrict token scopes to minimum required
- [ ] IP whitelist (optional, contact support)

### Reliability
- [ ] Implement exponential backoff for retries
- [ ] Generate unique idempotency keys per request
- [ ] Monitor rate limit headers
- [ ] Set request timeouts (30s recommended)
- [ ] Implement circuit breaker pattern
- [ ] Log all API interactions for debugging

### Monitoring
- [ ] Alert on 4xx/5xx error rates
- [ ] Track P95 response times
- [ ] Monitor failed disbursements
- [ ] Daily reconciliation automation
- [ ] Webhook delivery monitoring

### Support
- [ ] Integration team contacts saved
- [ ] Escalation path documented
- [ ] 24/7 support phone number tested
- [ ] Incident response plan in place

## Support Contacts

**Integration Support**:
- Email: integrations@redeem-x.com
- Phone: +63 2 1234 5678
- Hours: 24/7 for critical issues

**Technical Documentation**:
- OpenAPI Spec: https://redeem-x.com/api.json
- API Docs: https://redeem-x.com/docs/api
- Status Page: https://status.redeem-x.com

**Slack Integration Channel** (Available for Tier 1 partners):
- Request access via integrations@redeem-x.com
