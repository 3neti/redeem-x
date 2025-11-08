# API-First Architecture Refactoring Plan

## Executive Summary

Transform redeem-x from a traditional server-rendered Inertia app to an **API-first architecture** where:
- All business logic exposed via REST API endpoints
- Frontend (Inertia/Vue) consumes API exclusively
- Mobile apps and third-party integrations can use same API
- Laravel Actions serve as API controllers
- Full authentication via Laravel Sanctum
- Comprehensive API documentation

## Current State Analysis

### Existing Architecture
```
┌─────────────────────────────────────────┐
│  Inertia/Vue Frontend (Web Only)       │
├─────────────────────────────────────────┤
│  Traditional Laravel Controllers        │
│  - RedeemController                     │
│  - VoucherController                    │
│  - TransactionController                │
│  - SettingsController                   │
├─────────────────────────────────────────┤
│  Actions (Internal Only)                │
│  - ProcessRedemption                    │
│  - ValidateVoucherCode (unused)         │
│  - DisbursePayment (stub)               │
│  - SendFeedback (stub)                  │
├─────────────────────────────────────────┤
│  Package Actions (lbhurtado/voucher)    │
│  - GenerateVouchers                     │
│  - RedeemVoucher                        │
└─────────────────────────────────────────┘
```

**Problems:**
- ❌ No API for mobile apps
- ❌ Business logic tied to web controllers
- ❌ Form submissions use Inertia POST (server-side)
- ❌ Actions exist but not exposed as endpoints
- ❌ No third-party integration capability
- ❌ Difficult to test business logic independently

### Dependencies
- ✅ **Laravel Sanctum** already installed (v4.2)
- ✅ **Lorisleiva Laravel Actions** available (`AsAction` trait in use)
- ❌ **lorisleiva/laravel-actions** package NOT installed (need to add)
- ✅ **Spatie Laravel Data** for DTOs
- ✅ **Inertia.js** for SSR

## Target Architecture

```
┌──────────────────────────────────────────────────────────┐
│  Clients                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ Inertia/Vue  │  │  Mobile App  │  │  3rd Party   │  │
│  │  (Web UI)    │  │   (iOS/And)  │  │  Webhooks    │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │
└─────────┼──────────────────┼──────────────────┼──────────┘
          │                  │                  │
          └──────────────────┴──────────────────┘
                             │
          ┌──────────────────▼──────────────────┐
          │     REST API (routes/api.php)       │
          │  - Laravel Sanctum Auth             │
          │  - Rate Limiting                    │
          │  - JSON Responses                   │
          └──────────────────┬──────────────────┘
                             │
          ┌──────────────────▼──────────────────┐
          │  API Controllers (Laravel Actions)  │
          │  ┌──────────────────────────────┐  │
          │  │ Voucher API                   │  │
          │  │  - Generate                   │  │
          │  │  - List                       │  │
          │  │  - Show                       │  │
          │  │  - Validate                   │  │
          │  ├──────────────────────────────┤  │
          │  │ Redemption API                │  │
          │  │  - Start                      │  │
          │  │  - Validate Code              │  │
          │  │  - Submit Wallet              │  │
          │  │  - Submit Plugin Data         │  │
          │  │  - Finalize                   │  │
          │  │  - Confirm                    │  │
          │  ├──────────────────────────────┤  │
          │  │ Transaction API               │  │
          │  │  - List                       │  │
          │  │  - Show                       │  │
          │  │  - Export                     │  │
          │  ├──────────────────────────────┤  │
          │  │ Settings API                  │  │
          │  │  - Get/Update Profile         │  │
          │  │  - Get/Update Wallet Config   │  │
          │  │  - Get/Update Preferences     │  │
          │  ├──────────────────────────────┤  │
          │  │ Contact API                   │  │
          │  │  - List                       │  │
          │  │  - Show                       │  │
          │  └──────────────────────────────┘  │
          └──────────────────┬──────────────────┘
                             │
          ┌──────────────────▼──────────────────┐
          │  Domain Actions                     │
          │  - ProcessRedemption                │
          │  - DisbursePayment                  │
          │  - SendFeedback                     │
          │  - ValidateVoucherCode              │
          └──────────────────┬──────────────────┘
                             │
          ┌──────────────────▼──────────────────┐
          │  Package Actions                    │
          │  - GenerateVouchers                 │
          │  - RedeemVoucher                    │
          └─────────────────────────────────────┘
```

