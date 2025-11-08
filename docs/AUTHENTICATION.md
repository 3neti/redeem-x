# Authentication Architecture: WorkOS + Sanctum

**Project**: Redeem-X  
**Date**: 2025-11-08  
**Status**: Design Phase

---

## 🔐 Overview

Redeem-X uses a **hybrid authentication strategy** combining two authentication systems:

1. **WorkOS AuthKit** - For web application session-based authentication
2. **Laravel Sanctum** - For API token-based authentication

This approach provides the best of both worlds: secure SSO for web users and flexible token authentication for API consumers.

---

## 🏗️ Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      Redeem-X Application                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │              Web Routes (Inertia.js)                       │ │
│  │  ┌─────────────────────────────────────────────────────┐  │ │
│  │  │  WorkOS AuthKit (Session-Based)                     │  │ │
│  │  │  - OAuth/OIDC flow                                  │  │ │
│  │  │  - Laravel session cookies                          │  │ │
│  │  │  - ValidateSessionWithWorkOS middleware            │  │ │
│  │  │  - SSO support (Google, Microsoft, etc.)           │  │ │
│  │  └─────────────────────────────────────────────────────┘  │ │
│  │                                                             │ │
│  │  Routes: /dashboard, /vouchers, /wallet, /settings        │ │
│  │  Middleware: ['auth', ValidateSessionWithWorkOS::class]    │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │              API Routes (REST)                             │ │
│  │  ┌─────────────────────────────────────────────────────┐  │ │
│  │  │  Laravel Sanctum (Token-Based)                      │  │ │
│  │  │  - Personal access tokens                           │  │ │
│  │  │  - Bearer token authentication                      │  │ │
│  │  │  - Token scopes/abilities                           │  │ │
│  │  │  - Token expiration                                 │  │ │
│  │  └─────────────────────────────────────────────────────┘  │ │
│  │                                                             │ │
│  │  Routes: /api/v1/*                                         │ │
│  │  Middleware: ['auth:sanctum']                              │ │
│  │  Consumers: Mobile apps, partner integrations, CLI tools   │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Use Cases

### WorkOS AuthKit (Web)
✅ **Perfect for:**
- Main web application (Inertia.js SPA)
- Dashboard, voucher management, wallet UI
- User profile and settings pages
- SSO enterprise login requirements
- Session-based authentication with cookies

❌ **Not suitable for:**
- Mobile applications
- Third-party integrations
- Programmatic API access
- CLI tools or scripts

### Laravel Sanctum (API)
✅ **Perfect for:**
- Mobile applications (iOS, Android)
- Partner API integrations
- Third-party developer access
- CLI tools and scripts
- Webhooks and automation
- Microservices communication

❌ **Not suitable for:**
- Primary web application authentication
- When you need SSO features

---

## 📦 Installation & Setup

### 1. Install Dependencies

```bash
cd /Users/rli/PhpstormProjects/redeem-x

# WorkOS already installed ✅
composer require laravel/workos

# Install Sanctum
composer require laravel/sanctum
```

### 2. Publish Sanctum Configuration

```bash
# Publish Sanctum config and migrations
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run Sanctum migrations
php artisan migrate
```

### 3. Configure Sanctum

**File**: `config/sanctum.php`

```php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,redeem-x.test')),
    
    'expiration' => 525600, // 1 year in minutes
    
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

### 4. Update User Model

**File**: `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens; // Add this trait

    protected $fillable = [
        'name',
        'email',
    ];

    protected $hidden = [
        'remember_token',
    ];
}
```

### 5. Configure API Routes

**File**: `bootstrap/app.php`

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // Ensure API routes are loaded
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // ... rest of configuration
```

---

## 🛣️ Route Configuration

### Web Routes (WorkOS)

**File**: `routes/web.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

// Public routes
Route::get('/', fn () => Inertia::render('Welcome'));

// Authenticated web routes (WorkOS session)
Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    
    // Vouchers
    Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
    
    // Wallet
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    
    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens');
    });
});

require __DIR__.'/auth.php'; // WorkOS auth routes
```

### API Routes (Sanctum)

**File**: `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1;

// Public API endpoints (if any)
Route::prefix('v1')->group(function () {
    // Health check
    Route::get('health', fn () => ['status' => 'ok']);
});

// Protected API endpoints (Sanctum token required)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    // Authentication
    Route::get('me', [V1\AuthController::class, 'me']);
    Route::delete('auth/token', [V1\AuthController::class, 'revokeToken']);
    
    // Vouchers
    Route::apiResource('vouchers', V1\VoucherController::class);
    Route::post('vouchers/{voucher}/redeem', [V1\VoucherRedeemController::class, 'store']);
    
    // Wallet
    Route::prefix('wallet')->group(function () {
        Route::get('balance', [V1\WalletController::class, 'balance']);
        Route::post('topup', [V1\WalletController::class, 'topup']);
        Route::get('transactions', [V1\WalletController::class, 'transactions']);
    });
    
    // Payments
    Route::post('payments/disburse', [V1\PaymentController::class, 'disburse']);
    
    // Audit
    Route::get('audit', [V1\AuditController::class, 'index']);
});
```

---

## 💻 Implementation Examples

### 1. Issue API Token (Web UI)

Users authenticate via WorkOS, then can generate API tokens from the settings page.

**Controller**: `app/Http/Controllers/Settings/ApiTokenController.php`

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * Show API tokens management page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('settings/ApiTokens', [
            'tokens' => $request->user()->tokens,
        ]);
    }

    /**
     * Create a new API token.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'array',
        ]);

        $token = $request->user()->createToken(
            $validated['name'],
            $validated['abilities'] ?? ['*']
        );

        return back()->with([
            'token' => $token->plainTextToken,
            'message' => 'API token created successfully. Make sure to copy it now!',
        ]);
    }

    /**
     * Revoke an API token.
     */
    public function destroy(Request $request, $tokenId)
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('message', 'API token revoked successfully.');
    }
}
```

### 2. API Token Authentication

**Controller**: `app/Http/Controllers/Api/V1/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
            'token_abilities' => $request->user()->currentAccessToken()->abilities ?? [],
        ]);
    }

    /**
     * Revoke the current API token.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }
}
```

### 3. API Controller with Sanctum

**Controller**: `app/Http/Controllers/Api/V1/VoucherController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Models\Voucher;

class VoucherController extends Controller
{
    /**
     * List all vouchers for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $vouchers = $request->user()
            ->vouchers()
            ->latest()
            ->paginate(20);

        return response()->json($vouchers);
    }

    /**
     * Create a new voucher.
     */
    public function store(Request $request): JsonResponse
    {
        // Check token abilities
        if (!$request->user()->tokenCan('voucher:create')) {
            return response()->json([
                'message' => 'Token does not have permission to create vouchers'
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'recipient' => 'required|string',
        ]);

        $voucher = $request->user()->vouchers()->create($validated);

        return response()->json($voucher, 201);
    }

    /**
     * Get voucher details.
     */
    public function show(Voucher $voucher): JsonResponse
    {
        $this->authorize('view', $voucher);

        return response()->json($voucher);
    }
}
```

---

## 🔑 Token Abilities (Scopes)

Define granular permissions for API tokens.

### Available Abilities

```php
'voucher:create'     // Create vouchers
'voucher:read'       // View vouchers
'voucher:redeem'     // Redeem vouchers
'voucher:cancel'     // Cancel vouchers
'wallet:read'        // View wallet balance
'wallet:topup'       // Top up wallet
'wallet:transfer'    // Transfer funds
'payment:disburse'   // Disburse payments
'audit:read'         // View audit logs
'*'                  // Full access (wildcard)
```

### Creating Token with Specific Abilities

```php
// Create token with limited abilities
$token = $user->createToken('Mobile App', [
    'voucher:create',
    'voucher:read',
    'voucher:redeem',
    'wallet:read',
]);

