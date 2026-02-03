# QuestPay Voucher System Gaps

**Context**: QuestPay will be a separate application that uses redeem-x as the voucher generation/redemption engine via API integration.

This document identifies what's **missing** in the current redeem-x voucher system that QuestPay requires.

---

## ✅ What redeem-x Already Provides

| Feature | Status | Notes |
|---------|--------|-------|
| Voucher generation | ✅ | Generate codes with QR, expiration |
| Voucher redemption | ✅ | Web-based redemption flow |
| Campaigns (templates) | ✅ | Reusable voucher configurations |
| Splash pages | ✅ | Custom messaging on redemption |
| Landing pages | ✅ | Redirect after redemption |
| Input collection | ✅ | Photos, GPS, signatures, text, KYC |
| Validation rules | ✅ | Per-field validation |
| SMS/Email delivery | ✅ | EngageSpark, SMTP |
| Webhooks | ✅ | Post-redemption callbacks |
| Disbursements | ✅ | NetBank/Omnipay payment |

---

## 🔨 Gaps: What QuestPay Needs

### 1. **Progressive Voucher Chaining** 🔴 Critical
**Current State**: Landing pages can display static content, but cannot conditionally reveal the next voucher code based on interactions.

**QuestPay Needs**:
- Landing page that displays interactive elements (trivia, puzzles, captcha)
- Logic to show "Next Voucher Code" only after:
  - User completes interaction (answers trivia correctly)
  - External verification passes (webhook from QuestPay confirms challenge complete)
  - Time condition met (countdown timer expires)

**Implementation Options**:
1. **Option A**: Add `progressive_disclosure` config to landing pages
   - Landing page template supports "locked" sections
   - Unlock via webhook trigger from QuestPay
   - Display next voucher code when unlocked

2. **Option B**: QuestPay handles landing pages entirely
   - redeem-x only provides redemption + webhook
   - QuestPay receives webhook → generates next voucher via API → displays on own landing page

**Recommendation**: Option B (cleaner separation)

---

### 2. **Voucher Metadata for External Systems** 🔴 Critical
**Current State**: Vouchers have limited metadata fields.

**QuestPay Needs**:
- Associate voucher with external IDs:
  - `game_id` - Which game this voucher belongs to
  - `challenge_id` - Which challenge this voucher represents
  - `contestant_id` - Which contestant received this voucher
  - `sequence_number` - Order in challenge sequence (1, 2, 3...)
  - `challenge_type` - Type of challenge (location, purchase, task)

**Implementation**:
- Extend `vouchers` table with `external_metadata` JSON field
- API accepts metadata on voucher creation
- Webhook includes full metadata in payload

**Example**:
```php
POST /api/vouchers/create
{
  "amount": 100,
  "campaign_id": 123,
  "mobile": "09173011987",
  "external_metadata": {
    "game_id": "MMS-001",
    "challenge_id": "CH-005",
    "contestant_id": "CONT-042",
    "sequence": 3,
    "challenge_type": "location"
  }
}
```

---

### 3. **Location Validation on Redemption** 🟡 High
**Current State**: GPS location is collected but not validated against target coordinates.

**QuestPay Needs**:
- Define target location (lat/lng) and radius when creating voucher
- Auto-validate contestant's GPS is within radius during redemption
- Block redemption if location check fails (or allow with warning)

**Implementation**:
- Add `location_validation` to campaign/voucher config:
  ```json
  {
    "required": true,
    "target_lat": 14.5547,
    "target_lng": 121.0244,
    "radius_meters": 50,
    "on_failure": "block" // or "warn"
  }
  ```
- Redemption wizard validates before allowing submission
- Return validation result in webhook

---

### 4. **Time-Based Voucher Constraints** 🟡 High
**Current State**: Vouchers have `valid_until` for expiration, but no time-of-day or duration tracking.

**QuestPay Needs**:
- **Time Window**: Voucher only redeemable during specific hours
  - Example: "Only redeemable between 9 AM - 5 PM"
- **Time Limit from Issue**: Challenge must be completed within X minutes
  - Example: "Valid for 30 minutes from SMS delivery"
