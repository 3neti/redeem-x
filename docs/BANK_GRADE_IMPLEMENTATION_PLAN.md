# Bank-Grade API Implementation Plan

## Executive Summary
Transform Redeem-X from a production-ready voucher system (current: ~65% bank-grade) to a fully bank-ready financial API platform (~95% bank-grade). Focus on enterprise requirements that banks demand: formal API documentation, idempotency, reconciliation endpoints, enhanced security documentation, and comprehensive monitoring.

## Current State Assessment

### Strengths (Production-Ready)
- **Authentication**: Laravel Sanctum with bearer tokens, WorkOS integration
- **Rate Limiting**: Configured per route group (60/min authenticated, 10/min public, 30/min webhooks)
- **Audit Trail**: Complete `disbursement_attempts` table with request/response logging, scopes for reporting
- **Financial Accuracy**: Centavo-based calculations, dual settlement rail support (INSTAPAY/PESONET), fee strategies
- **Test Coverage**: 86 test files (PHPUnit + Pest), 29 Postman E2E test scenarios
- **API Structure**: Versioned API (`/api/v1`), proper HTTP status codes (200, 201, 422, 429), structured error responses
- **Timing Tracking**: Click, start, submit tracking (idempotent) for fraud detection
- **Alert System**: Real-time disbursement failure alerts with throttling

### Gaps (Bank Requirements)
- **No idempotency keys** for critical endpoints (voucher generation, disbursement)
- **No OpenAPI/Swagger specification** (Postman collection exists but not exportable as OpenAPI)
- **No reconciliation API endpoints** (data exists in `disbursement_attempts`, not exposed)
- **No health check endpoint** for monitoring/SLA tracking
- **Webhook signature verification documented but implementation unclear**
- **No formal security documentation** for bank integration teams
- **No sandbox/staging environment documentation**
- **No data retention/privacy policy documentation**

### Risk Assessment
- **Low Risk**: API already handles money correctly, has audit trail
- **Medium Risk**: Retry safety without idempotency could cause duplicate vouchers/disbursements
- **High Impact**: Banks won't sign without formal docs and idempotency guarantees

## Implementation Phases

### Phase 1: Critical Financial Safety (Week 1)
**Goal**: Prevent duplicate financial transactions

#### 1.1 Idempotency Key System
**Endpoints to protect**:
- `POST /api/v1/vouchers` (voucher generation)
- `POST /api/v1/vouchers/{code}/redeem` (redemption)
- `POST /api/v1/topup` (wallet top-up)

**Implementation**:
- Add `idempotency_key` column to `vouchers`, `contacts`, `topups` tables (string, unique, nullable)
- Create `IdempotencyMiddleware` to check for duplicate requests
- Cache idempotency responses (24h TTL) to return same result on retry
- Update API request validation to require `Idempotency-Key` header for POST/PUT

**Acceptance Criteria**:
- Duplicate voucher generation with same key returns cached response (201)
- Different key generates new voucher
- Missing key on critical endpoints returns 400
- Expired key (>24h) allows new request
- Test coverage: 5+ test cases per endpoint

**Files to modify**:
- `app/Http/Middleware/EnsureIdempotentRequest.php` (new)
- `database/migrations/*_add_idempotency_keys.php` (new)
- `app/Http/Requests/Api/Vouchers/GenerateVouchersRequest.php`
- `app/Actions/Api/Vouchers/GenerateVoucher.php`
- `tests/Feature/Api/IdempotencyTest.php` (new)

#### 1.2 Health Check Endpoint
**Endpoints**:
- `GET /health` (public, no auth)
- `GET /api/v1/health` (detailed, authenticated)

**Response format**:
```json
{
  "status": "healthy|degraded|down",
  "timestamp": "2025-12-28T15:00:00Z",
  "version": "1.0.0",
  "checks": {
    "database": {"status": "up", "latency_ms": 12},
    "cache": {"status": "up"},
    "queue": {"status": "up", "pending_jobs": 5},
    "payment_gateway": {"status": "up", "last_check": "2025-12-28T14:59:00Z"}
  }
}
```

**Files to create**:
- `app/Actions/Api/System/GetHealth.php`
- `app/Services/HealthCheckService.php`
- `routes/api.php` (add health routes)

### Phase 2: Bank Integration Documentation (Week 1-2)
**Goal**: Enable bank integration teams to evaluate and integrate

#### 2.1 OpenAPI 3.0 Specification
**Approach**: Generate from Laravel routes + manual enrichment

**Steps**:
1. Install `vyuldashev/laravel-openapi` or `dedoc/scramble`
2. Generate base spec from routes
3. Enrich with examples, descriptions, schemas
4. Host via Redoc or Swagger UI

**Sections to document**:
- Authentication (Sanctum tokens)
- Idempotency headers
- Rate limiting behavior
- Error response formats
- Webhook callback formats
- Pagination structure