// Create token with full access
$token = $user->createToken('Admin Token', ['*']);
```

### Checking Token Abilities in Controllers

```php
public function store(Request $request)
{
    if (!$request->user()->tokenCan('voucher:create')) {
        abort(403, 'Insufficient permissions');
    }
    
    // Proceed with creation
}
```

---

## 🌐 Frontend Integration

### Web App (Inertia.js) - WorkOS

No special handling needed! WorkOS manages sessions automatically.

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { edit, update } from '@/actions/App/Http/Controllers/Settings/ProfileController'

const updateProfile = (data) => {
  router.patch(update.url(), data) // WorkOS session handles auth
}
</script>
```

### API Consumers - Sanctum

Include the Bearer token in the Authorization header.

**Example: cURL**
```bash
curl -X GET https://redeem-x.test/api/v1/vouchers \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

**Example: JavaScript (fetch)**
```javascript
const token = 'YOUR_API_TOKEN_HERE'

fetch('http://redeem-x.test/api/v1/vouchers', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
  }
})
.then(response => response.json())
.then(data => console.log(data))
```

**Example: JavaScript (axios)**
```javascript
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://redeem-x.test/api/v1',
  headers: {
    'Accept': 'application/json',
  }
})

// Set token
api.defaults.headers.common['Authorization'] = `Bearer ${token}`

