# 🚀 Phase 2: Backend API Development - Implementation Plan

**Date**: 2025-11-08  
**Status**: Ready to Begin  
**Based on**: x-change codebase analysis + improvements

---

## 🎯 Goals

Build the backend API controllers and actions following the VoucherInstructionsData-driven architecture from x-change, with these improvements:

1. **Better separation of concerns** - Use Actions (Lorisleiva) consistently
2. **Type safety** - Leverage DTOs throughout
3. **Resource classes** - Proper API responses with Laravel Resources
4. **Test coverage** - Test every endpoint (Pest PHP)
5. **Wayfinder integration** - Auto-generate TypeScript routes
6. **Modern Laravel** - Use Laravel 12 features

---

## 📦 Deliverables

### **1. Controllers** (with Inertia + Actions pattern)
- ✅ `VoucherController` - Generation & listing
- ✅ `RedeemController` - Start & confirm redemption
- ✅ `RedeemWizardController` - Multi-step redemption flow

### **2. Actions** (Lorisleiva Actions)
- ✅ `GenerateVouchers` (already in package)
- ✅ `RedeemVoucher` - Execute redemption
- ✅ `ValidateVoucherCode` - Check voucher validity
- ✅ `DisbursePayment` - Process payment via gateway
- ✅ `SendFeedback` - Email/SMS/webhook notifications

### **3. Form Requests** (with Spatie Data)
- ✅ `VoucherInstructionDataRequest` (copy from x-change, adapt)
- ✅ `WalletFormRequest` - Bank account collection
- ✅ `PluginFormRequest` - Dynamic plugin inputs

### **4. Support Classes**
- ✅ `RedeemPluginMap` - Plugin configuration mapping
- ✅ `RedeemPluginSelector` - Dynamic plugin selection
- ✅ `InputRuleBuilder` - Dynamic validation builder

### **5. Resources** (API responses)
- ✅ `VoucherResource` - Voucher JSON representation
- ✅ `VoucherCollection` - Paginated voucher list
- ✅ `InstructionsResource` - Instructions DTO → JSON

### **6. Routes**
- ✅ `routes/vouchers.php` - Generation routes
- ✅ `routes/redeem.php` - Redemption routes

### **7. Tests**
- ✅ Feature tests for all endpoints
- ✅ Action unit tests
- ✅ Integration tests for redemption flow

---

## 🏗️ Implementation Structure

```
app/
├── Actions/
│   ├── Voucher/
│   │   ├── ValidateVoucherCode.php      ⭐ NEW
│   │   └── RedeemVoucher.php            ⭐ NEW
│   ├── Payment/
│   │   └── DisbursePayment.php          ⭐ NEW
│   └── Notification/
│       └── SendFeedback.php              ⭐ NEW
│
├── Http/
│   ├── Controllers/
│   │   ├── Voucher/
│   │   │   └── VoucherController.php    ⭐ NEW
│   │   └── Redeem/
│   │       ├── RedeemController.php      ⭐ NEW
│   │       └── RedeemWizardController.php ⭐ NEW
│   │
│   ├── Requests/
│   │   ├── Voucher/
│   │   │   └── VoucherInstructionDataRequest.php  📋 COPY + ADAPT
│   │   └── Redeem/
│   │       ├── WalletFormRequest.php     ⭐ NEW
│   │       └── PluginFormRequest.php     ⭐ NEW
│   │
│   └── Resources/
│       └── Voucher/
│           ├── VoucherResource.php       ⭐ NEW
│           ├── VoucherCollection.php     ⭐ NEW
│           └── InstructionsResource.php  ⭐ NEW
│
├── Support/
│   ├── RedeemPluginMap.php              📋 COPY + IMPROVE
│   ├── RedeemPluginSelector.php         📋 COPY + IMPROVE
│   └── InputRuleBuilder.php             📋 COPY + IMPROVE
│
routes/
├── vouchers.php                          ⭐ NEW
└── redeem.php                            ⭐ NEW

tests/Feature/
├── Voucher/
│   ├── VoucherGenerationTest.php        ⭐ NEW
│   └── VoucherListingTest.php           ⭐ NEW
└── Redeem/
    ├── RedeemFlowTest.php               ⭐ NEW
    ├── PluginSelectionTest.php          ⭐ NEW
    └── PaymentDisbursementTest.php      ⭐ NEW
```

