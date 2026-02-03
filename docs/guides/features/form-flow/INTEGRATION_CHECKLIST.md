# Form Flow Integration Checklist

**Version**: 1.0  
**Last Updated**: 2026-02-03  
**Package**: `3neti/form-flow` v1.7+

## Purpose

This checklist provides a step-by-step guide for integrating the form-flow system into a new or existing Laravel application. Follow these steps in order for a successful setup.

**Time Estimate**: 30-45 minutes for basic integration

---

## Prerequisites

- [ ] PHP 8.2 or higher installed
- [ ] Laravel 11+ application running
- [ ] Composer 2.x installed
- [ ] Node.js 18+ for frontend builds
- [ ] Basic understanding of Laravel, Inertia.js, and Vue 3

---

## Phase 1: Package Installation

### 1.1 Install Core Package

```bash
# Install form-flow-manager core
composer require 3neti/form-flow
```

**Verify**: Check `composer.json` contains `"3neti/form-flow": "^1.7"`

- [ ] Package installed successfully
- [ ] `composer.json` updated

### 1.2 Install Handler Plugins

```bash
# Install optional handlers (choose what you need)
composer require 3neti/form-handler-location    # GPS + geocoding
composer require 3neti/form-handler-selfie      # Camera capture
composer require 3neti/form-handler-signature   # Digital signature
composer require 3neti/form-handler-kyc         # Identity verification
composer require 3neti/form-handler-otp         # SMS verification
```

**Verify**: Check `composer.json` contains desired handlers

- [ ] Location handler installed (if needed)
- [ ] Selfie handler installed (if needed)
- [ ] Signature handler installed (if needed)
- [ ] KYC handler installed (if needed)
- [ ] OTP handler installed (if needed)

### 1.3 Publish Configuration

```bash
# Publish form-flow config
php artisan vendor:publish --tag=form-flow-config

# Publish handler configs (if installed)
php artisan vendor:publish --tag=location-handler-config
php artisan vendor:publish --tag=selfie-handler-config
php artisan vendor:publish --tag=signature-handler-config
php artisan vendor:publish --tag=kyc-handler-config
php artisan vendor:publish --tag=otp-handler-config
```

**Verify**: Check `config/` directory contains:
- `config/form-flow.php`
- `config/location-handler.php` (if installed)
- `config/selfie-handler.php` (if installed)
- etc.

- [ ] Core config published
- [ ] Handler configs published

### 1.4 Publish Frontend Assets

```bash
# Publish Vue components for all handlers
php artisan vendor:publish --tag=form-flow-stubs
php artisan vendor:publish --tag=location-handler-stubs
php artisan vendor:publish --tag=selfie-handler-stubs
php artisan vendor:publish --tag=signature-handler-stubs
php artisan vendor:publish --tag=kyc-handler-stubs
php artisan vendor:publish --tag=otp-handler-stubs
```

**Verify**: Check `resources/js/pages/form-flow/` contains:
- `form-flow/generic/` (core)
- `form-flow/splash/` (core)
- `form-flow/location/` (if installed)
- `form-flow/selfie/` (if installed)
- etc.

- [ ] Core Vue components published
- [ ] Handler Vue components published

---

## Phase 2: Environment Configuration

### 2.1 Add Environment Variables

Edit `.env` and add:

```bash
# Form Flow Configuration
FORM_FLOW_ROUTE_PREFIX=form-flow
FORM_FLOW_MIDDLEWARE=web
FORM_FLOW_DRIVER_DIRECTORY=config/form-flow-drivers
FORM_FLOW_SESSION_PREFIX=form_flow
```

**Optional handler-specific variables**:

```bash
# Location Handler (OpenCage Geocoding)
OPENCAGE_API_KEY=your_api_key_here

# KYC Handler (HyperVerge)
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=your_app_id
HYPERVERGE_APP_KEY=your_app_key

# OTP Handler (EngageSpark)
ENGAGESPARK_API_KEY=your_api_key
ENGAGESPARK_ORG_ID=your_org_id
```

