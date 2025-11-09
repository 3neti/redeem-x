# Security

This document outlines the security measures implemented in the Redeem-X voucher system.

## Table of Contents

- [Authentication](#authentication)
- [API Security](#api-security)
- [Rate Limiting](#rate-limiting)
- [CSRF Protection](#csrf-protection)
- [XSS Prevention](#xss-prevention)
- [Input Validation](#input-validation)
- [Database Security](#database-security)
- [Session Security](#session-security)
- [Best Practices](#best-practices)

## Authentication

### WorkOS AuthKit Integration

- Primary authentication via **Laravel WorkOS** package
- OAuth 2.0 + OpenID Connect authentication flow
- Centralized user management through WorkOS dashboard
- Multi-factor authentication (MFA) support via WorkOS
- Session validation on every authenticated request

**Configuration:**
```env
WORKOS_CLIENT_ID=your_client_id
WORKOS_API_KEY=your_api_key
WORKOS_REDIRECT_URL=https://your-app.com/auth/callback
```

### Laravel Sanctum

- SPA authentication for web UI (session-based)
- Token authentication for API access (mobile apps, third-party integrations)
- CSRF token verification for all state-changing requests
- Stateless API authentication with personal access tokens

## API Security

### Middleware Stack

All API routes are protected by:
1. **Authentication**: `auth:sanctum` middleware
2. **Rate Limiting**: Configurable per route group
3. **CORS**: Configured in `config/cors.php`
4. **CSRF**: Token verification for session-based requests

### API Route Groups

```php
// Authenticated API routes (60 requests/minute)
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        // Voucher, Transaction, Contact, Settings APIs
    });

// Public redemption API (10 requests/minute)
Route::prefix('v1')
    ->middleware(['throttle:10,1'])
    ->group(function () {
        // Public voucher redemption endpoints
    });

// Webhook API (30 requests/minute)
Route::prefix('v1/webhooks')
    ->middleware(['throttle:30,1'])
    ->group(function () {
        // Webhook handlers with signature verification
    });
```

## Rate Limiting

### Configured Limits

| Route Group | Limit | Window | Notes |
|-------------|-------|--------|-------|
| Authenticated API | 60 requests | 1 minute | For logged-in users |
| Public Redemption | 10 requests | 1 minute | Prevents abuse of public endpoints |
| Webhooks | 30 requests | 1 minute | For external integrations |
| Login Attempts | 5 attempts | 1 minute | Via Laravel Fortify |

### Implementation

Rate limiting uses Laravel's built-in throttle middleware with sliding window algorithm.

**Axios Retry Logic:**
- Failed requests (5xx, 429) are retried with exponential backoff
- Max 3 retries with delays: 1s, 2s, 4s
- Prevents cascading failures
- Respects rate limit responses (429 status code)

### Customizing Rate Limits

Edit `routes/api.php` to adjust limits:
```php
// Example: Increase authenticated API limit to 120/min
->middleware(['auth:sanctum', 'throttle:120,1'])
```

## CSRF Protection

### Session-Based Requests

All session-based API calls from the web UI include CSRF tokens via Laravel Sanctum's cookie-based authentication.

**Axios Configuration** (`resources/js/lib/axios.ts`):
```typescript
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
```

### Token-Based Requests

API token requests bypass CSRF protection as they're stateless and use bearer tokens.

### Verification

CSRF tokens are verified by:
1. `VerifyCsrfToken` middleware (enabled globally)
2. `ValidateCsrfToken` middleware from `laravel/sanctum`

## XSS Prevention

### Input Sanitization

1. **Vue.js Template Escaping**: All dynamic content in Vue templates is automatically escaped
   ```vue
   <!-- Safe - Vue escapes by default -->
   <div>{{ user.name }}</div>
   
   <!-- Dangerous - v-html should be avoided -->
   <div v-html="unsafeContent"></div>
   ```

2. **Backend Validation**: All user inputs validated before processing
   - Form Request classes for structured validation
   - Type hints and strict types (`declare(strict_types=1)`)

3. **Database Query Escaping**: Laravel's Query Builder and Eloquent ORM automatically escape inputs

### Content Security Policy

Consider adding CSP headers in production:
```php
// app/Http/Middleware/SecurityHeaders.php
$response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'");
```

## Input Validation

### Form Requests

All API endpoints use Laravel Form Request classes for validation:

```php
// Example: app/Http/Requests/Api/Vouchers/GenerateVouchersRequest.php
public function rules(): array
{
    return [
        'amount' => ['required', 'numeric', 'min:1'],
        'count' => ['required', 'integer', 'min:1', 'max:1000'],
        'prefix' => ['nullable', 'string', 'max:10'],
        // ...
    ];
}
```

### Validation Rules

- **Required fields**: Enforced at validation layer
- **Type checking**: Strict type hints in PHP 8.2+
- **Range validation**: Min/max constraints on numeric inputs
- **Format validation**: Regex patterns for codes, emails, etc.
- **Sanitization**: Automatic trimming and type casting

## Database Security

### Parameterized Queries

All database queries use Laravel's Query Builder or Eloquent ORM, which prevent SQL injection via parameterized queries.

**Example:**
```php
// Safe - parameterized
$vouchers = Voucher::where('code', $code)->get();

// Never do this - SQL injection risk
$vouchers = DB::select("SELECT * FROM vouchers WHERE code = '$code'");
```

### Mass Assignment Protection

Models use `$fillable` or `$guarded` properties to prevent mass assignment vulnerabilities:

```php
protected $fillable = ['code', 'amount', 'currency', 'status'];
```

### Database Credentials

Store credentials in `.env` file (excluded from version control):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=redeem_x
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

## Session Security

### Configuration

Session security settings in `config/session.php`:
```php
'secure' => env('SESSION_SECURE_COOKIE', true), // HTTPS only in production
'http_only' => true, // Prevent JavaScript access
'same_site' => 'lax', // CSRF protection
```

### Session Regeneration

Sessions are regenerated on login to prevent session fixation:
```php
session()->regenerate();
```

### Session Storage

- **Development**: File-based storage
- **Production**: Database or Redis (recommended)
  - Set `SESSION_DRIVER=redis` in `.env`
  - Configure Redis connection in `config/database.php`

## Best Practices

### Environment Configuration

1. **Never commit `.env` files** to version control
2. **Use strong APP_KEY**: Generate with `php artisan key:generate`
3. **HTTPS only in production**: Set `APP_URL=https://your-domain.com`
4. **Debug mode**: Always `APP_DEBUG=false` in production

### API Keys and Secrets

1. Store in environment variables
2. Rotate regularly
3. Use different keys for staging/production
4. Never log sensitive data

**Example:**
```env
WORKOS_API_KEY=sk_live_...  # Production key
WORKOS_CLIENT_ID=client_... # Production client
```

### Logging

1. **Error logging**: Enabled via Laravel's logging system
2. **Sensitive data**: Never log passwords, tokens, or API keys
3. **Request logging**: Axios interceptors log requests in development only

**Axios logging** (`resources/js/lib/axios.ts`):
```typescript
if (import.meta.env.DEV) {
    // Only log in development
    console.log(`API Request: ${config.method?.toUpperCase()} ${config.url}`);
}
```

### Code Updates

1. **Keep dependencies updated**: Run `composer update` and `npm update` regularly
2. **Security advisories**: Monitor Laravel and package security releases
3. **Vulnerability scanning**: Use `composer audit` to check for known vulnerabilities

### Deployment Checklist

Before deploying to production:

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] HTTPS enabled
- [ ] Strong `APP_KEY` generated
- [ ] WorkOS production credentials configured
- [ ] Rate limiting configured appropriately
- [ ] Session driver set to `redis` or `database`
- [ ] CORS configured for production domain
- [ ] CSP headers configured (optional but recommended)
- [ ] Error logging to external service (e.g., Sentry, Bugsnag)
- [ ] Database credentials secured
- [ ] `.env` file permissions set to 600
- [ ] All `composer` and `npm` dependencies updated

### Incident Response

In case of security incident:

1. **Isolate**: Take affected systems offline if necessary
2. **Assess**: Determine scope and impact
3. **Rotate**: Change all API keys, passwords, and secrets
4. **Patch**: Apply security updates immediately
5. **Audit**: Review logs for unauthorized access
6. **Notify**: Inform affected users if data was compromised

### Contact

For security issues, contact: [your-security-email@example.com]

---

Last updated: 2025-01-09