---

## 📋 Step-by-Step Implementation Plan

### **Step 1: Copy & Adapt Support Classes** ⏱️ 30 min

Copy from x-change and improve:

```php
// app/Support/RedeemPluginMap.php
// ✅ Add type hints
// ✅ Use readonly properties (PHP 8.3)
// ✅ Add return types
// ✅ Better documentation

class RedeemPluginMap
{
    public static function fieldsFor(string $plugin): array
    {
        return config("x-change.redeem.plugins.{$plugin}.fields", []);
    }
    
    public static function validationFor(string $plugin): array
    {
        return config("x-change.redeem.plugins.{$plugin}.validation", []);
    }
    
    public static function pageFor(string $plugin): string
    {
        return config("x-change.redeem.plugins.{$plugin}.page");
    }
}
```

**Files to create:**
- `app/Support/RedeemPluginMap.php`
- `app/Support/RedeemPluginSelector.php`
- `app/Support/InputRuleBuilder.php`

**Improvements:**
- ✅ Type safety (strict types, return types)
- ✅ PHPDoc for better IDE support
- ✅ Static analysis friendly (PHPStan level 9)

---

### **Step 2: Create Form Requests** ⏱️ 45 min

```php
// app/Http/Requests/Voucher/VoucherInstructionDataRequest.php
// Based on x-change but improved

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Spatie\LaravelData\WithData;

class VoucherInstructionDataRequest extends FormRequest
{
    use WithData;
    
    protected string $dataClass = VoucherInstructionsData::class;
    
    public function authorize(): bool
    {
        return auth()->check(); // WorkOS authenticated
    }
    
    public function rules(): array
    {
        return VoucherInstructionsData::rules();
    }
    
    public function defaults(): array
    {
        $userId = auth()->id();
        
        // Try to fetch last used instructions from cache
        return Cache::get("disburse.last_data.user:{$userId}", 
            VoucherInstructionsData::generateFromScratch()->toArray()
        );
    }
    
    public function messages(): array
    {
        return [
            'cash.amount.required' => 'Please specify the voucher amount.',
            'cash.amount.min' => 'Minimum amount is :min PHP.',
            'count.required' => 'Please specify how many vouchers to generate.',
        ];
    }
}
```

**Files to create:**
- `app/Http/Requests/Voucher/VoucherInstructionDataRequest.php`
- `app/Http/Requests/Redeem/WalletFormRequest.php`
- `app/Http/Requests/Redeem/PluginFormRequest.php`

**Improvements over x-change:**
- ✅ Better validation messages
- ✅ Type-safe defaults
- ✅ WorkOS auth check

---

### **Step 3: Create Actions** ⏱️ 1 hour

```php
// app/Actions/Voucher/ValidateVoucherCode.php

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;

class ValidateVoucherCode
{
    use AsAction;
    
    public function handle(string $code): array
    {
        $voucher = Voucher::where('code', strtoupper(trim($code)))->first();
        
        if (!$voucher) {
            return [
                'valid' => false,
                'error' => 'Voucher code not found.',
            ];
        }
        
        if ($voucher->isExpired()) {
            return [
                'valid' => false,
                'error' => 'This voucher has expired.',
            ];
        }
        
        if ($voucher->isRedeemed()) {
            return [
                'valid' => false,
                'error' => 'This voucher has already been redeemed.',
            ];
        }
        
        return [
            'valid' => true,
            'voucher' => $voucher,
        ];
    }
}
```