// Make request
const { data } = await api.get('/vouchers')
```

---

## 🧪 Testing

### Test WorkOS Authentication

```php
use Tests\TestCase;
use App\Models\User;

class DashboardTest extends TestCase
{
    public function test_authenticated_users_can_access_dashboard()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }
}
```

### Test Sanctum API Authentication

```php
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class VoucherApiTest extends TestCase
{
    public function test_authenticated_users_can_list_vouchers()
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user, ['voucher:read']);

        $this->getJson('/api/v1/vouchers')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $this->getJson('/api/v1/vouchers')
            ->assertUnauthorized();
    }

    public function test_token_without_permission_is_forbidden()
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user, ['wallet:read']); // Wrong ability

        $this->postJson('/api/v1/vouchers', ['amount' => 100])
            ->assertForbidden();
    }
}
```

---

## 🔒 Security Best Practices

### WorkOS
1. ✅ Always use `ValidateSessionWithWorkOS` middleware on protected routes
2. ✅ Configure proper `WORKOS_REDIRECT_URL` in production
3. ✅ Enable HTTPS in production
4. ✅ Use WorkOS organizations for multi-tenant scenarios

### Sanctum
1. ✅ Use token abilities to limit permissions
2. ✅ Set reasonable token expiration times
3. ✅ Store tokens securely (never in localStorage)
4. ✅ Implement token rotation for long-lived applications
5. ✅ Rate limit API endpoints
6. ✅ Validate all incoming API requests
7. ✅ Use HTTPS in production
8. ✅ Never log tokens in production

### General
1. ✅ Implement rate limiting on both web and API routes
2. ✅ Use CORS properly for API routes
3. ✅ Log authentication attempts
4. ✅ Monitor for suspicious activity
5. ✅ Implement 2FA via WorkOS

---

## 📚 Reference Links

- [WorkOS Laravel Documentation](https://workos.com/docs/integrations/laravel)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [WorkOS AuthKit](https://workos.com/docs/authkit)
- [API Authentication Best Practices](https://laravel.com/docs/12.x/sanctum#spa-authentication)

---

## 🚀 Next Steps

### Phase 1: Setup
- [x] Install WorkOS package
- [ ] Install Sanctum package
- [ ] Run Sanctum migrations
- [ ] Update User model with HasApiTokens trait

### Phase 2: Implementation
- [ ] Create API token management UI (`settings/ApiTokens.vue`)
- [ ] Create API controllers with Sanctum authentication
- [ ] Define token abilities/scopes
- [ ] Add API documentation

### Phase 3: Testing
- [ ] Write WorkOS authentication tests
- [ ] Write Sanctum API tests
- [ ] Test token abilities
- [ ] Test token expiration

### Phase 4: Documentation
- [ ] Document API endpoints
- [ ] Create API authentication guide for partners
- [ ] Add examples for common API operations

---

**Last Updated**: 2025-11-08  
**Status**: Design Complete, Implementation Pending  
**Next Review**: After Phase 1 completion