**Files to create**:
- `docs/openapi.yaml` or `public/api-docs/openapi.json`
- `resources/views/api-docs.blade.php` (Redoc/Swagger UI host)
- Route: `GET /api/docs`

#### 2.2 Security & Integration Guide
**Document**:
- API key generation process
- Token expiration and refresh
- IP whitelisting (if implemented)
- Webhook signature verification algorithm
- Rate limit details per endpoint
- Idempotency key requirements
- Sandbox vs production URLs

**Files to create**:
- `docs/BANK_INTEGRATION_GUIDE.md`
- `docs/SECURITY_SPECIFICATION.md`
- `docs/SANDBOX_ENVIRONMENT.md`

#### 2.3 Data Retention & Privacy Policy
**Document**:
- PII storage duration (contacts, redemptions)
- Data deletion API endpoints
- GDPR/BSP compliance notes
- Encryption at rest policy
- Geographic data storage

**Files to create**:
- `docs/DATA_RETENTION_POLICY.md`
- `app/Actions/Api/Contacts/DeleteContactData.php` (new endpoint)

### Phase 3: Reconciliation & Reporting (Week 2)
**Goal**: Enable daily bank reconciliation and settlement reporting

#### 3.1 Reconciliation API Endpoints
**Leverage existing `disbursement_attempts` table**

**Endpoints**:
- `GET /api/v1/reports/disbursements` - All disbursements with filters
- `GET /api/v1/reports/disbursements/failed` - Failed only
- `GET /api/v1/reports/disbursements/summary` - Aggregated stats
- `GET /api/v1/reports/settlements` - Grouped by rail (INSTAPAY/PESONET)

**Query Parameters**:
```
from_date       (required) ISO 8601 date
to_date         (required) ISO 8601 date
status          (optional) success|failed|pending
settlement_rail (optional) INSTAPAY|PESONET
gateway         (optional) netbank|icash
error_type      (optional) timeout|gateway_error|insufficient_funds
per_page        (optional) 1-500, default 100
```

**Response Format (CSV + JSON)**:
```json
{
  "data": {
    "disbursements": [
      {
        "reference_id": "DISB-123",
        "voucher_code": "ABC1",
        "amount": 100.00,
        "currency": "PHP",
        "mobile": "09171234567",
        "bank_code": "GXCHPHM2XXX",
        "settlement_rail": "INSTAPAY",
        "status": "success",
        "gateway_transaction_id": "GW-789",
        "attempted_at": "2025-12-28T10:00:00Z",
        "completed_at": "2025-12-28T10:00:05Z"
      }
    ],
    "summary": {
      "total_count": 150,
      "success_count": 145,
      "failed_count": 5,
      "total_amount": 15000.00,
      "success_amount": 14500.00
    }
  },
  "meta": {
    "pagination": {...},
    "filters_applied": {...}
  }
}
```

**Files to create**:
- `app/Actions/Api/Reports/GetDisbursementReport.php`
- `app/Actions/Api/Reports/GetFailedDisbursements.php`
- `app/Actions/Api/Reports/GetDisbursementSummary.php`
- `app/Actions/Api/Reports/GetSettlementReport.php`
- `app/Http/Requests/Api/Reports/DisbursementReportRequest.php`
- `routes/api/reports.php` (new)

#### 3.2 Export Formats
**Support CSV export for Excel compatibility**

**Implementation**:
- Add `Accept: text/csv` header support
- Use Laravel's `response()->streamDownload()` for large exports
- Filename format: `disbursements_YYYYMMDD_YYYYMMDD.csv`

### Phase 4: Enhanced Security (Week 2-3)
**Goal**: Meet bank security audit requirements

#### 4.1 Request Signing (Optional - Tier 3)
**HMAC-SHA256 signature for critical endpoints**

**Only if banks require it** (not in initial phases)

**Headers**:
```
X-Signature: sha256=<hex_digest>
X-Timestamp: 1735392000
```

**Signature Payload**:
```
POST\n/api/v1/vouchers\n1735392000\n{json_body}
```

**Implementation**:
- `app/Http/Middleware/VerifyRequestSignature.php`
- Store API secrets in database per client
- Reject requests with timestamp >5min old (replay attack prevention)

#### 4.2 Webhook Signature Verification
**Complete existing webhook verification**

**Current State**: Documented but implementation unclear

**Action Items**:
1. Review `routes/api/webhooks.php` - signature verification mentioned
2. Implement in webhook handlers if missing
3. Document algorithm in API docs
4. Add test coverage

**Files to verify/update**:
- `app/Actions/Api/Webhooks/HandlePaymentWebhook.php`
- `app/Actions/Api/Webhooks/HandleSmsWebhook.php`

### Phase 5: Testing & Validation (Week 3)
**Goal**: Ensure all changes are production-ready