- [ ] Core form-flow variables added
- [ ] Handler-specific variables added (if needed)
- [ ] API keys configured (if needed)

### 2.2 Update .env.example

Copy the variables you added to `.env.example` for team members:

```bash
# Form Flow Configuration
FORM_FLOW_ROUTE_PREFIX=form-flow
FORM_FLOW_MIDDLEWARE=web
# ... etc
```

- [ ] `.env.example` updated with form-flow variables

### 2.3 Clear Configuration Cache

```bash
php artisan config:clear
php artisan config:cache
```

- [ ] Configuration cache cleared

---

## Phase 3: Create Your First Driver

### 3.1 Create Driver Directory

```bash
mkdir -p config/form-flow-drivers
```

- [ ] Driver directory created

### 3.2 Create Simple Test Driver

Create `config/form-flow-drivers/simple-test.yaml`:

```yaml
# Simple 2-step test flow
driver_name: "simple-test"
driver_version: "1.0"

# Steps configuration
steps:
  # Step 1: Splash screen
  splash:
    handler: "splash"
    step_name: "welcome"
    config:
      title: "Welcome!"
      description: "This is a test form flow"
      button_text: "Start"
      
  # Step 2: Collect name
  collect_name:
    handler: "form"
    step_name: "user_info"
    config:
      title: "What's your name?"
      fields:
        - name: "name"
          type: "text"
          label: "Full Name"
          required: true
          validation:
            - "required"
            - "string"
            - "max:255"
```

- [ ] Simple test driver created
- [ ] YAML syntax verified (use YAML linter if available)

---

## Phase 4: Test the Flow

### 4.1 Create Test Route

Add to `routes/web.php`:

```php
use Illuminate\Support\Facades\Route;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

Route::get('/test-form-flow', function (DriverService $driverService, FormFlowService $formFlowService) {
    // Load driver
    $instructions = $driverService->loadDriver('simple-test', [
        'reference_id' => 'test-' . time(),
    ]);
    
    // Start flow
    $state = $formFlowService->startFlow($instructions);
    
    // Redirect to flow
    return redirect("/form-flow/{$state['flow_id']}");
});

// Callback endpoint (receives data after completion)
Route::post('/test-form-flow/callback', function (Request $request) {
    Log::info('Form flow completed', $request->all());
    
    return response()->json([
        'success' => true,
        'message' => 'Data received successfully'
    ]);
});
```

- [ ] Test route created
- [ ] Callback endpoint created

### 4.2 Update Driver with Callback

Edit `config/form-flow-drivers/simple-test.yaml` and add:

```yaml
# Callbacks (after steps configuration)
callbacks:
  on_complete: "{{ app_url }}/test-form-flow/callback"
  on_cancel: "{{ app_url }}"
```

- [ ] Callback URL added to driver

### 4.3 Run Test Flow

```bash
# Start dev server (if not already running)
php artisan serve

# In another terminal, start Vite
npm run dev
```

Open browser: `http://localhost:8000/test-form-flow`

**Expected behavior**:
1. Redirects to `/form-flow/{flow_id}`
2. Shows splash screen with "Welcome!" message
3. Click "Start" → Shows name input form
4. Submit name → Redirects to complete page
5. Callback receives data

- [ ] Splash screen displays correctly
- [ ] Name form displays correctly
- [ ] Form submission works
- [ ] Callback receives data
- [ ] Flow completes without errors

### 4.4 Verify Session Data

Add to your test route:

```php
Route::get('/test-form-flow/debug/{flow_id}', function ($flowId, FormFlowService $service) {
    $state = $service->getFlowState($flowId);
    
    return response()->json($state);
});
```

Visit: `http://localhost:8000/test-form-flow/debug/{flow_id}`

