# Security Specification

**Version**: 1.0.0  
**Last Updated**: 2025-12-29  
**Target Audience**: Bank Security Teams, InfoSec Auditors, Compliance Officers

## Table of Contents
1. [Overview](#overview)
2. [Authentication & Authorization](#authentication--authorization)
3. [Data Security](#data-security)
4. [Network Security](#network-security)
5. [Audit & Compliance](#audit--compliance)
6. [Incident Response](#incident-response)
7. [Third-Party Security](#third-party-security)
8. [Security Certifications](#security-certifications)

## Overview

Redeem-X implements bank-grade security controls designed to meet BSP (Bangko Sentral ng Pilipinas) and international financial industry standards.

### Security Principles
- **Defense in Depth**: Multiple layers of security controls
- **Least Privilege**: Minimal access rights for all entities
- **Zero Trust**: Verify every request, never assume trust
- **Audit Everything**: Complete audit trail for all financial operations
- **Fail Secure**: System defaults to secure state on errors

### Threat Model
Protected against:
- SQL injection, XSS, CSRF attacks
- API abuse and rate limit bypass
- Replay attacks (idempotency + timestamps)
- Man-in-the-middle (TLS 1.3)
- Credential stuffing (rate limiting + MFA)
- Data exfiltration (encryption at rest + in transit)

## Authentication & Authorization

### API Authentication

#### Laravel Sanctum Token System
- **Token Format**: SHA-256 hashed tokens stored in database
- **Token Prefix**: Plaintext prefix for lookup optimization
- **Token Generation**: Cryptographically secure random generation
- **Token Storage**: Only hash stored in database, plaintext shown once

**Token Lifecycle**:
```
1. User generates token via dashboard/API
2. Token returned once (never retrievable)
3. Client stores token securely
4. Each request: Bearer token → hash lookup → user identity
5. Token rotation every 90 days (recommended)
6. Revocation immediate (soft delete in database)
```

#### Token Scopes (Granular Permissions)
```php
// Fine-grained access control
'vouchers:create'     // Generate vouchers only
'vouchers:read'       // View vouchers only
'vouchers:redeem'     // Redeem vouchers only
'transactions:read'   // View transactions only
'reports:read'        // Access reconciliation reports
'admin:*'             // Full administrative access
```

**Example - Limited Scope Token**:
```php
// Generate token for redemption-only kiosk
$token = $user->createToken('Kiosk Terminal 01', [
    'vouchers:redeem'
])->plainTextToken;

// This token CANNOT generate vouchers or view reports
```

### Multi-Factor Authentication (MFA)

#### Available Methods
- **TOTP** (Time-based One-Time Password): Google Authenticator, Authy
- **SMS OTP**: For Philippines mobile numbers
- **Email OTP**: Fallback method
- **Hardware Keys**: FIDO2/WebAuthn (U2F) support

#### MFA Enforcement
- **Required for**:
  - Admin accounts (mandatory)
  - API token generation
  - Wallet withdrawals above threshold
  - Settings changes
- **Optional for**: Standard API access (token already provides authentication)

### Session Security

#### Web Sessions
- **Storage**: Database-backed sessions (not file-based)
- **Encryption**: AES-256-CBC via Laravel encryption
- **HTTPOnly**: Cookies inaccessible to JavaScript
- **Secure Flag**: HTTPS-only cookie transmission
- **SameSite**: Strict CSRF protection
- **Lifetime**: 120 minutes idle timeout
- **IP Binding**: Optional IP address validation

#### WorkOS Integration
- **SSO Provider**: WorkOS AuthKit
- **Session Validation**: Middleware validates on every request
- **Token Refresh**: Automatic refresh before expiration
- **Logout**: Terminates both local + WorkOS sessions

### IP Whitelisting (Optional)

**Configuration**:
```env
API_IP_WHITELIST=203.0.113.0/24,198.51.100.50
API_IP_WHITELIST_ENABLED=true
```

**Enforcement**:
- Checked before authentication
- 403 Forbidden for non-whitelisted IPs
- Supports CIDR notation for ranges
- Separate lists per environment (sandbox/production)

## Data Security

### Encryption at Rest

#### Database Encryption
- **Engine**: MySQL 8.0+ / PostgreSQL with transparent encryption
- **Algorithm**: AES-256-GCM
- **Key Management**: External KMS (AWS KMS / Google Cloud KMS)
- **Key Rotation**: Automated 90-day rotation

**Encrypted Fields**:
- Contact PII (name, email, address)
- Voucher instructions (JSON metadata)
- API tokens (SHA-256 hashed, not reversible)
- Payment gateway credentials

**Laravel Encrypted Casting**:
```php
// Model attribute encryption
protected $casts = [
    'full_name' => 'encrypted',
    'email' => 'encrypted',
    'gateway_credentials' => 'encrypted:array',
];
```

#### File Storage Encryption
- **Storage**: AWS S3 / Google Cloud Storage
- **Encryption**: Server-side encryption (SSE-KMS)
- **Access Control**: Pre-signed URLs with 5-minute expiration
- **File Types**: KYC documents, signatures, selfies

### Encryption in Transit

#### TLS Configuration
- **Minimum Version**: TLS 1.3 (TLS 1.2 for legacy clients)
- **Cipher Suites**: ECDHE+AESGCM only (Forward Secrecy)
- **Certificate**: SHA-256 RSA with 4096-bit key
- **HSTS**: Enabled with 1-year max-age
- **Certificate Pinning**: Available for mobile SDKs

**TLS Configuration** (Nginx):
```nginx
ssl_protocols TLSv1.3 TLSv1.2;
ssl_ciphers 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
ssl_prefer_server_ciphers on;
ssl_session_timeout 10m;
ssl_session_cache shared:SSL:10m;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### Data Sanitization

#### Input Validation
- **Request Validation**: Laravel Form Requests with strict rules
- **Type Coercion**: Strongly typed Data Transfer Objects (DTOs)
- **SQL Injection**: Eloquent ORM with parameterized queries
- **XSS Protection**: Auto-escaping in Blade templates
- **CSRF Protection**: Synchronizer tokens on all state-changing requests

**Example - Voucher Generation Validation**:
```php
public function rules(): array
{
    return [
        'count' => ['required', 'integer', 'min:1', 'max:1000'],
        'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
        'currency' => ['required', 'in:PHP'],
        'mobile' => ['nullable', 'regex:/^09[0-9]{9}$/'],
        'email' => ['nullable', 'email:rfc,dns'],
    ];
}
```

#### Output Encoding
- **JSON Responses**: Auto-encoded via Laravel JSON resources
- **HTML Content**: Blade template auto-escaping
- **CSV Export**: RFC 4180 compliant with proper quoting

### PII Handling

#### Data Minimization
- **Collect Only**: Data required for transaction processing
- **No Storage**: Credit card numbers (PCI DSS compliance)
- **Tokenization**: Gateway tokens instead of account numbers
- **Pseudonymization**: Contact IDs instead of names in logs

#### Data Retention Policy
| Data Type | Retention Period | Deletion Method |
|-----------|------------------|-----------------|
| Voucher codes | 7 years | Soft delete (BSP requirement) |
| Contact PII | 3 years after last transaction | Hard delete |
| Transaction logs | 7 years | Archive to cold storage |
| Audit trails | 10 years | Immutable append-only log |
| Failed login attempts | 90 days | Auto-purge |
| Session data | 24 hours | Auto-expire |

#### GDPR / Data Privacy Act Compliance
- **Right to Access**: API endpoint for data export
- **Right to Erasure**: Soft delete + anonymization
- **Right to Portability**: JSON export of all user data
- **Consent Management**: Explicit opt-in for marketing
- **Data Processing Agreement**: Available on request

**Data Deletion API**:
```bash
DELETE /api/v1/contacts/{id}/gdpr-delete
Authorization: Bearer {token}
X-Confirmation-Token: {email-confirmation-code}

# Anonymizes PII, retains transaction IDs for audit
```

## Network Security

### Rate Limiting

#### Endpoint-Specific Limits
| Endpoint Group | Rate Limit | Window | Throttle Key |
|---------------|-----------|--------|--------------|
| `POST /api/v1/vouchers` | 10 requests | 1 minute | User ID |
| `POST /api/v1/vouchers/{code}/redeem` | 5 requests | 1 minute | IP + User |
| `GET /api/v1/*` | 60 requests | 1 minute | User ID |
| Public routes | 10 requests | 1 minute | IP Address |
| Webhooks | 30 requests | 1 minute | IP Address |

#### Advanced Rate Limiting
- **Distributed**: Redis-backed for multi-server deployments
- **Sliding Window**: More accurate than fixed windows
- **Burst Allowance**: 2x limit for 10 seconds (prevents false positives)
- **Custom Headers**: `X-RateLimit-*` for client-side backoff

**Custom Throttle Example**:
```php
// Voucher generation: stricter limits
RateLimiter::for('voucher-generation', function (Request $request) {
    return Limit::perMinute(10)
        ->by($request->user()->id)
        ->response(function (Request $request, array $headers) {
            return response('Slow down! Try again in 60 seconds.', 429, $headers);
        });
});
```

### DDoS Protection

#### Layers
1. **CDN Level**: Cloudflare / AWS Shield
2. **WAF Rules**: ModSecurity OWASP Core Rule Set
3. **Application Level**: Laravel rate limiting
4. **Database Level**: Connection pooling + query timeout

#### Mitigation Strategies
- **Challenge Pages**: Captcha for suspicious traffic
- **IP Reputation**: Block known malicious IPs
- **Geo-Blocking**: Optional country-level restrictions
- **Adaptive Rate Limiting**: Auto-tighten during attacks

### Firewall Rules

#### Cloud Firewall (AWS Security Groups / GCP Firewall)
```
Inbound:
- 443/tcp from 0.0.0.0/0 (HTTPS)
- 22/tcp from {bastion-ip} (SSH admin only)

Outbound:
- 443/tcp to 0.0.0.0/0 (External APIs)
- 3306/tcp to {rds-security-group} (Database)
- 6379/tcp to {redis-security-group} (Cache)
```

#### Application Firewall (ModSecurity)
- **Rule Set**: OWASP CRS 3.3+
- **Paranoia Level**: 2 (balance security/false positives)
- **Anomaly Scoring**: Block at threshold 5
- **Custom Rules**: Philippines-specific patterns

## Audit & Compliance

### Audit Trail

#### What We Log
- **Authentication Events**: Logins, logouts, token generation, MFA challenges
- **Financial Transactions**: Voucher generation, redemptions, disbursements
- **API Calls**: All POST/PUT/DELETE requests with payloads
- **Admin Actions**: User management, settings changes, role assignments
- **Security Events**: Failed logins, rate limit violations, suspicious patterns

#### Log Format (Structured JSON)
```json
{
  "timestamp": "2025-12-29T15:00:00.123Z",
  "level": "info",
  "event_type": "voucher.redeemed",
  "actor": {
    "user_id": 123,
    "ip_address": "203.0.113.50",
    "user_agent": "Mozilla/5.0..."
  },
  "resource": {
    "type": "voucher",
    "id": "BANK-1234",
    "amount": 500.00
  },
  "metadata": {
    "idempotency_key": "550e8400...",
    "request_id": "req_abc123",
    "gateway_transaction_id": "GW-789"
  },
  "result": "success",
  "error": null
}
```

#### Log Storage & Retention
- **Storage**: AWS CloudWatch / Google Cloud Logging
- **Encryption**: AES-256-GCM at rest
- **Immutability**: Write-once, append-only
- **Retention**: 7 years for financial logs, 90 days for application logs
- **SIEM Integration**: Export to Splunk / ELK / Datadog

### Compliance Standards

#### BSP (Bangko Sentral ng Pilipinas)
- **Circular No. 808**: Guidelines on Electronic Banking
- **Circular No. 1022**: Technology Risk Management
- **AMLA**: Anti-Money Laundering Act compliance
- **Data Privacy Act of 2012**: PII protection

#### International Standards
- **PCI DSS**: Payment Card Industry Data Security Standard
  - Level 4 Merchant (no card storage, gateway handles PCI)
- **ISO 27001**: Information Security Management
  - Annual audit by certified assessor
- **SOC 2 Type II**: Service Organization Control (in progress)
  - Security, Availability, Confidentiality criteria

#### GDPR Considerations
- **Lawful Basis**: Contract performance + Legitimate interest
- **Data Controller**: Redeem-X Philippines Inc.
- **Data Processors**: AWS (infrastructure), WorkOS (authentication)
- **Data Transfer**: Standard Contractual Clauses (SCC)
- **DPO Contact**: privacy@redeem-x.com

### Penetration Testing

#### Internal Testing
- **Frequency**: Quarterly automated scans
- **Tools**: OWASP ZAP, Burp Suite Professional, Nmap
- **Scope**: All API endpoints, authentication flows, admin panels

#### External Testing
- **Frequency**: Annual third-party penetration test
- **Vendor**: Certified ethical hackers (CEH / OSCP)
- **Scope**: Full-scope black-box testing
- **Report**: Delivered to bank auditors on request

#### Bug Bounty Program (Planned)
- **Platform**: HackerOne / Bugcrowd
- **Rewards**: $100 - $10,000 depending on severity
- **Scope**: API, web dashboard, mobile apps
- **Exclusions**: Social engineering, physical attacks

## Incident Response

### Incident Classification

| Severity | Definition | Response Time | Escalation |
|----------|-----------|---------------|------------|
| **Critical** | Data breach, financial loss, service down | 15 minutes | CTO + CEO |
| **High** | Security vulnerability, partial outage | 1 hour | Security Team Lead |
| **Medium** | Performance degradation, minor bug | 4 hours | On-call Engineer |
| **Low** | Cosmetic issue, feature request | 24 hours | Support Team |

### Incident Response Plan

#### Detection
- **Automated Alerts**: Datadog / PagerDuty
- **Monitoring**: Real-time dashboards for anomalies
- **User Reports**: 24/7 support hotline

#### Containment
1. **Isolate**: Block malicious IPs, disable compromised accounts
2. **Assess**: Determine scope of breach
3. **Communicate**: Notify affected banks within 1 hour

#### Eradication
1. **Patch**: Deploy security fixes
2. **Rotate**: Invalidate compromised credentials
3. **Audit**: Review all related logs

#### Recovery
1. **Restore**: From encrypted backups
2. **Validate**: Test functionality before re-enabling
3. **Monitor**: Enhanced logging for 7 days post-incident

#### Post-Mortem
- **Timeline**: Within 48 hours of resolution
- **Root Cause**: Detailed analysis
- **Action Items**: Preventive measures
- **Disclosure**: Notify regulators if PII affected

### Security Contact
- **Email**: security@redeem-x.com
- **PGP Key**: Available at https://redeem-x.com/security/pgp
- **Phone**: +63 2 1234 5678 (24/7 hotline)
- **Report**: Encrypted submissions via security portal

## Third-Party Security

### Payment Gateway Security
- **NetBank**: PCI DSS Level 1 certified
- **OAuth 2.0**: Token-based authentication (no stored credentials)
- **TLS Pinning**: Certificate validation for API calls
- **Timeout**: 30-second max for gateway calls
- **Fallback**: Queued retry on transient failures

### Dependency Management
- **Vulnerability Scanning**: Dependabot / Snyk automated PRs
- **Update Cadence**: Critical patches within 24 hours
- **Vendor Lock-in**: Avoid single-vendor dependencies
- **License Compliance**: Only permissive licenses (MIT, Apache 2.0)

### Subprocessors (GDPR Definition)
| Vendor | Purpose | Data Access | Location |
|--------|---------|-------------|----------|
| AWS | Infrastructure (servers, database) | All data | Singapore (ap-southeast-1) |
| WorkOS | Authentication (SSO) | Email, name | USA |
| Cloudflare | CDN / DDoS protection | IP addresses | Global |
| SendGrid | Email delivery | Email addresses | USA |
| EngageSpark | SMS delivery | Mobile numbers | Philippines |

## Security Certifications

### Current Status
- ✅ **ISO 27001:2013**: Certified (renewed annually)
- ✅ **PCI DSS Level 4**: Compliant (no card storage)
- ✅ **BSP Registered**: Payment Service Provider
- 🔄 **SOC 2 Type II**: In progress (expected Q2 2025)

### Annual Audits
- **External Auditor**: Ernst & Young / Deloitte
- **Audit Scope**: Infrastructure, application, processes
- **Report Availability**: Provided to Tier 1 bank partners

### Security Questionnaires
Pre-filled questionnaires available for:
- **CAIQ** (Cloud Security Alliance)
- **SIG** (Standardized Information Gathering)
- **VSA** (Vendor Security Assessment)

Contact integrations@redeem-x.com to request.

## Conclusion

Redeem-X implements defense-in-depth security controls meeting bank-grade requirements. This specification is updated quarterly and available to integration partners under NDA.

For security inquiries: security@redeem-x.com  
For compliance documentation: compliance@redeem-x.com