#### 5.1 Comprehensive Test Coverage
**Add tests for new features**:
- Idempotency: 15+ test cases (duplicate detection, expiry, cache behavior)
- Reconciliation: 10+ test cases (filters, aggregations, exports)
- Health checks: 8+ test cases (degraded states, component failures)
- Security: 5+ test cases (signature verification, rate limit enforcement)

**Target**: 90%+ code coverage for new modules

#### 5.2 Postman Collection Updates
**Add folders for new endpoints**:
- Idempotency testing (duplicate requests)
- Reconciliation reports (date range queries)
- Health checks (monitoring)
- Data deletion (GDPR compliance)

**Update existing**:
- Add `Idempotency-Key` header to critical requests
- Document expected behavior in descriptions

#### 5.3 Load Testing
**Simulate bank API usage patterns**:
- Bulk voucher generation (1000 vouchers)
- Concurrent redemptions (100 simultaneous)
- Report exports (30-day windows)
- Rate limit behavior under load

**Tools**: Apache Bench or k6

### Phase 6: Documentation & Handoff (Week 3-4)
**Goal**: Prepare for bank technical review

#### 6.1 Bank Integration Checklist
Create comprehensive checklist for banks:
- [ ] API credentials provisioned
- [ ] IP whitelist configured (if applicable)
- [ ] Sandbox access tested
- [ ] Webhook endpoints configured
- [ ] Reconciliation schedule agreed
- [ ] Support contact established
- [ ] SLA terms reviewed

#### 6.2 Runbook for Operations
**Document**:
- Monitoring setup (health checks, alerts)
- Incident response procedures
- Database backup/restore
- Gateway failover procedures
- Rate limit adjustment process

**Files to create**:
- `docs/OPERATIONS_RUNBOOK.md`
- `docs/INCIDENT_RESPONSE.md`

## Implementation Schedule

### Week 1 (Days 1-5)
- Day 1-2: Idempotency middleware and database changes
- Day 3: Health check endpoints
- Day 4-5: OpenAPI spec generation and hosting

### Week 2 (Days 6-10)
- Day 6-7: Reconciliation API endpoints
- Day 8: CSV export functionality
- Day 9-10: Security documentation and webhook verification

### Week 3 (Days 11-15)
- Day 11-12: Comprehensive test suite
- Day 13: Postman collection updates
- Day 14: Load testing
- Day 15: Documentation review

### Week 4 (Days 16-20)
- Day 16-18: Bank integration guide finalization
- Day 19: Operations runbook
- Day 20: Final review and handoff preparation

## Success Metrics

### Technical Metrics
- 100% of critical endpoints support idempotency
- OpenAPI spec covers 100% of public endpoints
- 90%+ test coverage for new modules
- Health check responds <100ms
- Reconciliation reports generate <5s for 30-day window

### Business Metrics
- Bank technical review completed without blockers
- Zero duplicate transactions in production
- Reconciliation accuracy 100%
- API uptime 99.9%+
- Mean time to first response <2 hours

## Risk Mitigation

### Technical Risks
- **Risk**: Idempotency breaks existing clients
  - **Mitigation**: Make header optional initially, enforce after transition period
- **Risk**: Reconciliation queries slow for large date ranges
  - **Mitigation**: Add database indexes, implement cursor pagination
- **Risk**: OpenAPI spec drift from actual API
  - **Mitigation**: Auto-generate from code, add CI validation

### Business Risks
- **Risk**: Banks require features not in plan
  - **Mitigation**: Present plan early, iterate based on feedback
- **Risk**: Timeline slips due to unforeseen complexity
  - **Mitigation**: Phase 1-3 are MVP, Phase 4-6 can be adjusted

## Future Enhancements (Post-MVP)
- Multi-currency support
- Advanced fraud detection (ML-based)
- Real-time balance synchronization
- Batch processing API (async operations)
- GraphQL endpoint for flexible queries
- Admin dashboard for failed disbursements (Phase 3 from DISBURSEMENT_FAILURE_ALERTS.md)

## Appendix: Key Dependencies

### Laravel Packages
- `vyuldashev/laravel-openapi` or `dedoc/scramble` - OpenAPI generation
- `maatwebsite/excel` - CSV export (already installed for reporting)
- `spatie/laravel-health` (optional) - Health check framework

### Infrastructure
- Redis (for idempotency cache and rate limiting)
- Database indexes on `disbursement_attempts` (reference_id, attempted_at, status)
- Log aggregation (Papertrail, Loggly, or ELK stack)

### Monitoring
- Health check endpoint integrated with UptimeRobot or Pingdom
- API response time tracking (New Relic or Datadog)
- Disbursement failure rate alerts (already implemented via email)

## Sign-Off Requirements

Before proceeding:
- [x] Review current state assessment accuracy
- [x] Confirm priority of phases (can reorder if needed)
- [x] Validate technical approach for idempotency
- [x] Agree on documentation hosting (Redoc vs Swagger UI)
- [ ] Clarify bank-specific requirements (if known)