**Expected JSON structure**:
```json
{
  "flow_id": "abc123",
  "reference_id": "test-1234567890",
  "current_step": 2,
  "status": "completed",
  "collected_data": {
    "welcome": {},
    "user_info": {
      "name": "John Doe"
    }
  }
}
```

- [ ] Session data stores correctly
- [ ] `collected_data` contains submitted values

---

## Phase 5: Production Integration

### 5.1 Create Production Driver

For a real use case (e.g., voucher redemption), create:

`config/form-flow-drivers/voucher-redemption.yaml`

See [INTEGRATION.md](./INTEGRATION.md) Section 4 for complete driver reference.

- [ ] Production driver created
- [ ] All required steps configured
- [ ] Conditional logic tested (if applicable)
- [ ] Template variables validated

### 5.2 Implement Production Routes

```php
// routes/web.php
Route::get('/redeem/{code}', [RedeemController::class, 'start'])->name('redeem.start');
Route::post('/redeem/{code}/complete', [RedeemController::class, 'complete'])->name('redeem.complete');
Route::post('/redeem/{code}/cancel', [RedeemController::class, 'cancel'])->name('redeem.cancel');
```

- [ ] Production routes created
- [ ] Route names follow convention
- [ ] CSRF protection enabled

### 5.3 Create Controller Integration

Example `RedeemController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LBHurtado\FormFlowManager\Services\{DriverService, FormFlowService};

class RedeemController extends Controller
{
    public function __construct(
        protected DriverService $driverService,
        protected FormFlowService $formFlowService
    ) {}
    
    public function start(string $code)
    {
        // Load voucher and build context
        $voucher = Voucher::whereCode($code)->firstOrFail();
        
        $context = [
            'reference_id' => "voucher-{$voucher->code}",
            'voucher' => $voucher,
            'amount' => $voucher->instructions['cash'] ?? 0,
            // ... other context variables
        ];
        
        // Load driver with context
        $instructions = $this->driverService->loadDriver('voucher-redemption', $context);
        
        // Start flow
        $state = $this->formFlowService->startFlow($instructions);
        
        // Redirect to flow
        return redirect("/form-flow/{$state['flow_id']}");
    }
    
    public function complete(string $code, Request $request)
    {
        $flowId = $request->input('flow_id');
        
        // Get collected data
        $state = $this->formFlowService->getFlowState($flowId);
        $collectedData = $state['collected_data'];
        
        // Map to your domain model
        $mappedData = $this->mapCollectedData($collectedData);
        
        // Process (e.g., redeem voucher)
        $voucher = Voucher::whereCode($code)->firstOrFail();
        $result = ProcessRedemption::run($voucher, $mappedData);
        
        // Clean up session
        $this->formFlowService->clearFlow($flowId);
        
        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }
    
    protected function mapCollectedData(array $collectedData): array
    {
        // Flatten step data into single array
        $flat = [];
        foreach ($collectedData as $stepData) {
            $flat = array_merge($flat, $stepData);
        }
        
        return $flat;
    }
}
```

- [ ] Controller created
- [ ] Start method loads driver with context
- [ ] Complete method maps collected data
- [ ] Session cleanup implemented

### 5.4 Create Data Mapper (Optional)

For complex mapping logic:

```php
// app/Services/InputFieldMapper.php
class InputFieldMapper
{
    public function mapToRedemptionData(array $collectedData): array
    {
        // Extract step data
        $walletInfo = $collectedData['wallet_info'] ?? [];
        $bioData = $collectedData['biometric_data'] ?? [];
        
        // Map to domain structure
        return [
            'mobile' => $walletInfo['mobile'] ?? null,
            'selfie' => $bioData['selfie'] ?? null,
            'signature' => $bioData['signature'] ?? null,
            // ... more mappings
        ];
    }
}
```

- [ ] Mapper service created (if needed)
- [ ] Mapping logic tested
- [ ] Edge cases handled (missing fields, etc.)

---

## Phase 6: Frontend Integration

### 6.1 Install Frontend Dependencies

