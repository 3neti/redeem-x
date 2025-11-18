# Voucher API Endpoints - Phase 2

**Version:** v1  
**Base URL:** `/api/v1`  
**Authentication:** Laravel Sanctum (Bearer Token)  
**Rate Limiting:** 60 requests per minute (authenticated)

## Table of Contents
1. [Authentication](#authentication)
2. [External Metadata Management](#external-metadata-management)
3. [Timing Tracking](#timing-tracking)
4. [Voucher Queries](#voucher-queries)
5. [Bulk Operations](#bulk-operations)
6. [Error Responses](#error-responses)

---

## Authentication

All endpoints require authentication via Laravel Sanctum.

### Headers Required
```http
Authorization: Bearer {your-api-token}
Content-Type: application/json
Accept: application/json
```

### Creating API Tokens
```php
// Generate a token for a user
$token = $user->createToken('api-token-name')->plainTextToken;
```

---

## External Metadata Management

### Set External Metadata

Set or update external metadata for a voucher (e.g., for QuestPay integration).

**Endpoint:** `POST /api/v1/vouchers/{code}/external`

**Request Body:**
```json
{
  "external_id": "quest-123",
  "external_type": "questpay",
  "reference_id": "ref-456",
  "user_id": "player-789",
  "custom": {
    "level": 10,
    "zone": "north",
    "quest_name": "Dragon Slayer"
  }
}
```

**Response:** `200 OK`
```json
{
  "data": {
    "message": "External metadata updated successfully",
    "external_metadata": {
      "external_id": "quest-123",
      "external_type": "questpay",
      "reference_id": "ref-456",
      "user_id": "player-789",
      "custom": {
        "level": 10,
        "zone": "north",
        "quest_name": "Dragon Slayer"
      }
    }
  },
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

**Validation Rules:**
- `external_id`: optional, string, max 255 chars
- `external_type`: optional, string, max 255 chars
- `reference_id`: optional, string, max 255 chars
- `user_id`: optional, string, max 255 chars
- `custom`: optional, array of any structure

**Use Cases:**
- QuestPay integration: Link vouchers to game quests
- Rewards programs: Track reward campaigns
- Analytics: Store campaign or promotion IDs
- External system correlation: Link to external transaction IDs

---

## Timing Tracking

Track user interaction timing for analytics and fraud detection.

### Track Click Event

Record when a user first clicks a voucher link.

**Endpoint:** `POST /api/v1/vouchers/{code}/timing/click`

**Request Body:** None

**Response:** `200 OK`
```json
{
  "data": {
    "message": "Click tracked successfully",
    "timing": {
      "clicked_at": "2025-01-17T05:00:00Z",
      "started_at": null,
      "submitted_at": null,
      "duration_seconds": null
    }
  },
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

**Note:** This endpoint is idempotent - subsequent calls won't update the timestamp.

---

### Track Redemption Start

Record when a user begins the redemption process.

**Endpoint:** `POST /api/v1/vouchers/{code}/timing/start`

**Request Body:** None

**Response:** `200 OK`
```json
{
  "data": {
    "message": "Redemption start tracked successfully",
    "timing": {
      "clicked_at": "2025-01-17T05:00:00Z",
      "started_at": "2025-01-17T05:01:30Z",
      "submitted_at": null,
      "duration_seconds": null
    }
  },
  "meta": {
    "timestamp": "2025-01-17T05:01:30Z",
    "version": "v1"
  }
}
```

---

### Track Redemption Submit

Record when a user completes the redemption form submission.

**Endpoint:** `POST /api/v1/vouchers/{code}/timing/submit`

**Request Body:** None

**Response:** `200 OK`
```json
{
  "data": {
    "message": "Redemption submit tracked successfully",
    "timing": {
      "clicked_at": "2025-01-17T05:00:00Z",
      "started_at": "2025-01-17T05:01:30Z",
      "submitted_at": "2025-01-17T05:03:45Z",
      "duration_seconds": 135
    },
    "duration_seconds": 135
  },
  "meta": {
    "timestamp": "2025-01-17T05:03:45Z",
    "version": "v1"
  }
}
```

**Use Cases:**
- Fraud detection: Flag unusually fast redemptions
- UX analytics: Understand user behavior patterns
- Time-based validation: Enforce minimum/maximum redemption durations

---

## Voucher Queries

### Query Vouchers

Search and filter vouchers with advanced criteria.

**Endpoint:** `GET /api/v1/vouchers/query`

**Query Parameters:**
```
external_type     (string)   Filter by external system type
external_id       (string)   Filter by external ID
reference_id      (string)   Filter by reference ID
user_id           (string)   Filter by external user ID
status            (string)   active | redeemed | expired
validation_status (string)   passed | failed | blocked
order_by          (string)   created_at | redeemed_at | expires_at | code
order_direction   (string)   asc | desc (default: desc)
per_page          (integer)  Results per page (1-100, default: 15)
```

**Example Request:**
```http
GET /api/v1/vouchers/query?external_type=questpay&user_id=player-123&status=active&per_page=20
```

**Response:** `200 OK`
```json
{
  "data": {
    "vouchers": [
      {
        "id": 1,
        "code": "QUEST-ABC123",
        "status": "active",
        "amount": 100,
        "currency": "PHP",
        "created_at": "2025-01-17T05:00:00Z",
        "expires_at": "2025-01-18T05:00:00Z",
        "redeemed_at": null
      }
    ],
    "pagination": {
      "total": 15,
      "per_page": 20,
      "current_page": 1,
      "last_page": 1,
      "from": 1,
      "to": 15
    }
  },
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

**Use Cases:**
- Dashboard: Show vouchers by external campaign
- Player management: Find all vouchers for a specific player
- Analytics: Query vouchers by validation status

---

### Show Voucher with Extended Data

Get detailed voucher information including all Phase 1/2 extensions.

**Endpoint:** `GET /api/v1/vouchers/{code}`

**Response:** `200 OK`
```json
{
  "data": {
    "voucher": {
      "id": 1,
      "code": "QUEST-ABC123",
      "status": "active",
      "amount": 100,
      "currency": "PHP",
      "created_at": "2025-01-17T05:00:00Z",
      "expires_at": "2025-01-18T05:00:00Z",
      "redeemed_at": null,
      "instructions": { }
    },
    "redemption_count": 0,
    "external_metadata": {
      "external_id": "quest-123",
      "external_type": "questpay",
      "user_id": "player-789",
      "custom": {
        "level": 10
      }
    },
    "timing": {
      "clicked_at": "2025-01-17T05:00:00Z",
      "started_at": "2025-01-17T05:01:30Z",
      "submitted_at": null,
      "duration_seconds": null
    },
    "validation_results": {
      "location": null,
      "time": null,
      "passed": null,
      "failed": null,
      "blocked": null
    }
  },
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

---

## Bulk Operations

### Bulk Create Vouchers

Generate multiple vouchers with external metadata in a single request.

**Endpoint:** `POST /api/v1/vouchers/bulk-create`

**Request Body:**
```json
{
  "campaign_id": 1,
  "vouchers": [
    {
      "mobile": "09171234567",
      "external_metadata": {
        "external_id": "quest-1",
        "external_type": "questpay",
        "user_id": "player-1",
        "custom": {
          "level": 5,
          "quest_name": "Tutorial Quest"
        }
      }
    },
    {
      "mobile": "09179876543",
      "external_metadata": {
        "external_id": "quest-2",
        "external_type": "questpay",
        "user_id": "player-2",
        "custom": {
          "level": 10,
          "quest_name": "Dragon Slayer"
        }
      }
    }
  ]
}
```

**Response:** `201 Created`
```json
{
  "data": {
    "count": 2,
    "vouchers": [
      {
        "id": 1,
        "code": "BULK-ABC123",
        "status": "active",
        "amount": 100,
        "currency": "PHP"
      },
      {
        "id": 2,
        "code": "BULK-DEF456",
        "status": "active",
        "amount": 100,
        "currency": "PHP"
      }
    ],
    "total_amount": 200,
    "currency": "PHP"
  },
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

**Validation Rules:**
- `campaign_id`: required, must exist and belong to authenticated user
- `vouchers`: required, array, min 1, max 100 items
- `vouchers.*.mobile`: optional, valid Philippine mobile number
- `vouchers.*.external_metadata`: optional, object with same rules as `POST /external`

**Limits:**
- Maximum 100 vouchers per request
- Requires sufficient wallet balance (amount × count)
- Rate limited to 60 requests/minute

**Use Cases:**
- QuestPay: Generate reward vouchers for completed quests
- Bulk campaigns: Create vouchers for multiple recipients
- Event distribution: Mass-generate codes for promotions

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated.",
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

### 403 Forbidden
```json
{
  "message": "You do not have permission to modify this voucher.",
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

### 404 Not Found
```json
{
  "message": "Resource not found",
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "external_id": [
      "The external id must not be greater than 255 characters."
    ],
    "vouchers": [
      "You cannot create more than 100 vouchers at once."
    ]
  },
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

### 429 Too Many Requests
```json
{
  "message": "Too Many Attempts.",
  "meta": {
    "timestamp": "2025-01-17T05:00:00Z",
    "version": "v1"
  }
}
```

---

## Integration Examples

### QuestPay Flow

1. **User completes quest in game**
2. **Game backend calls bulk-create**
   ```bash
   POST /api/v1/vouchers/bulk-create
   {
     "campaign_id": 1,
     "vouchers": [{
       "mobile": "09171234567",
       "external_metadata": {
         "external_id": "quest-dragon-slayer",
         "external_type": "questpay",
         "user_id": "player-12345",
         "custom": {"level": 10, "reward_tier": "gold"}
       }
     }]
   }
   ```

3. **Game sends voucher code to player**

4. **Player clicks voucher link**
   ```bash
   POST /api/v1/vouchers/{code}/timing/click
   ```

5. **Player starts redemption**
   ```bash
   POST /api/v1/vouchers/{code}/timing/start
   ```

6. **Player submits form**
   ```bash
   POST /api/v1/vouchers/{code}/timing/submit
   ```

7. **Game queries player vouchers**
   ```bash
   GET /api/v1/vouchers/query?external_type=questpay&user_id=player-12345
   ```

---

## Webhook Integration

Voucher redemption triggers webhook notifications including all Phase 1/2 data:

```json
{
  "event": "voucher.redeemed",
  "voucher": { },
  "redeemer": { },
  "external": {
    "external_id": "quest-123",
    "external_type": "questpay",
    "user_id": "player-789",
    "custom": {"level": 10}
  },
  "timing": {
    "clicked_at": "2025-01-17T05:00:00Z",
    "started_at": "2025-01-17T05:01:30Z",
    "submitted_at": "2025-01-17T05:03:45Z",
    "duration_seconds": 135
  },
  "validation": {
    "location": {"passed": true, "distance_km": 0.5},
    "time": {"passed": true, "within_window": true}
  }
}
```

See `docs/PHASE_1_COMPLETE.md` for full webhook payload documentation.

---

## Testing

Run API tests:
```bash
php artisan test tests/Feature/Api/VoucherApiExtensionsTest.php
```

**Test Coverage:**
- ✅ 17 passing tests
- ✅ External metadata CRUD
- ✅ Timing tracking (click, start, submit)
- ✅ Authorization checks
- ✅ Validation rules
- ✅ Query filtering
- ✅ Bulk creation
- ✅ Pagination

---

## Rate Limits

- **Authenticated endpoints:** 60 requests/minute
- **Bulk operations:** Same limit, plan accordingly
- **Query endpoints:** Cached for 30 seconds

## Support

For issues or questions:
- GitHub: `lbhurtado/redeem-x`
- Docs: `/docs/VOUCHER_API_EXTENSIONS.md`