```php
// app/Actions/Voucher/RedeemVoucher.php

use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use App\Actions\Payment\DisbursePayment;
use App\Actions\Notification\SendFeedback;

class RedeemVoucher
{
    use AsAction;
    
    public function handle(Voucher $voucher, array $inputs, Contact $contact): bool
    {
        DB::beginTransaction();
        
        try {
            // 1. Mark voucher as redeemed
            $voucher->redeemBy($contact);
            
            // 2. Attach inputs to voucher
            foreach ($inputs as $field => $value) {
                $voucher->addInput($field, $value);
            }
            
            // 3. Disburse payment
            $payment = DisbursePayment::run($voucher, $contact);
            
            // 4. Send feedback notifications
            if ($voucher->instructions->feedback) {
                SendFeedback::run($voucher, $contact, $payment);
            }
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

**Files to create:**
- `app/Actions/Voucher/ValidateVoucherCode.php`
- `app/Actions/Voucher/RedeemVoucher.php`
- `app/Actions/Payment/DisbursePayment.php`
- `app/Actions/Notification/SendFeedback.php`

**Improvements:**
- ✅ Transaction safety
- ✅ Clear single responsibility
- ✅ Testable in isolation
- ✅ Better error handling

---

### **Step 4: Create Resources** ⏱️ 30 min

```php
// app/Http/Resources/Voucher/VoucherResource.php

use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'amount' => $this->instructions->cash->amount,
            'currency' => $this->instructions->cash->currency,
            'status' => $this->getStatus(), // NEW: expired, redeemed, active
            'starts_at' => $this->starts_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'redeemed_at' => $this->redeemed_at?->toISOString(),
            'instructions' => new InstructionsResource($this->instructions),
            'redeemer' => $this->when($this->contact, fn() => [
                'name' => $this->contact?->name,
                'mobile' => $this->contact?->mobile,
            ]),
        ];
    }
    
    protected function getStatus(): string
    {
        if ($this->isRedeemed()) return 'redeemed';
        if ($this->isExpired()) return 'expired';
        return 'active';
    }
}
```

**Files to create:**
- `app/Http/Resources/Voucher/VoucherResource.php`
- `app/Http/Resources/Voucher/VoucherCollection.php`
- `app/Http/Resources/Voucher/InstructionsResource.php`

**Improvements:**
- ✅ Consistent API responses
- ✅ ISO 8601 timestamps
- ✅ Computed status field
- ✅ Conditional attributes

---

### **Step 5: Create Controllers** ⏱️ 2 hours

```php
// app/Http/Controllers/Voucher/VoucherController.php

use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use App\Http\Requests\Voucher\VoucherInstructionDataRequest;
use App\Http\Resources\Voucher\{VoucherResource, VoucherCollection};

class VoucherController extends Controller
{
    public function index(): Response
    {
        $vouchers = auth()->user()
            ->vouchers()
            ->latest()
            ->paginate(20);
        
        return Inertia::render('Vouchers/Index', [
            'vouchers' => new VoucherCollection($vouchers),
        ]);
    }
    
    public function create(): Response
    {
        return Inertia::render('Vouchers/Create', [
            'defaults' => VoucherInstructionsData::generateFromScratch(),
            'pricing' => config('x-change.pricing'),
        ]);
    }
    
    public function store(VoucherInstructionDataRequest $request): RedirectResponse
    {
        $instructions = $request->getData();
        
        $vouchers = GenerateVouchers::run($instructions);
        
        // Cache for next generation
        Cache::put(
            "disburse.last_data.user:" . auth()->id(),
            $instructions->toArray(),
            now()->addDays(7)
        );
        
        return redirect()
            ->route('vouchers.show', $vouchers->first())
            ->with('success', "Generated {$vouchers->count()} voucher(s) successfully!");
    }
    
    public function show(Voucher $voucher): Response
    {
        $this->authorize('view', $voucher);
        
        return Inertia::render('Vouchers/Show', [
            'voucher' => new VoucherResource($voucher),
        ]);
    }
}
```

```php
// app/Http/Controllers/Redeem/RedeemWizardController.php

use Inertia\Inertia;
use Inertia\Response;
use App\Support\{RedeemPluginSelector, RedeemPluginMap};