## Implementation Plan

### Phase 1: Foundation Setup

#### 1.1 Install Laravel Actions Package
```bash
composer require lorisleiva/laravel-actions
php artisan vendor:publish --tag=laravel-actions-config
```

**Purpose**: Enable actions to be used as controllers, jobs, listeners, and commands.

#### 1.2 Configure Sanctum for API Authentication
- Update `config/sanctum.php`
- Add SPA stateful domains
- Configure token abilities and expiration
- Add Sanctum middleware to API routes

#### 1.3 Create API Routes File Structure
```
routes/
  ├── api.php              # Main API router
  ├── api/
  │   ├── vouchers.php     # Voucher API endpoints
  │   ├── redemption.php   # Redemption API endpoints
  │   ├── transactions.php # Transaction API endpoints
  │   ├── settings.php     # Settings API endpoints
  │   └── contacts.php     # Contact API endpoints
```

#### 1.4 Setup API Middleware Stack
- Rate limiting (60 requests/minute for authenticated, 10 for guest)
- JSON-only responses
- Sanctum auth:sanctum middleware
- Custom API versioning middleware (optional)

### Phase 2: API Endpoints Design

#### 2.1 Voucher Generation API
```
POST   /api/vouchers              - Generate vouchers
GET    /api/vouchers              - List user's vouchers (paginated)
GET    /api/vouchers/{voucher}    - Get voucher details
DELETE /api/vouchers/{voucher}    - Cancel voucher (if not redeemed)
```

**Request/Response:**
```typescript
// POST /api/vouchers
Request: {
  quantity: number
  amount: number
  currency: string
  instructions: {
    cash: {...}
    inputs?: {...}
    signature?: {...}
    rider?: {...}
    feedback?: {...}
  }
  expires_in_days?: number
  starts_at?: string (ISO 8601)
}

Response: {
  data: {
    count: number
    vouchers: VoucherData[]
    total_amount: number
    estimated_cost: number
  }
}
```

#### 2.2 Redemption API (Public - No Auth Required)
```
POST   /api/redeem/validate       - Validate voucher code
POST   /api/redeem/start          - Start redemption session
POST   /api/redeem/wallet         - Submit wallet info
POST   /api/redeem/plugin         - Submit plugin data
GET    /api/redeem/finalize       - Get finalize summary
POST   /api/redeem/confirm        - Execute redemption
GET    /api/redeem/status/{code}  - Check redemption status
```

**Stateless Approach:**
- Use signed temporary tokens for multi-step flow
- Each step returns a token for next step
- No server-side session storage
- Perfect for mobile apps

**Request/Response:**
```typescript
// POST /api/redeem/validate
Request: { code: string }
Response: {
  data: {
    valid: boolean
    voucher?: VoucherData
    token?: string  // Temporary signed token for redemption flow
    error?: string
  }
}

// POST /api/redeem/start
Request: {
  token: string   // From validate step
  mobile: string
  country: string
}
Response: {
  data: {
    token: string  // New token for next step
    next_step: 'wallet' | 'plugin' | 'finalize'
    required_plugins?: string[]
  }
}

// POST /api/redeem/wallet
Request: {
  token: string
  bank_code: string
  account_number: string
}
Response: {
  data: {
    token: string
    next_step: 'plugin' | 'finalize'
  }
}

// POST /api/redeem/confirm
Request: { token: string }
Response: {
  data: {
    success: boolean
    voucher: VoucherData
    redeemer: ContactData
    transaction_id?: string
  }
}
```

#### 2.3 Transaction API (Authenticated)
```
GET    /api/transactions          - List user's transactions
GET    /api/transactions/{id}     - Get transaction details
GET    /api/transactions/export   - Export CSV/Excel
GET    /api/transactions/stats    - Get summary statistics
```