```bash
# Install required npm packages (if not already installed)
npm install @inertiajs/vue3
npm install qrcode  # For location handler map snapshots
npm install signature_pad  # For signature handler
```

- [ ] Inertia.js installed
- [ ] Handler-specific packages installed

### 6.2 Verify Vite Configuration

Check `vite.config.js` includes form-flow pages:

```js
export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: [
        'resources/js/pages/**/*.vue',  // Includes form-flow pages
      ],
    }),
    vue(),
  ],
})
```

- [ ] Vite config includes Vue pages
- [ ] Hot reload configured

### 6.3 Build Frontend Assets

```bash
# Development build
npm run dev

# Or production build
npm run build
```

- [ ] Assets build successfully
- [ ] No TypeScript errors
- [ ] No Vue compilation errors

---

## Phase 7: Testing & Validation

### 7.1 Unit Test Driver Loading

```php
// tests/Feature/FormFlowDriverTest.php
it('loads voucher redemption driver', function () {
    $driverService = app(DriverService::class);
    
    $instructions = $driverService->loadDriver('voucher-redemption', [
        'reference_id' => 'test-123',
        'voucher' => (object)['code' => 'TEST'],
    ]);
    
    expect($instructions->reference_id)->toBe('test-123');
    expect($instructions->steps)->toHaveCount(7); // Adjust based on your driver
});
```

- [ ] Driver loading test passes
- [ ] Context variables render correctly
- [ ] Conditional steps work

### 7.2 Integration Test Complete Flow

```php
// tests/Feature/FormFlowIntegrationTest.php
it('completes voucher redemption flow', function () {
    $voucher = Voucher::factory()->create();
    
    // Start flow
    $response = $this->get("/redeem/{$voucher->code}");
    $response->assertRedirect(); // Redirects to form-flow
    
    // Extract flow_id from redirect
    $flowId = extractFlowIdFromUrl($response->headers->get('Location'));
    
    // Submit steps (simplified)
    $this->post("/form-flow/{$flowId}/step/0", ['data' => []]);
    $this->post("/form-flow/{$flowId}/step/1", ['data' => ['mobile' => '09171234567']]);
    // ... more steps
    
    // Complete flow
    $response = $this->post("/redeem/{$voucher->code}/complete", [
        'flow_id' => $flowId,
    ]);
    
    $response->assertJson(['success' => true]);
});
```

- [ ] Integration test passes
- [ ] All steps complete successfully
- [ ] Data persists correctly

### 7.3 Manual E2E Test

**Test scenario**: Complete a voucher redemption from start to finish

1. [ ] Visit redemption URL
2. [ ] Splash screen shows voucher details
3. [ ] Enter mobile number (wallet step)
4. [ ] Complete KYC (if enabled)
5. [ ] Enter OTP verification (if enabled)
6. [ ] Capture location (if enabled)
7. [ ] Take selfie (if enabled)
8. [ ] Sign signature (if enabled)
9. [ ] Review finalize page
10. [ ] Submit and receive success message
11. [ ] Verify data stored in database
12. [ ] Verify callback executed

### 7.4 Error Handling Test

Test error scenarios:

- [ ] Invalid voucher code → Shows error
- [ ] Session expires mid-flow → Graceful error
- [ ] Invalid step data → Validation errors display
- [ ] Network timeout → Retry mechanism works
- [ ] Callback failure → Error logged, user notified

---

## Phase 8: Production Deployment

### 8.1 Environment Variables

- [ ] Copy all `FORM_FLOW_*` variables to production `.env`
- [ ] Set production callback URLs
- [ ] Configure production API keys (OpenCage, HyperVerge, etc.)
- [ ] Verify `APP_URL` is correct

### 8.2 Build Production Assets

```bash
npm run build
```

- [ ] Production build completes
- [ ] Assets optimized (check file sizes)
- [ ] Source maps excluded (or configured correctly)

### 8.3 Deploy Code

