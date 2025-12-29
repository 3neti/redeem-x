# Data Retention Policy

**Version**: 1.0.0  
**Last Updated**: 2025-12-29  
**Effective Date**: 2025-01-01  
**Next Review**: 2025-12-29

## Table of Contents
1. [Policy Overview](#policy-overview)
2. [Legal & Regulatory Framework](#legal--regulatory-framework)
3. [Data Categories & Retention](#data-categories--retention)
4. [Data Deletion Procedures](#data-deletion-procedures)
5. [User Rights](#user-rights)
6. [Technical Implementation](#technical-implementation)
7. [Compliance Monitoring](#compliance-monitoring)

## Policy Overview

### Purpose
This Data Retention Policy defines how Redeem-X Philippines Inc. ("Redeem-X") collects, stores, retains, and deletes personal and financial data to:
- Comply with Philippines Data Privacy Act of 2012 (R.A. 10173)
- Meet BSP (Bangko Sentral ng Pilipinas) regulatory requirements
- Honor GDPR principles for international partners
- Protect user privacy and data security

### Scope
This policy applies to:
- All personal data processed by Redeem-X
- Financial transaction records
- System logs and audit trails
- Backup and archived data
- Third-party processors (subprocessors)

### Key Principles
1. **Data Minimization**: Collect only data necessary for service delivery
2. **Purpose Limitation**: Use data only for stated purposes
3. **Storage Limitation**: Retain data no longer than necessary
4. **Accuracy**: Keep data accurate and up-to-date
5. **Security**: Protect data with appropriate technical measures

## Legal & Regulatory Framework

### Philippines Data Privacy Act of 2012
- **Authority**: National Privacy Commission (NPC)
- **Registration**: PH-DPA-2024-001234 (Redeem-X registration number)
- **Data Protection Officer**: privacy@redeem-x.com
- **Requirements**:
  - Explicit consent for data collection
  - Right to access, correction, erasure
  - Mandatory breach notification (72 hours)
  - Annual compliance reporting

### BSP Regulations
- **Circular No. 808 (2014)**: Guidelines on Electronic Banking
  - 7-year retention for financial transactions
  - Audit trail requirements
  - KYC/CDD documentation
- **Circular No. 1022 (2019)**: Technology Risk Management
  - Log retention: 90 days minimum
  - Incident records: 3 years
- **AMLA** (Anti-Money Laundering Act):
  - Customer records: 5 years after account closure
  - Transaction records: 5 years from date of transaction

### GDPR Considerations (For EU Partners)
- **Lawful Basis**: Legitimate interest + Contract performance
- **Data Controller**: Redeem-X Philippines Inc.
- **EU Representative**: None required (no EU establishment)
- **Standard Contractual Clauses**: In place for EU data transfers

## Data Categories & Retention

### 1. User Account Data

#### Account Information
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| Email address | Authentication, notifications | Until account deletion + 30 days | Contract |
| Hashed password | Authentication | Until account deletion | Contract |
| User ID | System identifier | Indefinite (anonymized after deletion) | Legitimate interest |
| Account creation date | Audit trail | 7 years | BSP requirement |
| Last login timestamp | Security monitoring | 90 days | Legitimate interest |
| MFA settings | Security | Until disabled + 30 days | Contract |

**Retention**: Active duration + 30 days after account closure  
**Deletion Method**: Hard delete (overwrite with random data)

#### API Tokens
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| Token hash (SHA-256) | Authentication | Until revoked | Contract |
| Token name/description | User reference | Until revoked | Contract |
| Token scopes | Authorization | Until revoked | Contract |
| Last used timestamp | Security audit | 90 days | Legitimate interest |

**Retention**: Until manually revoked or expired  
**Deletion Method**: Soft delete (marked as revoked, hard delete after 30 days)

### 2. Contact Data (Voucher Recipients)

#### Personal Information
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| Mobile number | Disbursement, notifications | 3 years after last transaction | Contract + BSP |
| Full name | KYC, identification | 3 years after last transaction | Contract + AMLA |
| Email address | Notifications, receipts | 3 years after last transaction | Contract |
| Location data (lat/long) | Fraud prevention, analytics | 1 year after collection | Legitimate interest |
| Selfie images | KYC verification | 5 years after transaction | AMLA requirement |
| Signature images | Proof of receipt | 5 years after transaction | BSP + legal defense |

**Retention**: 3 years after last transaction (5 years for KYC-related data)  
**Deletion Method**: 
- Names/emails: Hard delete (GDPR "right to erasure")
- Mobile: Anonymized (last 4 digits kept for audit)
- Images: Purged from storage + CDN

#### KYC Data (HyperVerge Integration)
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| KYC transaction ID | Audit trail | 5 years | AMLA |
| ID document type | Verification | 5 years | AMLA |
| ID document images | Compliance | 5 years | AMLA |
| Face match score | Risk assessment | 5 years | AMLA |
| KYC status | Transaction validation | 5 years | AMLA |
| Rejection reasons | Compliance reporting | 5 years | AMLA |

**Retention**: 5 years from transaction date (AMLA requirement)  
**Deletion Method**: 
- Images: Encrypted archival to cold storage (year 3-5)
- Metadata: Anonymized after 5 years (keep for statistical analysis)

### 3. Voucher Data

#### Voucher Records
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| Voucher code | Financial record | 7 years | BSP requirement |
| Amount & currency | Accounting | 7 years | BSP requirement |
| Issuer user ID | Audit trail | 7 years | BSP requirement |
| Generation timestamp | Audit trail | 7 years | BSP requirement |
| Expiration date | Business logic | 7 years | BSP requirement |
| Redemption timestamp | Financial record | 7 years | BSP requirement |
| Redeemer contact ID | Transaction link | 7 years | BSP requirement |
| Instructions (JSON) | Service delivery | 1 year after expiry | Contract |

**Retention**: 7 years from generation date (BSP requirement)  
**Deletion Method**: 
- Soft delete (status = 'archived')
- Instructions JSON: Purged after 1 year
- Core financial fields: Never deleted (immutable audit trail)

### 4. Financial Transaction Data

#### Disbursement Attempts
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| Reference ID | Reconciliation | 7 years | BSP requirement |
| Gateway transaction ID | Bank reconciliation | 7 years | BSP requirement |
| Amount & currency | Financial record | 7 years | BSP requirement |
| Settlement rail | Reconciliation | 7 years | BSP requirement |
| Status (success/failed) | Audit trail | 7 years | BSP requirement |
| Error details | Operations | 1 year | Legitimate interest |
| Request/response payload | Debugging | 90 days | Legitimate interest |
| Attempted timestamp | Financial record | 7 years | BSP requirement |

**Retention**: 7 years from transaction date (BSP requirement)  
**Deletion Method**: 
- Core fields: Never deleted (immutable)
- Error payloads: Purged after 90 days
- PII in payloads: Redacted after 90 days

#### Top-Up Transactions
| Data Field | Purpose | Retention Period | Legal Basis |
|-----------|---------|------------------|-------------|
| Reference ID | Reconciliation | 7 years | BSP requirement |
| User ID | Account linkage | 7 years | BSP requirement |
| Amount & currency | Financial record | 7 years | BSP requirement |
| Payment method | Audit trail | 7 years | BSP requirement |
| Gateway response | Reconciliation | 1 year | Contract |
| Timestamp | Financial record | 7 years | BSP requirement |

**Retention**: 7 years from transaction date  
**Deletion Method**: Soft delete (marked as archived), never purged

### 5. System Logs & Audit Trails

#### Application Logs
| Log Type | Purpose | Retention Period | Legal Basis |
|----------|---------|------------------|-------------|
| Authentication events | Security | 90 days | Legitimate interest |
| API requests (GET) | Debugging | 30 days | Legitimate interest |
| API requests (POST/PUT/DELETE) | Audit trail | 7 years | BSP requirement |
| Error logs | Operations | 90 days | Legitimate interest |
| Performance metrics | Monitoring | 30 days | Legitimate interest |

**Retention**: 30-90 days (routine), 7 years (financial)  
**Deletion Method**: Automatic rotation (log rotation)

#### Security Logs
| Log Type | Purpose | Retention Period | Legal Basis |
|----------|---------|------------------|-------------|
| Failed login attempts | Security | 90 days | Legitimate interest |
| Rate limit violations | Security | 90 days | Legitimate interest |
| IP address logs | Fraud prevention | 90 days | Legitimate interest |
| Session data | Security | 24 hours | Contract |
| MFA challenges | Audit trail | 90 days | Legitimate interest |

**Retention**: 90 days (security), 24 hours (sessions)  
**Deletion Method**: Automatic expiration (Redis TTL, database cleanup)

### 6. Analytics & Aggregated Data

#### Business Metrics
| Data Type | Retention Period | Notes |
|-----------|------------------|-------|
| Voucher generation stats | Indefinite | Fully anonymized |
| Redemption rates | Indefinite | No PII |
| Geographic heatmaps | Indefinite | Aggregated only (no precise locations) |
| Error rate trends | Indefinite | No user identifiers |
| Revenue reports | Indefinite | Aggregated only |

**Retention**: Indefinite (no PII, fully anonymized)  
**Deletion Method**: N/A (anonymous by design)

## Data Deletion Procedures

### Automated Deletion

#### Daily Jobs (Laravel Scheduler)
```php
// Storage/app/Console/Kernel.php

// Purge expired sessions (24 hours)
$schedule->command('session:gc')->daily();

// Anonymize contacts (3 years after last transaction)
$schedule->command('privacy:anonymize-contacts')->daily();

// Purge old error logs (90 days)
$schedule->command('logs:purge --days=90')->daily();

// Archive old voucher instructions (1 year after expiry)
$schedule->command('vouchers:archive-instructions')->weekly();
```

#### Monthly Jobs
- Hard delete soft-deleted API tokens (30 days)
- Purge request/response payloads from disbursement attempts (90 days)
- Archive KYC images to cold storage (3 years)

#### Annual Jobs
- Generate data retention compliance report
- Review retention periods for regulatory changes
- Audit backup retention compliance

### Manual Deletion

#### User-Initiated Account Deletion
```bash
# Via dashboard: Settings → Privacy → Delete Account
# Confirmation required: Email verification code

# Effects:
1. Immediate: Session termination, API token revocation
2. Within 30 days: Hard delete of:
   - Email, password, profile data
   - Non-financial logs
   - MFA settings
3. Retained (7 years, BSP requirement):
   - User ID (anonymized: user_deleted_12345)
   - Financial transactions (vouchers, disbursements)
   - Transaction-linked contact data
```

#### GDPR "Right to Erasure" Request
```bash
POST /api/v1/users/{id}/gdpr-delete
Authorization: Bearer {admin-token}
Content-Type: application/json

{
  "user_id": 123,
  "confirmation_email": "user@example.com",
  "reason": "GDPR Article 17 request"
}

# Effects:
- PII anonymized within 72 hours
- Financial records retained (legal obligation exception)
- Data export provided before deletion
```

### Deletion Verification

#### Audit Log Entry (Immutable)
```json
{
  "event": "user.gdpr_deleted",
  "timestamp": "2025-12-29T15:00:00Z",
  "user_id": 123,
  "initiator": "admin@redeem-x.com",
  "data_deleted": [
    "users.email",
    "users.name",
    "contacts.full_name",
    "contacts.email"
  ],
  "data_retained": [
    "vouchers.code",
    "disbursement_attempts.*"
  ],
  "verification_hash": "sha256:abc123..."
}
```

## User Rights

### Right to Access (DPA Section 16)
Users can request a copy of all personal data held:

```bash
GET /api/v1/users/me/data-export
Authorization: Bearer {token}

# Response: JSON file with all user data
# Delivered within 15 days (DPA requirement: 30 days max)
```

**Includes**:
- Account information
- Transaction history
- Contact data (if redeemed vouchers)
- Login history (last 90 days)
- API token metadata

### Right to Rectification (DPA Section 17)
Users can update inaccurate personal data:

```bash
PATCH /api/v1/users/me
Authorization: Bearer {token}
Content-Type: application/json

{
  "email": "updated@example.com",
  "notification_preferences": {
    "sms": false,
    "email": true
  }
}
```

**Note**: Financial records (vouchers, transactions) are immutable once created.

### Right to Erasure (DPA Section 18)
Users can request deletion:

```bash
DELETE /api/v1/users/me
Authorization: Bearer {token}
X-Confirmation-Code: {email-code}

# Exceptions (retained data):
- Financial transactions (BSP 7-year requirement)
- Audit trails (legal obligation)
- Anonymized analytics (no PII)
```

### Right to Data Portability (DPA Section 19)
Users can export data in machine-readable format:

```bash
GET /api/v1/users/me/data-export?format=json
Authorization: Bearer {token}

# Response: application/json with structured data
# Also available: CSV format for transaction history
```

### Right to Object (DPA Section 20)
Users can opt-out of:
- Marketing communications (anytime)
- Analytics tracking (anytime)
- Automated decision-making (contact support)

## Technical Implementation

### Database Schema Flags

```sql
-- Users table
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN anonymized_at TIMESTAMP NULL;

-- Contacts table
ALTER TABLE contacts ADD COLUMN anonymized_at TIMESTAMP NULL;

-- Vouchers table (immutable, never deleted)
ALTER TABLE vouchers ADD COLUMN archived_at TIMESTAMP NULL;
```

### Anonymization Strategy

#### Pseudonymization (Reversible)
- **Method**: AES-256 encryption with user-specific key
- **Use Case**: Temporary anonymization for analytics
- **Reversibility**: Possible with decryption key

#### Anonymization (Irreversible)
- **Method**: Data overwrite with generic values
- **Example**:
  ```sql
  UPDATE contacts
  SET 
    full_name = 'DELETED USER',
    email = CONCAT('deleted_', id, '@example.com'),
    mobile = CONCAT('09', LPAD(id, 9, '0')),
    anonymized_at = NOW()
  WHERE id = 123;
  ```

### Backup Retention

#### Database Backups
- **Frequency**: Hourly incremental, daily full
- **Retention**: 
  - Hot backups: 7 days (immediate restore)
  - Cold backups: 7 years (compliance)
- **Deletion**: After 7 years, encrypted archives deleted
- **GDPR Compliance**: Backups exempt from "right to erasure" (legal obligation)

#### File Backups (S3/GCS)
- **KYC Documents**: 5 years, then deleted
- **Signatures/Selfies**: 5 years, then deleted
- **Versioning**: Disabled (no need for old versions)

## Compliance Monitoring

### Annual Audit Checklist
- [ ] Review all retention periods for regulatory changes
- [ ] Verify automated deletion jobs ran successfully
- [ ] Audit sample of deleted data for verification
- [ ] Generate compliance report for NPC/BSP
- [ ] Review third-party processor contracts (DPAs)
- [ ] Test GDPR request workflow (access, erasure, export)

### Compliance Reports

#### Monthly Report
- Contacts anonymized: 145
- Vouchers archived: 2,340
- API tokens revoked: 23
- Logs purged: 1.2 GB

#### Annual Report (Submitted to NPC)
- Total user accounts: 10,500
- GDPR requests processed: 12
- Data breaches: 0
- Third-party processors: 5 (AWS, WorkOS, SendGrid, EngageSpark, Cloudflare)

### Non-Compliance Penalties (DPA 2012)
- **Negligence**: PHP 500,000 - PHP 5,000,000
- **Malice**: Imprisonment (3-6 years) + fines
- **Unauthorized access**: Imprisonment (1-3 years) + fines

## Contact Information

### Data Protection Officer
- **Name**: [DPO Name]
- **Email**: privacy@redeem-x.com
- **Phone**: +63 2 1234 5678
- **Office Hours**: Mon-Fri, 9 AM - 5 PM PHT

### National Privacy Commission
- **Website**: https://www.privacy.gov.ph
- **Hotline**: 1388
- **Email**: info@privacy.gov.ph

### User Inquiries
For data retention questions:
- Email: support@redeem-x.com
- Live Chat: https://redeem-x.com/support (24/7)

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2025-12-29 | Privacy Team | Initial release |

**Next Review Date**: 2025-12-29  
**Approval**: [CEO Name], [DPO Name]