- **Redemption Duration Tracking**: How long did redemption take?
  - Start: When contestant clicked voucher link
  - End: When redemption submitted
  - Duration needed for speed bonus calculation

**Implementation**:
- Add to voucher config:
  ```json
  {
    "time_window": {
      "start_time": "09:00",
      "end_time": "17:00",
      "timezone": "Asia/Manila"
    },
    "time_limit_minutes": 30,
    "track_duration": true
  }
  ```
- Webhook includes `redemption_duration_seconds`

---

### 5. **Bulk Voucher Generation API** 🟡 High
**Current State**: Voucher generation is one-at-a-time via UI or single API call.

**QuestPay Needs**:
- Generate multiple vouchers at once (batch API)
- Example: Generate 10 vouchers for 10 contestants at game start
- Each with unique metadata but same campaign template

**Implementation**:
```php
POST /api/vouchers/bulk-create
{
  "campaign_id": 123,
  "vouchers": [
    {
      "mobile": "09171234567",
      "external_metadata": {"contestant_id": "CONT-001"}
    },
    {
      "mobile": "09177654321",
      "external_metadata": {"contestant_id": "CONT-002"}
    }
    // ... up to 100 per request
  ]
}

Response:
{
  "created": 10,
  "vouchers": [
    {"code": "QP-ABC123", "contestant_id": "CONT-001"},
    {"code": "QP-DEF456", "contestant_id": "CONT-002"}
  ]
}
```

---

### 6. **Enhanced Webhook Payload** 🟡 High
**Current State**: Webhooks provide basic redemption data.

**QuestPay Needs**:
- All collected input data in webhook
- GPS coordinates (not just stored, but sent)
- Photo/video URLs (S3 paths or signed URLs)
- Redemption timing data:
  - `clicked_at` - When voucher link was clicked
  - `started_at` - When redemption wizard started
  - `submitted_at` - When form submitted
  - `duration_seconds` - Time to complete

**Implementation**:
Enhance webhook payload:
```json
{
  "event": "voucher.redeemed",
  "voucher_code": "QP-ABC123",
  "external_metadata": {
    "game_id": "MMS-001",
    "challenge_id": "CH-005",
    "contestant_id": "CONT-042"
  },
  "collected_data": {
    "location": {
      "lat": 14.5547,
      "lng": 121.0244,
      "validated": true,
      "distance_meters": 12
    },
    "photos": [
      "https://s3.../receipt.jpg"
    ],
    "text_responses": {
      "proof_text": "I bought lumpia at the market"
    },
    "signature_url": "https://s3.../signature.png"
  },
  "timing": {
    "clicked_at": "2025-01-15T10:00:00Z",
    "started_at": "2025-01-15T10:00:05Z",
    "submitted_at": "2025-01-15T10:02:30Z",
    "duration_seconds": 145
  }
}
```

---

### 7. **Redemption Status Callbacks** 🟢 Medium
**Current State**: Webhook fires once on redemption success.

**QuestPay Needs**:
- Status updates at key stages:
  - `voucher.clicked` - User clicked link
  - `voucher.wizard_started` - Redemption form opened
  - `voucher.submitted` - Form submitted (before verification)
  - `voucher.verified` - Verification passed
  - `voucher.rejected` - Verification failed

**Implementation**:
- Multiple webhook events with status field
- QuestPay subscribes to specific events via config

---

### 8. **Custom Landing Page Templates** 🟢 Medium
**Current State**: Landing pages use fixed templates.

**QuestPay Needs**:
- Ability to define custom landing page HTML/Vue components
- Pass dynamic data to landing page (challenge info, sponsor content)
- Embed interactive elements (trivia questions, puzzles)

**Implementation Options**:
1. **Option A**: redeem-x supports custom Vue component injection
2. **Option B**: Landing page redirect to QuestPay-hosted page with redemption data as query params

**Recommendation**: Option B (simpler, more flexible for QuestPay)

---

### 9. **Voucher Query/Status API** 🟢 Medium
**Current State**: No API to query voucher status from external systems.

**QuestPay Needs**:
- Check voucher status programmatically:
  ```php
  GET /api/vouchers/{code}/status
  
  Response:
  {
    "code": "QP-ABC123",
    "status": "redeemed",
    "redeemed_at": "2025-01-15T10:02:30Z",
    "external_metadata": {...},
    "collected_data": {...}
  }
  ```