class RedeemWizardController extends Controller
{
    public function wallet(Voucher $voucher): Response
    {
        // Step 1: Collect bank account
        return Inertia::render('Redeem/Wallet', [
            'voucher' => $voucher->code,
            'default_mobile' => session('redeem.mobile'),
        ]);
    }
    
    public function storeWallet(WalletFormRequest $request, Voucher $voucher): RedirectResponse
    {
        // Store in session
        session()->put('redeem.wallet', $request->validated());
        session()->put('redeem.mobile', $request->mobile);
        
        // Determine next plugin
        $plugins = RedeemPluginSelector::fromVoucher($voucher);
        $nextPlugin = $plugins[0] ?? null;
        
        if (!$nextPlugin) {
            return redirect()->route('redeem.finalize', $voucher);
        }
        
        return redirect()->route('redeem.plugin', [
            'voucher' => $voucher,
            'plugin' => $nextPlugin,
        ]);
    }
    
    public function plugin(Voucher $voucher, string $plugin): Response
    {
        // Dynamic plugin rendering
        $pluginConfig = config("x-change.redeem.plugins.{$plugin}");
        
        if (!$pluginConfig || !$pluginConfig['enabled']) {
            abort(404, "Plugin '{$plugin}' not found or disabled.");
        }
        
        $pluginFields = RedeemPluginMap::fieldsFor($plugin);
        $voucherFields = $voucher->instructions->inputs->fields;
        
        // Only show fields required by THIS voucher
        $requestedFields = array_intersect($pluginFields, $voucherFields);
        
        // Pre-fill from contact if mobile exists
        $defaultValues = [];
        if ($mobile = session('redeem.mobile')) {
            $contact = Contact::firstOrCreate(['mobile' => $mobile]);
            $defaultValues = $contact->getMeta()->toArray();
        }
        
        return Inertia::render($pluginConfig['page'], [
            'voucher' => $voucher->code,
            'plugin' => $plugin,
            'requestedFields' => $requestedFields,
            'defaultValues' => $defaultValues,
        ]);
    }
    
    public function storePlugin(
        PluginFormRequest $request, 
        Voucher $voucher, 
        string $plugin
    ): RedirectResponse 
    {
        // Store plugin data in session
        $sessionKey = config("x-change.redeem.plugins.{$plugin}.session_key");
        session()->put("redeem.{$sessionKey}", $request->validated());
        
        // Determine next plugin
        $plugins = RedeemPluginSelector::fromVoucher($voucher);
        $currentIndex = array_search($plugin, $plugins);
        $nextPlugin = $plugins[$currentIndex + 1] ?? null;
        
        if (!$nextPlugin) {
            return redirect()->route('redeem.finalize', $voucher);
        }
        
        return redirect()->route('redeem.plugin', [
            'voucher' => $voucher,
            'plugin' => $nextPlugin,
        ]);
    }
    
    public function finalize(Voucher $voucher): Response
    {
        // Review all collected data
        $wallet = session('redeem.wallet');
        $inputs = session('redeem.inputs', []);
        $signature = session('redeem.signature');
        
        return Inertia::render('Redeem/Finalize', [
            'voucher' => new VoucherResource($voucher),
            'wallet' => $wallet,
            'inputs' => $inputs,
            'signature' => $signature,
        ]);
    }
}
```

```php
// app/Http/Controllers/Redeem/RedeemController.php

use App\Actions\Voucher\{ValidateVoucherCode, RedeemVoucher};

class RedeemController extends Controller
{
    public function start(): Response
    {
        return Inertia::render('Redeem/Start');
    }
    
    public function confirm(Voucher $voucher): RedirectResponse
    {
        // Gather all session data
        $wallet = session('redeem.wallet');
        $inputs = array_merge(
            session('redeem.inputs', []),
            session('redeem.signature', [])
        );
        
        $mobile = session('redeem.mobile');
        $contact = Contact::firstOrCreate(['mobile' => $mobile]);
        
        // Execute redemption
        RedeemVoucher::run($voucher, $inputs, $contact);
        
        // Clear session
        session()->forget(['redeem.wallet', 'redeem.inputs', 'redeem.signature']);
        
        return redirect()
            ->route('redeem.success', $voucher)
            ->with('success', 'Voucher redeemed successfully!');
    }
    
