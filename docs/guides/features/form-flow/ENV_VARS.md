# Form Flow Environment Variables Reference

**Version**: 1.0  
**Last Updated**: 2026-02-03  
**Package**: `3neti/form-flow` v1.7+

## Table of Contents

1. [Overview](#overview)
2. [Core Form Flow Variables](#core-form-flow-variables)
3. [Handler-Specific Variables](#handler-specific-variables)
4. [Environment-Specific Configurations](#environment-specific-configurations)
5. [Security Considerations](#security-considerations)
6. [Complete .env.example](#complete-envexample)

---

## Overview

This document provides a complete reference for all environment variables used by the form-flow system and its handlers.

**Configuration Hierarchy**:
1. Environment variables (`.env`) - Highest priority
2. Published config files (`config/*.php`) - Can be overridden by .env
3. Package defaults - Lowest priority

**Best Practices**:
- Always add new variables to `.env.example` with descriptions
- Use environment-specific values (see [Environment-Specific Configurations](#environment-specific-configurations))
- Never commit `.env` to version control
- Use secrets management for API keys in production

---

## Core Form Flow Variables

### FORM_FLOW_ROUTE_PREFIX

**Description**: URL prefix for all form flow routes

**Default**: `form-flow`

**Type**: String

**Usage**:
```bash
# .env
FORM_FLOW_ROUTE_PREFIX=form-flow
```

**Impact**:
- Routes become: `/form-flow/{flow_id}`, `/form-flow/{flow_id}/step/{step}`, etc.
- Changing this affects all generated URLs
- Must update callbacks in YAML drivers if changed

**Example values**:
```bash
# Default
FORM_FLOW_ROUTE_PREFIX=form-flow

# Custom prefix
FORM_FLOW_ROUTE_PREFIX=flow

# Multi-tenant
FORM_FLOW_ROUTE_PREFIX=tenant/{tenant_id}/flow
```

---

### FORM_FLOW_MIDDLEWARE

**Description**: Middleware applied to all form flow routes

**Default**: `web`

**Type**: String (comma-separated list)

**Usage**:
```bash
# .env
FORM_FLOW_MIDDLEWARE=web
```

**Impact**:
- Controls session handling, CSRF protection, cookie encryption
- `web` middleware includes: `EncryptCookies`, `VerifyCsrfToken`, `ShareErrorsFromSession`
- Adding `auth` requires authentication for all flows

**Example values**:
```bash
# Default (public flows)
FORM_FLOW_MIDDLEWARE=web

# Authenticated flows only
FORM_FLOW_MIDDLEWARE=web,auth

# API flows (no CSRF, stateless)
FORM_FLOW_MIDDLEWARE=api

# Custom middleware stack
FORM_FLOW_MIDDLEWARE=web,throttle:60,custom.middleware
```

**Security Note**: Always include `web` middleware for CSRF protection unless using API tokens.

---

### FORM_FLOW_DRIVER_DIRECTORY

**Description**: Directory path for YAML driver files (relative to `config/`)

**Default**: `config/form-flow-drivers`

**Type**: String (filesystem path)

**Usage**:
```bash
# .env
FORM_FLOW_DRIVER_DIRECTORY=config/form-flow-drivers
```

**Impact**:
- DriverService loads YAML files from this directory
- Path is relative to Laravel base path
- Changing this requires moving all driver files

**Example values**:
```bash
# Default
FORM_FLOW_DRIVER_DIRECTORY=config/form-flow-drivers

# Custom directory
FORM_FLOW_DRIVER_DIRECTORY=storage/app/drivers

# Multi-tenant (dynamic)
FORM_FLOW_DRIVER_DIRECTORY=config/tenants/{tenant_id}/drivers
```

**File Structure**:
```
config/form-flow-drivers/
├── voucher-redemption.yaml
├── simple-test.yaml
└── examples/
    ├── conditional-flow.yaml
    └── kyc-bio-flow.yaml
```

---

### FORM_FLOW_SESSION_PREFIX

**Description**: Prefix for session keys to avoid collisions

**Default**: `form_flow`

**Type**: String

**Usage**:
```bash
# .env
FORM_FLOW_SESSION_PREFIX=form_flow
```

**Impact**:
- Session keys become: `form_flow.{flow_id}`, `form_flow_ref.{reference_id}`
- Changing this will invalidate existing sessions
- Useful for multi-app deployments sharing session storage

**Example values**:
```bash
# Default
FORM_FLOW_SESSION_PREFIX=form_flow

# App-specific
FORM_FLOW_SESSION_PREFIX=myapp_flow

# Environment-specific
FORM_FLOW_SESSION_PREFIX=dev_form_flow
```

**Session Structure**:
```php
// With default prefix
session('form_flow.abc123') = [
    'flow_id' => 'abc123',
    'reference_id' => 'voucher-TEST',
    'current_step' => 2,
    'collected_data' => [...],
];

// With custom prefix
session('myapp_flow.abc123') = [...];
```

---

### FORM_FLOW_SESSION_LIFETIME

**Description**: Session lifetime for flows (in minutes)

**Default**: `120` (2 hours)

**Type**: Integer

**Usage**:
```bash
# .env
FORM_FLOW_SESSION_LIFETIME=120
```

**Impact**:
- Overrides Laravel's `SESSION_LIFETIME` for form flows only
- Flows expire after this duration of inactivity
- Longer times needed for KYC/media upload flows

**Example values**:
```bash
# Short flows (OTP verification)
FORM_FLOW_SESSION_LIFETIME=15

# Standard (default)
FORM_FLOW_SESSION_LIFETIME=120

# Long flows (KYC with document upload)
FORM_FLOW_SESSION_LIFETIME=240

# Development (never expire)
FORM_FLOW_SESSION_LIFETIME=43200  # 30 days
```

**Recommendation**: Set based on longest expected flow duration + buffer.

---

### FORM_FLOW_CACHE_DRIVERS

**Description**: Enable caching of loaded drivers for performance

**Default**: `true`

**Type**: Boolean

**Usage**:
```bash
# .env
FORM_FLOW_CACHE_DRIVERS=true
```

**Impact**:
- Caches parsed YAML drivers in memory/Redis
- Reduces file I/O on repeated loads
- Disable in development to see YAML changes immediately

**Example values**:
```bash
# Production (enable caching)
FORM_FLOW_CACHE_DRIVERS=true

# Development (disable for hot reload)
FORM_FLOW_CACHE_DRIVERS=false
```

**Clear Cache**:
```bash
php artisan config:clear
php artisan cache:clear
```

---

## Handler-Specific Variables

### Location Handler

#### OPENCAGE_API_KEY

**Description**: API key for OpenCage geocoding service

**Default**: `null`

**Type**: String (API key)

**Usage**:
```bash
# .env
OPENCAGE_API_KEY=your_api_key_here
```

**Required**: Yes (for reverse geocoding)

**Get API Key**: https://opencagedata.com/api

**Example**:
```bash
OPENCAGE_API_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

---

#### LOCATION_HANDLER_CAPTURE_SNAPSHOT

**Description**: Enable map snapshot capture

**Default**: `true`

**Type**: Boolean

**Usage**:
```bash
# .env
LOCATION_HANDLER_CAPTURE_SNAPSHOT=true
```

**Impact**:
- Captures static map image using Leaflet + OpenStreetMap
- Base64-encoded PNG stored in handler response
- Increases payload size (~50-200KB per snapshot)

---

#### LOCATION_HANDLER_REQUIRE_ADDRESS

**Description**: Require formatted address (via reverse geocoding)

**Default**: `false`

**Type**: Boolean

**Usage**:
```bash
# .env
LOCATION_HANDLER_REQUIRE_ADDRESS=false
```

**Impact**:
- If true, flow fails if geocoding API unavailable
- If false, proceeds with lat/lng only

---

### Selfie Handler

#### SELFIE_HANDLER_MAX_FILE_SIZE

**Description**: Maximum selfie file size in kilobytes

**Default**: `5120` (5MB)

**Type**: Integer

**Usage**:
```bash
# .env
SELFIE_HANDLER_MAX_FILE_SIZE=5120
```

**Example values**:
```bash
# Low quality (mobile networks)
SELFIE_HANDLER_MAX_FILE_SIZE=2048  # 2MB

# High quality
SELFIE_HANDLER_MAX_FILE_SIZE=10240  # 10MB
```

---

#### SELFIE_HANDLER_ALLOWED_FORMATS

**Description**: Allowed image formats (comma-separated)

**Default**: `jpg,jpeg,png,webp`

**Type**: String

**Usage**:
```bash
# .env
SELFIE_HANDLER_ALLOWED_FORMATS=jpg,jpeg,png,webp
```

---

### Signature Handler

#### SIGNATURE_HANDLER_WIDTH

**Description**: Signature canvas width in pixels

**Default**: `600`

**Type**: Integer

**Usage**:
```bash
# .env
SIGNATURE_HANDLER_WIDTH=600
```

**Impact**: Affects signature resolution and file size

---

#### SIGNATURE_HANDLER_HEIGHT

**Description**: Signature canvas height in pixels

**Default**: `256`

**Type**: Integer

**Usage**:
```bash
# .env
SIGNATURE_HANDLER_HEIGHT=256
```

---

### KYC Handler (HyperVerge)

#### HYPERVERGE_BASE_URL

**Description**: HyperVerge API base URL

**Default**: `https://ind.idv.hyperverge.co/v1`

**Type**: String (URL)

**Usage**:
```bash
# .env
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
```

**Environments**:
```bash
# Production (India)
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1

# Production (US)
HYPERVERGE_BASE_URL=https://us.idv.hyperverge.co/v1

# Sandbox
HYPERVERGE_BASE_URL=https://sandbox.hyperverge.co/v1
```

---

#### HYPERVERGE_APP_ID

**Description**: HyperVerge application ID

**Default**: `null`

**Type**: String

**Required**: Yes

**Usage**:
```bash
# .env
HYPERVERGE_APP_ID=your_app_id
```

**Get Credentials**: Contact HyperVerge sales

---

#### HYPERVERGE_APP_KEY

**Description**: HyperVerge application key (secret)

**Default**: `null`

**Type**: String (secret)

**Required**: Yes

**Usage**:
```bash
# .env
HYPERVERGE_APP_KEY=your_app_key
```

**Security**: Store in secrets manager in production (AWS Secrets Manager, 1Password, etc.)

---

#### HYPERVERGE_URL_WORKFLOW

**Description**: HyperVerge workflow type

**Default**: `onboarding`

**Type**: String

**Usage**:
```bash
# .env
HYPERVERGE_URL_WORKFLOW=onboarding
```

**Options**: `onboarding`, `verification`, `custom`

---

### OTP Handler (EngageSpark)

#### ENGAGESPARK_API_KEY

**Description**: EngageSpark API key

**Default**: `null`

**Type**: String (API key)

**Required**: Yes

**Usage**:
```bash
# .env
ENGAGESPARK_API_KEY=your_api_key
```

---

#### ENGAGESPARK_ORG_ID

**Description**: EngageSpark organization ID

**Default**: `null`

**Type**: String

**Required**: Yes

**Usage**:
```bash
# .env
ENGAGESPARK_ORG_ID=your_org_id
```

---

#### OTP_HANDLER_CODE_LENGTH

**Description**: OTP code length

**Default**: `6`

**Type**: Integer

**Usage**:
```bash
# .env
OTP_HANDLER_CODE_LENGTH=6
```

**Example values**:
```bash
# Short codes (4 digits)
OTP_HANDLER_CODE_LENGTH=4

# Standard (6 digits)
OTP_HANDLER_CODE_LENGTH=6

# High security (8 digits)
OTP_HANDLER_CODE_LENGTH=8
```

---

#### OTP_HANDLER_EXPIRY_MINUTES

**Description**: OTP expiry time in minutes

**Default**: `5`

**Type**: Integer

**Usage**:
```bash
# .env
OTP_HANDLER_EXPIRY_MINUTES=5
```

---

## Environment-Specific Configurations

### Local Development

```bash
# .env.local
APP_ENV=local
APP_DEBUG=true

# Form Flow - Development Settings
FORM_FLOW_ROUTE_PREFIX=form-flow
FORM_FLOW_MIDDLEWARE=web
FORM_FLOW_CACHE_DRIVERS=false  # Disable caching for hot reload
FORM_FLOW_SESSION_LIFETIME=43200  # 30 days (never expire during dev)

# Handlers - Test/Mock APIs
OPENCAGE_API_KEY=test_key_here
HYPERVERGE_BASE_URL=https://sandbox.hyperverge.co/v1
HYPERVERGE_APP_ID=test_app_id
HYPERVERGE_APP_KEY=test_app_key

# OTP - Use test mode (logs instead of sending)
OTP_HANDLER_TEST_MODE=true

# Location - Allow without geocoding
LOCATION_HANDLER_REQUIRE_ADDRESS=false
```

---

### Staging

```bash
# .env.staging
APP_ENV=staging
APP_DEBUG=false

# Form Flow - Staging Settings
FORM_FLOW_ROUTE_PREFIX=form-flow
FORM_FLOW_MIDDLEWARE=web
FORM_FLOW_CACHE_DRIVERS=true  # Enable caching
FORM_FLOW_SESSION_LIFETIME=120

# Handlers - Sandbox APIs
OPENCAGE_API_KEY=${OPENCAGE_STAGING_KEY}  # From secrets manager
HYPERVERGE_BASE_URL=https://sandbox.hyperverge.co/v1
HYPERVERGE_APP_ID=${HYPERVERGE_STAGING_ID}
HYPERVERGE_APP_KEY=${HYPERVERGE_STAGING_KEY}

# OTP - Real SMS but test org
ENGAGESPARK_API_KEY=${ENGAGESPARK_STAGING_KEY}
ENGAGESPARK_ORG_ID=staging_org_id

# Location - Require address (test geocoding)
LOCATION_HANDLER_REQUIRE_ADDRESS=true
```

---

### Production

```bash
# .env.production
APP_ENV=production
APP_DEBUG=false

# Form Flow - Production Settings
FORM_FLOW_ROUTE_PREFIX=form-flow
FORM_FLOW_MIDDLEWARE=web,throttle:60
FORM_FLOW_CACHE_DRIVERS=true
FORM_FLOW_SESSION_LIFETIME=120

# Handlers - Production APIs (use secrets manager)
OPENCAGE_API_KEY=${OPENCAGE_PROD_KEY}
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=${HYPERVERGE_PROD_ID}
HYPERVERGE_APP_KEY=${HYPERVERGE_PROD_KEY}

# OTP - Production org
ENGAGESPARK_API_KEY=${ENGAGESPARK_PROD_KEY}
ENGAGESPARK_ORG_ID=production_org_id

# Location - Strict validation
LOCATION_HANDLER_REQUIRE_ADDRESS=true
LOCATION_HANDLER_CAPTURE_SNAPSHOT=true

# Security
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
```

---

## Security Considerations

### 1. API Keys & Secrets

**Problem**: Sensitive API keys in `.env` file

**Solution**: Use secrets management

**AWS Secrets Manager Example**:
```bash
# .env (production)
HYPERVERGE_APP_KEY=arn:aws:secretsmanager:region:account:secret:hyperverge-key

# config/kyc-handler.php
'app_key' => env('HYPERVERGE_APP_KEY') ?: AWS::getSecret('hyperverge-key'),
```

**Laravel Secrets (Laravel 11+)**:
```bash
php artisan secret:set HYPERVERGE_APP_KEY
```

**Environment Variables in Deployment**:
- Use platform secrets (Heroku Config Vars, Laravel Forge Environment, etc.)
- Never commit `.env` to version control
- Rotate keys regularly

---

### 2. CSRF Protection

**Required Variables**:
```bash
# Must include 'web' middleware
FORM_FLOW_MIDDLEWARE=web
```

**Impact**:
- All POST/PUT/DELETE requests require CSRF token
- Inertia.js handles CSRF automatically
- External webhooks need CSRF exemption

**CSRF Exemption** (for callbacks):
```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'form-flow/*/callback',  // If external webhook posts to callback
];
```

---

### 3. Session Security

**Recommended Settings**:
```bash
# Production
SESSION_DRIVER=redis  # Or database (not file/cookie)
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true  # HTTPS only
SESSION_SAME_SITE=strict  # Prevent CSRF
SESSION_HTTP_ONLY=true  # Prevent XSS
```

**Why**:
- File driver: Not scalable, session fixation risks
- Cookie driver: 4KB limit, client-side tampering
- Database/Redis: Secure, scalable, server-side storage

---

### 4. Rate Limiting

**Prevent Abuse**:
```bash
# .env
FORM_FLOW_MIDDLEWARE=web,throttle:60,1
```

**Explanation**: 60 requests per minute per IP

**Custom Rate Limiting**:
```php
// config/form-flow.php
'middleware' => [
    'web',
    'throttle:flows',  // Custom rate limiter
],

// app/Providers/RouteServiceProvider.php
RateLimiter::for('flows', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});
```

---

### 5. Data Validation

**Environment-Specific Validation**:
```bash
# Development - Lenient
SELFIE_HANDLER_MAX_FILE_SIZE=10240
LOCATION_HANDLER_REQUIRE_ADDRESS=false

# Production - Strict
SELFIE_HANDLER_MAX_FILE_SIZE=5120
LOCATION_HANDLER_REQUIRE_ADDRESS=true
```

**Handler Validation**: Always validate in handlers, not just config

---

### 6. Logging Sensitive Data

**Problem**: API keys in logs

**Solution**: Sanitize logs

```php
// config/logging.php
'channels' => [
    'form-flow' => [
        'driver' => 'daily',
        'path' => storage_path('logs/form-flow.log'),
        'processors' => [
            \App\Logging\SanitizeProcessor::class,  // Remove API keys
        ],
    ],
],
```

---

## Complete .env.example

Full reference for copying to your `.env.example`:

```bash
# ============================================================================
# Form Flow Configuration
# ============================================================================

# Route prefix for form flow URLs
# Default: form-flow
# Example: /form-flow/{flow_id}
FORM_FLOW_ROUTE_PREFIX=form-flow

# Middleware applied to form flow routes
# Default: web
# Options: web, web,auth, web,throttle:60
FORM_FLOW_MIDDLEWARE=web

# Directory for YAML driver files (relative to config/)
# Default: config/form-flow-drivers
FORM_FLOW_DRIVER_DIRECTORY=config/form-flow-drivers

# Session key prefix (avoid collisions)
# Default: form_flow
FORM_FLOW_SESSION_PREFIX=form_flow

# Session lifetime for flows (minutes)
# Default: 120 (2 hours)
FORM_FLOW_SESSION_LIFETIME=120

# Enable driver caching (disable in dev for hot reload)
# Default: true
FORM_FLOW_CACHE_DRIVERS=true

# ============================================================================
# Location Handler (3neti/form-handler-location)
# ============================================================================

# OpenCage Geocoding API key
# Get key: https://opencagedata.com/api
OPENCAGE_API_KEY=

# Capture map snapshot (increases payload ~50-200KB)
# Default: true
LOCATION_HANDLER_CAPTURE_SNAPSHOT=true

# Require formatted address (fail if geocoding unavailable)
# Default: false
LOCATION_HANDLER_REQUIRE_ADDRESS=false

# ============================================================================
# Selfie Handler (3neti/form-handler-selfie)
# ============================================================================

# Maximum selfie file size (KB)
# Default: 5120 (5MB)
SELFIE_HANDLER_MAX_FILE_SIZE=5120

# Allowed image formats (comma-separated)
# Default: jpg,jpeg,png,webp
SELFIE_HANDLER_ALLOWED_FORMATS=jpg,jpeg,png,webp

# ============================================================================
# Signature Handler (3neti/form-handler-signature)
# ============================================================================

# Signature canvas width (pixels)
# Default: 600
SIGNATURE_HANDLER_WIDTH=600

# Signature canvas height (pixels)
# Default: 256
SIGNATURE_HANDLER_HEIGHT=256

# ============================================================================
# KYC Handler (3neti/form-handler-kyc) - HyperVerge Integration
# ============================================================================

# HyperVerge API base URL
# Production (India): https://ind.idv.hyperverge.co/v1
# Production (US): https://us.idv.hyperverge.co/v1
# Sandbox: https://sandbox.hyperverge.co/v1
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1

# HyperVerge application ID
HYPERVERGE_APP_ID=

# HyperVerge application key (keep secret!)
HYPERVERGE_APP_KEY=

# HyperVerge workflow type
# Options: onboarding, verification, custom
# Default: onboarding
HYPERVERGE_URL_WORKFLOW=onboarding

# ============================================================================
# OTP Handler (3neti/form-handler-otp) - EngageSpark Integration
# ============================================================================

# EngageSpark API key
ENGAGESPARK_API_KEY=

# EngageSpark organization ID
ENGAGESPARK_ORG_ID=

# OTP code length
# Default: 6
OTP_HANDLER_CODE_LENGTH=6

# OTP expiry time (minutes)
# Default: 5
OTP_HANDLER_EXPIRY_MINUTES=5
```

---

## Validation Checklist

Before deploying, verify:

- [ ] All required variables are set (check for empty values)
- [ ] API keys are valid (test with curl or Tinker)
- [ ] Environment-specific values used (staging vs production)
- [ ] Secrets stored securely (not in `.env` in production)
- [ ] `.env.example` is up to date
- [ ] Session driver supports nested arrays (database or redis)
- [ ] CSRF protection enabled (`web` middleware included)
- [ ] Rate limiting configured for production
- [ ] Session lifetime appropriate for longest flow

**Test Command**:
```bash
php artisan config:show form-flow
php artisan config:show location-handler
php artisan config:show kyc-handler
# ... etc
```

---

## Related Documentation

- [INTEGRATION.md](./INTEGRATION.md) - Complete integration guide
- [INTEGRATION_CHECKLIST.md](./INTEGRATION_CHECKLIST.md) - Setup checklist
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) - Common issues
- [README.md](./README.md) - Documentation index

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-03  
**Maintained By**: Development Team