#### 2.4 Settings API (Authenticated)
```
GET    /api/settings/profile      - Get user profile
PATCH  /api/settings/profile      - Update user profile
GET    /api/settings/wallet       - Get wallet configuration
PATCH  /api/settings/wallet       - Update wallet config
GET    /api/settings/preferences  - Get preferences
PATCH  /api/settings/preferences  - Update preferences
```

#### 2.5 Contact API (Authenticated)
```
GET    /api/contacts              - List user's contacts
GET    /api/contacts/{contact}    - Get contact details
GET    /api/contacts/{contact}/vouchers - Get contact's vouchers
```

#### 2.6 Webhook API (Public with Signature Verification)
```
POST   /api/webhooks/payment      - Payment gateway callbacks
POST   /api/webhooks/sms          - SMS delivery status
```

### Phase 3: Implement Actions as API Controllers

#### 3.1 Refactor Existing Actions
Transform actions to support `asController` method:

**Example: `ProcessRedemption` Action**
```php
<?php

namespace App\Actions\Voucher;

use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;

class ProcessRedemption
{
    use AsAction;

    // Handle as controller (API endpoint)
    public function asController(ActionRequest $request)
    {
        $result = $this->handle(
            $request->get('voucher'),
            $request->get('phone_number'),
            $request->get('inputs', []),
            $request->get('bank_account', [])
        );

        return response()->json([
            'data' => [
                'success' => $result,
                'voucher' => VoucherData::fromModel($request->get('voucher')),
            ]
        ]);
    }

    // Handle as job (background processing)
    public function asJob(Voucher $voucher, PhoneNumber $phoneNumber, array $inputs, array $bankAccount)
    {
        return $this->handle($voucher, $phoneNumber, $inputs, $bankAccount);
    }

    // Core business logic
    public function handle(Voucher $voucher, PhoneNumber $phoneNumber, array $inputs, array $bankAccount): bool
    {
        // Existing implementation...
    }

    // Validation rules
    public function rules(): array
    {
        return [
            'voucher' => 'required',
            'phone_number' => 'required|string',
            'inputs' => 'array',
            'bank_account' => 'required|array',
            'bank_account.bank_code' => 'required|string',
            'bank_account.account_number' => 'required|string',
        ];
    }
}
```

#### 3.2 Create New API Actions

**Structure:**
```
app/Actions/Api/
  ├── Vouchers/
  │   ├── GenerateVouchers.php      (asController + asJob)
  │   ├── ListVouchers.php          (asController)
  │   ├── ShowVoucher.php           (asController)
  │   └── ValidateVoucherCode.php   (refactor existing)
  ├── Redemption/
  │   ├── StartRedemption.php
  │   ├── ValidateRedemptionCode.php
  │   ├── SubmitWallet.php
  │   ├── SubmitPlugin.php
  │   ├── FinalizeRedemption.php
  │   └── ConfirmRedemption.php
  ├── Transactions/
  │   ├── ListTransactions.php
  │   ├── ShowTransaction.php
  │   ├── ExportTransactions.php
  │   └── GetTransactionStats.php
  ├── Settings/
  │   ├── GetProfile.php
  │   ├── UpdateProfile.php
  │   ├── GetWalletConfig.php
  │   ├── UpdateWalletConfig.php
  │   ├── GetPreferences.php
  │   └── UpdatePreferences.php
  └── Contacts/
      ├── ListContacts.php
      ├── ShowContact.php
      └── GetContactVouchers.php
```

### Phase 4: Update Frontend to Use API