    public function success(Voucher $voucher): Response
    {
        $this->authorize('viewRedemption', $voucher);
        
        return Inertia::render('Redeem/Success', [
            'voucher' => new VoucherResource($voucher),
            'rider' => [
                'message' => $voucher->instructions->rider->message,
                'url' => $voucher->instructions->rider->url,
            ],
        ]);
    }
}
```

**Files to create:**
- `app/Http/Controllers/Voucher/VoucherController.php`
- `app/Http/Controllers/Redeem/RedeemController.php`
- `app/Http/Controllers/Redeem/RedeemWizardController.php`

**Improvements:**
- ✅ Authorization checks
- ✅ Flash messages
- ✅ Resource responses
- ✅ Wayfinder-compatible routes
- ✅ Better session management

---

### **Step 6: Create Routes** ⏱️ 15 min

```php
// routes/vouchers.php

use App\Http\Controllers\Voucher\VoucherController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('vouchers', VoucherController::class)
        ->only(['index', 'create', 'store', 'show']);
});
```

```php
// routes/redeem.php

use App\Http\Controllers\Redeem\{RedeemController, RedeemWizardController};

Route::prefix('redeem')->name('redeem.')->group(function () {
    
    // Start redemption (no auth required)
    Route::get('/', [RedeemController::class, 'start'])->name('start');
    
    // Scoped to specific voucher
    Route::prefix('{voucher:code}')->group(function () {
        
        // Wallet collection
        Route::get('/wallet', [RedeemWizardController::class, 'wallet'])->name('wallet');
        Route::post('/wallet', [RedeemWizardController::class, 'storeWallet'])->name('wallet.store');
        
        // Dynamic plugin flow
        Route::get('/{plugin}', [RedeemWizardController::class, 'plugin'])->name('plugin');
        Route::post('/{plugin}', [RedeemWizardController::class, 'storePlugin'])->name('plugin.store');
        
        // Finalize & confirm
        Route::get('/finalize', [RedeemWizardController::class, 'finalize'])->name('finalize');
        Route::post('/confirm', [RedeemController::class, 'confirm'])->name('confirm');
        
        // Success page
        Route::get('/success', [RedeemController::class, 'success'])->name('success');
    });
});
```

**Files to create:**
- `routes/vouchers.php`
- `routes/redeem.php`

**Register in `bootstrap/app.php`:**
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware('web')->group(base_path('routes/vouchers.php'));
        Route::middleware('web')->group(base_path('routes/redeem.php'));
        Route::middleware('web')->group(base_path('routes/auth.php'));
        Route::middleware('web')->group(base_path('routes/settings.php'));
    }
)
```

**Improvements:**
- ✅ Grouped by domain
- ✅ Named routes for Wayfinder
- ✅ Route model binding with `code`
- ✅ Clear middleware boundaries

---

### **Step 7: Write Tests** ⏱️ 2 hours

```php
// tests/Feature/Voucher/VoucherGenerationTest.php

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

test('authenticated user can access voucher generation form', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get(route('vouchers.create'));
    
    $response->assertOk();
    $response->assertInertia(fn($page) => 
        $page->component('Vouchers/Create')
            ->has('defaults')
            ->has('pricing')
    );
});

test('user can generate vouchers with instructions', function () {
    $user = User::factory()->create();
    
    $instructions = VoucherInstructionsData::generateFromScratch([
        'count' => 5,
        'prefix' => 'TEST',
    ]);
    
    $response = $this->actingAs($user)
        ->post(route('vouchers.store'), $instructions->toArray());
    
    $response->assertRedirect();
    $response->assertSessionHas('success');
    
    expect(Voucher::where('owner_id', $user->id)->count())->toBe(5);
    expect(Voucher::first()->code)->toStartWith('TEST');
});

test('generated vouchers have instructions in metadata', function () {
    $user = User::factory()->create();
    
    $instructions = VoucherInstructionsData::generateFromScratch();
    
    $this->actingAs($user)
        ->post(route('vouchers.store'), $instructions->toArray());
    
    $voucher = Voucher::first();
    
    expect($voucher->instructions)->toBeInstanceOf(VoucherInstructionsData::class);
    expect($voucher->instructions->cash->amount)->toBe(500.0);
});
```