**Use Case**: QuestPay needs to verify redemption status without waiting for webhook (e.g., manual check, fallback if webhook fails)

---

### 10. **Manual Verification Queue Webhook** 🟢 Medium
**Current State**: Admin can manually verify redemptions via UI, but no webhook for this event.

**QuestPay Needs**:
- Webhook when admin manually approves/rejects a redemption
- Event: `voucher.manual_verification`
- Payload includes admin decision and notes

**Implementation**:
```json
{
  "event": "voucher.manual_verification",
  "voucher_code": "QP-ABC123",
  "status": "approved", // or "rejected"
  "admin_user_id": 5,
  "admin_notes": "Photo unclear but contestant confirmed via SMS",
  "verified_at": "2025-01-15T10:05:00Z"
}
```

---

## Gap Summary by Priority

### 🔴 Critical (Must Have for Pilot)
1. **Voucher metadata** - External IDs for game/challenge/contestant
2. **Enhanced webhook payload** - Include all data, timing, GPS

### 🟡 High (Strongly Recommended)
3. **Location validation** - Auto-check GPS on redemption
4. **Time constraints** - Time windows and duration tracking
5. **Bulk generation API** - Create multiple vouchers at once

### 🟢 Medium (Nice to Have)
6. **Progressive chaining** - Conditional next code reveal (or handle in QuestPay)
7. **Status callbacks** - Multiple webhook events for stages
8. **Custom landing pages** - Template injection or redirect
9. **Voucher query API** - Check status programmatically
10. **Manual verification webhook** - Admin approval notifications

---

## Recommended Implementation Approach

### Phase 1: Core API Extensions (Week 1-2)
- Add `external_metadata` JSON field to `vouchers` table
- Enhance webhook payload with all collected data + timing
- Implement bulk voucher generation API
- Add voucher status query API

### Phase 2: Validation Enhancements (Week 3)
- Location validation on redemption
- Time window and duration tracking
- Return validation results in webhook

### Phase 3: Advanced Features (Week 4)
- Multiple webhook events (status callbacks)
- Manual verification webhooks
- Custom landing page redirect logic (if Option B chosen)

---

## Integration Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  QuestPay Application                    │
│  (Separate Laravel App - Game Engine)                   │
├─────────────────────────────────────────────────────────┤
│ • Game/Episode/Challenge Management                      │
│ • Contestant Registration & Tracking                     │
│ • Leaderboard & Scoring                                  │
│ • Real-Time Updates (Reverb)                            │
│ • Production Dashboard                                   │
│ • Contestant PWA                                         │
│ • Public Viewer Experience                               │
└──────────────┬──────────────────────────────────────────┘
               │
               │ REST API Integration
               │
    ┌──────────▼──────────┐
    │  Creates Vouchers   │ POST /api/vouchers/create
    │  (with metadata)    │ POST /api/vouchers/bulk-create
    └──────────┬──────────┘
               │
               │
┌──────────────▼──────────────────────────────────────────┐
│              redeem-x Application                        │
│  (Voucher Generation & Redemption Engine)               │
├─────────────────────────────────────────────────────────┤
│ • Voucher CRUD + Campaign Templates                      │
│ • Redemption Flow (Splash → Wizard → Landing)          │
│ • Input Collection (Photos, GPS, Signature, Text)      │
│ • Validation Rules                                       │
│ • SMS/Email Delivery (EngageSpark)                      │
│ • Disbursement (NetBank/Omnipay)                       │
└──────────────┬──────────────────────────────────────────┘
               │
               │ Webhooks
               │
    ┌──────────▼──────────┐
    │  QuestPay Webhook   │ voucher.redeemed
    │  Endpoint           │ voucher.verified
    │  Receives:          │ voucher.manual_verification
    │  • Redemption data  │
    │  • GPS coordinates  │
    │  • Photos/media     │
    │  • Timing data      │
    └─────────────────────┘