#### 4.1 Create API Client Service
```typescript
// resources/js/services/api.ts
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // For Sanctum SPA auth
})

// Add CSRF token to all requests
api.interceptors.request.use((config) => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (token) {
    config.headers['X-CSRF-TOKEN'] = token
  }
  return config
})

// API methods
export const voucherApi = {
  generate: (data) => api.post('/vouchers', data),
  list: (params) => api.get('/vouchers', { params }),
  show: (id) => api.get(`/vouchers/${id}`),
}

export const redemptionApi = {
  validate: (code) => api.post('/redeem/validate', { code }),
  start: (data) => api.post('/redeem/start', data),
  submitWallet: (data) => api.post('/redeem/wallet', data),
  submitPlugin: (data) => api.post('/redeem/plugin', data),
  finalize: (token) => api.get('/redeem/finalize', { params: { token } }),
  confirm: (token) => api.post('/redeem/confirm', { token }),
}

export const transactionApi = {
  list: (params) => api.get('/transactions', { params }),
  show: (id) => api.get(`/transactions/${id}`),
  export: (params) => api.get('/transactions/export', { params, responseType: 'blob' }),
}

export const settingsApi = {
  getProfile: () => api.get('/settings/profile'),
  updateProfile: (data) => api.patch('/settings/profile', data),
  getWallet: () => api.get('/settings/wallet'),
  updateWallet: (data) => api.patch('/settings/wallet', data),
}
```

#### 4.2 Create Composables for API Integration
```typescript
// resources/js/composables/useVouchers.ts
import { ref } from 'vue'
import { voucherApi } from '@/services/api'
import { router } from '@inertiajs/vue3'

export function useVouchers() {
  const loading = ref(false)
  const error = ref(null)

  const generateVouchers = async (data) => {
    loading.value = true
    error.value = null
    try {
      const response = await voucherApi.generate(data)
      // Optionally navigate to success page
      router.visit(`/vouchers/generate/success/${response.data.data.count}`)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to generate vouchers'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    generateVouchers,
  }
}
```

#### 4.3 Update Vue Components
```vue
<!-- resources/js/pages/Vouchers/Generate/Create.vue -->
<script setup lang="ts">
import { useVouchers } from '@/composables/useVouchers'

const { loading, error, generateVouchers } = useVouchers()

const handleSubmit = async (formData) => {
  await generateVouchers(formData)
}
</script>
```

**Keep Inertia for:**
- Initial page loads
- Navigation between pages
- SSR benefits

**Use API for:**
- Form submissions
- Data mutations
- Real-time updates
- Background operations

### Phase 5: Authentication & Security

#### 5.1 Sanctum SPA Authentication
```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort()
))),

'guard' => ['web'],
```

#### 5.2 API Token Authentication (for mobile/third-party)
```php
// Add to User model
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    // Create tokens with abilities
    public function createApiToken(string $name, array $abilities = ['*'])
    {
        return $this->createToken($name, $abilities);
    }
}

// Token abilities
$token = $user->createToken('mobile-app', [
    'voucher:generate',
    'voucher:list',
    'voucher:view',
    'transaction:list',
    'transaction:view',
]);
```

#### 5.3 Rate Limiting
```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    // Authenticated API routes
});

Route::middleware(['throttle:10,1'])->group(function () {
    // Public redemption API routes
});
```

#### 5.4 API Response Format
Standardize all API responses:

```php
// app/Http/Resources/ApiResource.php
class ApiResource extends JsonResource
{
    public function with($request)
    {
        return [
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ];
    }
}

// Success response
{
  "data": { ... },
  "meta": {
    "timestamp": "2025-11-08T15:18:29Z",
    "version": "v1"
  }
}

// Error response
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["The code field is required."]
  },
  "meta": {
    "timestamp": "2025-11-08T15:18:29Z",
    "version": "v1"
  }
}
```

### Phase 6: Testing

#### 6.1 API Test Structure
```
tests/Feature/Api/
  ├── Vouchers/
  │   ├── GenerateVouchersTest.php
  │   ├── ListVouchersTest.php
  │   └── ValidateVoucherCodeTest.php
  ├── Redemption/
  │   ├── RedemptionFlowTest.php
  │   ├── ValidateCodeTest.php
  │   └── ConfirmRedemptionTest.php
  ├── Transactions/
  │   └── TransactionApiTest.php
  ├── Settings/
  │   └── SettingsApiTest.php
  └── Auth/
      └── SanctumAuthTest.php
```