```php
// tests/Feature/Redeem/RedeemFlowTest.php

test('guest can start redemption with valid code', function () {
    $voucher = Voucher::factory()->create();
    
    $response = $this->get(route('redeem.wallet', $voucher));
    
    $response->assertOk();
    $response->assertInertia(fn($page) => 
        $page->component('Redeem/Wallet')
            ->where('voucher', $voucher->code)
    );
});

test('redemption flow follows plugin selection', function () {
    $instructions = VoucherInstructionsData::generateFromScratch([
        'inputs' => ['fields' => [VoucherInputField::NAME, VoucherInputField::EMAIL]],
    ]);
    
    $voucher = Voucher::factory()->withInstructions($instructions)->create();
    
    // Submit wallet
    $response = $this->post(route('redeem.wallet.store', $voucher), [
        'mobile' => '+639171234567',
        'bank_code' => 'BDO',
        'account_number' => '1234567890',
    ]);
    
    // Should redirect to inputs plugin (not signature)
    $response->assertRedirect(route('redeem.plugin', [
        'voucher' => $voucher,
        'plugin' => 'inputs',
    ]));
});

test('voucher can be redeemed successfully', function () {
    $voucher = Voucher::factory()->create();
    
    // Setup session data
    session([
        'redeem.wallet' => ['mobile' => '+639171234567', 'bank_code' => 'BDO'],
        'redeem.inputs' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        'redeem.mobile' => '+639171234567',
    ]);
    
    $response = $this->post(route('redeem.confirm', $voucher));
    
    $response->assertRedirect(route('redeem.success', $voucher));
    
    expect($voucher->fresh()->isRedeemed())->toBeTrue();
});
```

**Files to create:**
- `tests/Feature/Voucher/VoucherGenerationTest.php`
- `tests/Feature/Voucher/VoucherListingTest.php`
- `tests/Feature/Redeem/RedeemFlowTest.php`
- `tests/Feature/Redeem/PluginSelectionTest.php`
- `tests/Feature/Redeem/PaymentDisbursementTest.php`

---

## 🎯 Key Improvements Over x-change

| Aspect | x-change | redeem-x (Improved) |
|--------|----------|---------------------|
| **Actions** | Mixed in controllers | Isolated, testable actions |
| **Resources** | Array responses | Laravel Resources |
| **Type Safety** | Minimal | Full PHP 8.3 types |
| **Testing** | Sparse | Comprehensive Pest tests |
| **Validation** | Rules in request | DTOs with rules() |
| **API Responses** | Inconsistent | Standardized Resources |
| **Error Handling** | Try-catch scattered | Centralized in actions |
| **Session Management** | Direct session calls | Structured session keys |
| **Frontend Routes** | Manual strings | Wayfinder auto-generated |

---

## ✅ Acceptance Criteria

Before moving to Phase 3:

- [ ] All controllers created and working
- [ ] All actions isolated and tested
- [ ] All routes registered and accessible
- [ ] All tests passing (aim for 50+ tests)
- [ ] Wayfinder routes generated
- [ ] No breaking changes to lbhurtado packages
- [ ] Documentation updated

---

## 📊 Estimated Timeline

- **Step 1** (Support Classes): 30 min
- **Step 2** (Form Requests): 45 min
- **Step 3** (Actions): 1 hour
- **Step 4** (Resources): 30 min
- **Step 5** (Controllers): 2 hours
- **Step 6** (Routes): 15 min
- **Step 7** (Tests): 2 hours

**Total: ~7 hours of focused development**

---

## 🚀 Ready to Begin?

Shall we start with Step 1 (Support Classes)? Or would you like to adjust the approach?