```bash
# Example deployment steps
git add .
git commit -m "Add form-flow integration"
git push origin main

# On production server
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- [ ] Code deployed to production
- [ ] Dependencies installed
- [ ] Caches cleared and rebuilt

### 8.4 Verify Production

- [ ] Visit production URL
- [ ] Test complete flow end-to-end
- [ ] Check logs for errors
- [ ] Verify callbacks execute
- [ ] Monitor session storage

---

## Phase 9: Monitoring & Maintenance

### 9.1 Setup Logging

Add to `config/logging.php`:

```php
'channels' => [
    'form-flow' => [
        'driver' => 'daily',
        'path' => storage_path('logs/form-flow.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

Use in your code:

```php
Log::channel('form-flow')->info('Flow started', ['flow_id' => $flowId]);
```

- [ ] Form-flow log channel created
- [ ] Logging implemented in critical points
- [ ] Log rotation configured

### 9.2 Setup Monitoring

Monitor these metrics:

- [ ] Flow completion rate (started vs completed)
- [ ] Step abandonment rate (which step users drop off)
- [ ] Average flow duration
- [ ] Error rate by step
- [ ] Callback failure rate

### 9.3 Create Admin Dashboard (Optional)

```bash
php artisan make:controller Admin/FormFlowAnalyticsController
```

Display:
- Active flows count
- Completed flows count (last 24h, 7d, 30d)
- Most common errors
- Average completion time

- [ ] Analytics controller created (if needed)
- [ ] Dashboard displays metrics
- [ ] Real-time updates configured (optional)

---

## Troubleshooting Checklist

If something doesn't work, verify:

- [ ] All packages installed (`composer show 3neti/*`)
- [ ] Vue components published to `resources/js/pages/form-flow/`
- [ ] Config files published to `config/`
- [ ] Environment variables set correctly
- [ ] Configuration cache cleared (`php artisan config:clear`)
- [ ] Routes registered (`php artisan route:list | grep form-flow`)
- [ ] Handlers registered (`config('form-flow.handlers')`)
- [ ] YAML driver syntax valid (use online YAML validator)
- [ ] Frontend built (`npm run build` or `npm run dev`)
- [ ] Browser console shows no errors
- [ ] Session driver supports nested arrays (database/redis recommended)
- [ ] CSRF protection enabled on routes
- [ ] Callback URLs reachable from internet (if using webhooks)

**Common Issues**:
- **"Handler not found"** → Run `php artisan config:clear` and verify handler package installed
- **"CSRF token mismatch"** → Check middleware configuration in `config/form-flow.php`
- **"Session expired"** → Increase `SESSION_LIFETIME` in `.env` or extend session in driver config
- **"Vue component not found"** → Verify `vendor:publish --tag=*-stubs` ran successfully
- **"Template variable not rendering"** → Check context variable names match driver templates exactly

For detailed troubleshooting, see [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)

---

## Next Steps

After successful integration:

1. **Optimize Performance**: Add caching for driver loading, optimize Vue components
2. **Add Custom Handlers**: Create domain-specific handlers (see [HANDLERS.md](./HANDLERS.md))
3. **A/B Testing**: Test different driver configurations for better UX
4. **Analytics**: Track user behavior and optimize flow based on data
5. **Internationalization**: Add multi-language support to drivers
6. **Mobile Optimization**: Test and optimize for mobile devices

**Related Documentation**:
- [INTEGRATION.md](./INTEGRATION.md) - Complete integration guide
- [HANDLERS.md](./HANDLERS.md) - Handler development guide
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) - Common issues
- [README.md](./README.md) - Documentation index

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-03  
**Maintained By**: Development Team

## Completion Badge

Once you've completed all steps, you can claim you've successfully integrated form-flow! 🎉

**Your integration is complete when**:
- ✅ All Phase 1-8 checkboxes are checked
- ✅ E2E test passes in production
- ✅ Zero errors in production logs (first 24 hours)
- ✅ Callback success rate > 95%