#### 6.2 Example API Test
```php
<?php

namespace Tests\Feature\Api\Vouchers;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class GenerateVouchersTest extends TestCase
{
    public function test_authenticated_user_can_generate_vouchers(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/vouchers', [
            'quantity' => 10,
            'amount' => 100,
            'currency' => 'PHP',
            'instructions' => [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'count',
                    'vouchers',
                    'total_amount',
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_generate_vouchers(): void
    {
        $response = $this->postJson('/api/vouchers', [
            'quantity' => 10,
            'amount' => 100,
        ]);

        $response->assertStatus(401);
    }
}
```

### Phase 7: Documentation

#### 7.1 Install API Documentation Tools
```bash
composer require --dev darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

#### 7.2 Add OpenAPI Annotations
```php
/**
 * @OA\Post(
 *     path="/api/vouchers",
 *     summary="Generate vouchers",
 *     tags={"Vouchers"},
 *     security={{"sanctum": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"quantity", "amount", "currency", "instructions"},
 *             @OA\Property(property="quantity", type="integer", example=10),
 *             @OA\Property(property="amount", type="number", example=100),
 *             @OA\Property(property="currency", type="string", example="PHP"),
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Vouchers generated successfully",
 *     )
 * )
 */
```

#### 7.3 Generate Documentation
```bash
php artisan l5-swagger:generate
```

Access at: `http://localhost:8000/api/documentation`

## Migration Strategy

### Approach: Gradual Migration (Not Big Bang)

#### Step 1: Add API alongside existing web routes
- Keep web routes working
- Add API routes in parallel
- No breaking changes

#### Step 2: Update one feature at a time
1. Start with **Voucher Generation** (least complex)
2. Then **Transaction Listing** (read-only)
3. Then **Settings** (simple CRUD)
4. Then **Redemption Flow** (most complex)

#### Step 3: Feature flags for gradual rollout
```php
// config/features.php
return [
    'api_voucher_generation' => env('FEATURE_API_VOUCHER_GENERATION', false),
    'api_redemption' => env('FEATURE_API_REDEMPTION', false),
];

// In Vue component
if (useFeature('api_voucher_generation')) {
    // Use API call
} else {
    // Use Inertia form
}
```

## Success Metrics

- ✅ All business logic accessible via API
- ✅ 100% test coverage for API endpoints
- ✅ Frontend uses API for all mutations
- ✅ Complete API documentation
- ✅ Mobile app can authenticate and use API
- ✅ Rate limiting and security in place
- ✅ Performance: API responses < 200ms (p95)

## Timeline Estimate

| Phase | Duration | Effort |
|-------|----------|--------|
| 1. Foundation Setup | 1 day | Install packages, configure Sanctum |
| 2. API Design | 1 day | Define endpoints, request/response schemas |
| 3. Voucher API | 2 days | Implement + tests |
| 4. Transaction API | 1 day | Implement + tests |
| 5. Settings API | 1 day | Implement + tests |
| 6. Redemption API | 3 days | Complex flow + tests |
| 7. Frontend Migration | 3 days | Update all components to use API |
| 8. Testing & QA | 2 days | Integration tests, E2E tests |
| 9. Documentation | 1 day | OpenAPI docs, examples |
| **Total** | **15 days** | **~3 weeks** |

## Next Steps

1. **Review and approve this plan**
2. **Install lorisleiva/laravel-actions package**
3. **Start with Phase 1: Foundation Setup**
4. **Implement Voucher Generation API first** (proof of concept)
5. **Iterate based on feedback**

## Questions to Answer

1. **API Versioning**: Do we want `/api/v1/` prefix now or later?
2. **Mobile App Timeline**: When do you plan to start mobile development?
3. **Third-party integrations**: Any specific partners to support?
4. **Background jobs**: Should redemption be async (queued)?
5. **Webhooks**: What events should trigger webhooks?

---

**Ready to start? Let me know if you want to:**
- Proceed with Phase 1 implementation
- Modify any part of this plan
- Start with a specific feature as proof of concept