```

### Data Flow Example

1. **Game Start**: QuestPay creates 10 vouchers via bulk API
   ```
   QuestPay → POST /api/vouchers/bulk-create → redeem-x
   ```

2. **Voucher Delivery**: redeem-x sends SMS with codes to contestants

3. **Redemption**: Contestant clicks link, fills form, submits
   ```
   Contestant → Redemption Wizard → redeem-x validates
   ```

4. **Webhook**: redeem-x sends redemption data back to QuestPay
   ```
   redeem-x → POST https://questpay.app/webhooks/voucher → QuestPay
   ```

5. **Scoring**: QuestPay receives webhook, calculates score, updates leaderboard

6. **Next Voucher**: QuestPay generates next voucher, displays code to contestant

---

## API Changes Required in redeem-x

### New Endpoints
- `POST /api/vouchers/bulk-create` - Batch generation
- `GET /api/vouchers/{code}/status` - Query redemption status

### Modified Endpoints
- `POST /api/vouchers/create` - Accept `external_metadata` parameter

### Enhanced Webhooks
- `voucher.redeemed` - Include full payload (GPS, photos, timing)
- `voucher.manual_verification` - New event for admin actions
- `voucher.clicked` - New event (optional)
- `voucher.wizard_started` - New event (optional)

### Database Schema
```sql
ALTER TABLE vouchers ADD COLUMN external_metadata JSON;
ALTER TABLE vouchers ADD COLUMN clicked_at TIMESTAMP NULL;
ALTER TABLE vouchers ADD COLUMN redemption_started_at TIMESTAMP NULL;
ALTER TABLE vouchers ADD COLUMN redemption_duration_seconds INT NULL;

-- For location validation
ALTER TABLE campaigns ADD COLUMN location_validation JSON NULL;

-- For time constraints
ALTER TABLE vouchers ADD COLUMN time_window JSON NULL;
```

---

## Configuration Example

### Campaign Configuration (redeem-x)
```json
{
  "name": "QuestPay Challenge Template",
  "amount": 100,
  "input_fields": [
    {"type": "photo", "label": "Receipt Photo", "required": true},
    {"type": "location", "label": "Your Location", "required": true},
    {"type": "text", "label": "Describe what you bought"}
  ],
  "location_validation": {
    "required": true,
    "radius_meters": 50,
    "on_failure": "block"
  },
  "time_constraints": {
    "track_duration": true,
    "time_limit_minutes": 30
  }
}
```

### Voucher Creation (QuestPay → redeem-x)
```json
POST /api/vouchers/create
{
  "campaign_id": 123,
  "mobile": "09173011987",
  "external_metadata": {
    "game_id": "MMS-001",
    "episode_id": "EP-01",
    "challenge_id": "CH-005",
    "contestant_id": "CONT-042",
    "sequence": 3,
    "challenge_type": "location",
    "location": {
      "name": "Quiapo Market",
      "lat": 14.5547,
      "lng": 121.0244
    }
  },
  "time_window": {
    "start_time": "09:00",
    "end_time": "17:00"
  }
}
```

---

## Effort Estimate

| Feature | Effort | Notes |
|---------|--------|-------|
| External metadata | 1 day | Database + API changes |
| Enhanced webhook | 2 days | Payload structure, timing tracking |
| Bulk generation API | 1 day | Batch processing endpoint |
| Location validation | 2 days | Distance calculation, validation logic |
| Time constraints | 2 days | Time window checks, duration tracking |
| Status query API | 1 day | Read-only endpoint |
| Status callbacks | 2 days | Multiple webhook events |
| Manual verification webhook | 1 day | Admin action event |

**Total: ~12 days** (2-3 weeks for 1 developer)

---

## Next Steps

1. ✅ Review this gap analysis
2. Prioritize features for MVP (recommend: Critical + High priority)
3. Create branch in redeem-x: `feature/questpay-api-enhancements`
4. Implement Phase 1 (Core API Extensions)
5. Update API documentation with new endpoints
6. QuestPay team begins integration using new APIs

---

**For full QuestPay system architecture**: See [QUESTPAY_IMPLEMENTATION_PLAN.md](./QUESTPAY_IMPLEMENTATION_PLAN.md)

**For executive overview**: See [QUESTPAY_EXECUTIVE_SUMMARY.md](./QUESTPAY_EXECUTIVE_SUMMARY.md)
