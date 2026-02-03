# Form Flow Troubleshooting Guide

**Version**: 1.0  
**Last Updated**: 2026-02-03  
**Package**: `3neti/form-flow` v1.7+

## Table of Contents

1. [Quick Diagnosis](#quick-diagnosis)
2. [Common Errors](#common-errors)
3. [Debugging Tools](#debugging-tools)
4. [Handler Issues](#handler-issues)
5. [YAML Driver Issues](#yaml-driver-issues)
6. [Session & State Issues](#session--state-issues)
7. [Frontend Issues](#frontend-issues)
8. [Callback & Webhook Issues](#callback--webhook-issues)
9. [Known Issues & Workarounds](#known-issues--workarounds)
10. [Getting Help](#getting-help)

---

## Quick Diagnosis

### Symptom Checklist

Use this checklist to quickly identify your issue category:

**Handler Problems**:
- [ ] Error message contains "Handler not found" or "Handler not registered"
- [ ] Custom handler not appearing in flow
- [ ] Handler executes but data not saved

**YAML Problems**:
- [ ] Error message contains "YAML parse error"
- [ ] Template variables showing as literal text (e.g., `{{ voucher.code }}`)
- [ ] Conditional steps not evaluating correctly
- [ ] Steps executing in wrong order

**Session Problems**:
- [ ] Error message contains "Session expired" or "Flow not found"
- [ ] Data lost between steps
- [ ] Cannot access collected data after completion

**Frontend Problems**:
- [ ] White screen / blank page
- [ ] Vue component not rendering
- [ ] Form submission does nothing
- [ ] JavaScript console errors

**Callback Problems**:
- [ ] Callback URL not receiving data
- [ ] "CSRF token mismatch" error
- [ ] Callback receives empty payload

---

## Common Errors

### Error 1: Handler Not Found

**Symptom**:
```
InvalidArgumentException: Handler [location] not registered.
```

**Cause**: Handler package not installed or service provider not loaded

**Solution**:
```bash
# 1. Verify package is installed
composer show 3neti/form-handler-location

# 2. Clear config cache
php artisan config:clear

# 3. Verify handler is registered
php artisan tinker
>>> config('form-flow.handlers')
# Should show: ["location" => "LBHurtado\LocationHandler\LocationHandler"]

# 4. If not registered, check service provider
composer dump-autoload
php artisan package:discover
```

**Prevention**:
- Always run `php artisan config:clear` after installing handler packages
- Verify `extra.laravel.providers` in handler's `composer.json`

---

### Error 2: CSRF Token Mismatch

**Symptom**:
```
TokenMismatchException in VerifyCsrfToken.php
```

**Cause**: Form submission missing CSRF token or middleware misconfigured

**Solution**:

**Option A: Verify middleware configuration**
```php
// config/form-flow.php
'middleware' => ['web'],  // Must include 'web' for CSRF protection
```

**Option B: Check Inertia configuration**
```js
// resources/js/app.js
import { createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
  resolve: name => resolvePageComponent(name, import.meta.glob('./pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
```

**Option C: Verify form submission includes CSRF**
```vue
<!-- Vue component -->
<script setup>
import { router } from '@inertiajs/vue3'

const submit = () => {
  // Inertia automatically includes CSRF token
  router.post(url, data)
}
</script>
```

---

### Error 3: Session Expired Mid-Flow

**Symptom**:
```
Session expired. Please start over.
```
or
```
Flow [abc123] not found.
```

**Cause**: Session timeout shorter than flow completion time

**Solution**:

**Option A: Increase session lifetime**
```bash
# .env
SESSION_LIFETIME=120  # Increase from default (e.g., 120 minutes)
```

**Option B: Extend session in driver config**
```yaml
# config/form-flow-drivers/voucher-redemption.yaml
config:
  session_timeout: 7200  # 2 hours in seconds
```

**Option C: Implement session keep-alive**
```js
// resources/js/pages/form-flow/location/LocationCapturePage.vue
import { onMounted, onUnmounted } from 'vue'

let keepAliveInterval

onMounted(() => {
  // Ping session every 5 minutes
  keepAliveInterval = setInterval(() => {
    fetch('/api/ping')
  }, 5 * 60 * 1000)
})

onUnmounted(() => {
  clearInterval(keepAliveInterval)
})
```

---

### Error 4: Vue Component Not Found

**Symptom**:
```
Inertia page not found: form-flow/location/LocationCapturePage
```

**Cause**: Vue component not published or path mismatch

**Solution**:
```bash
# 1. Re-publish handler stubs
php artisan vendor:publish --tag=location-handler-stubs --force

# 2. Verify file exists
ls -la resources/js/pages/form-flow/location/

# 3. Check Vite is building it
npm run dev
# Look for: "✓ built in XXXms" and check for errors

# 4. Verify Inertia path resolution
# Handler renders: Inertia::render('form-flow/location/LocationCapturePage', [...])
# File should be at: resources/js/pages/form-flow/location/LocationCapturePage.vue
```

---

### Error 5: Template Variable Not Rendering

**Symptom**:
YAML driver shows literal text instead of values:
```
Title displays: "Redeem {{ voucher.code }}"
Expected: "Redeem ABC123"
```

**Cause**: Context variable missing or misspelled

**Solution**:

**Step 1: Verify context variable exists**
```php
// In controller or wherever loadDriver is called
$context = [
    'voucher' => $voucherObject,  // NOT $voucher->toArray()
];

$instructions = $driverService->loadDriver('voucher-redemption', $context);
```

**Step 2: Check YAML template syntax**
```yaml
# CORRECT: Use dot notation for object properties
title: "Redeem {{ voucher.code }}"

# INCORRECT: Don't use array syntax
title: "Redeem {{ voucher['code'] }}"
```

**Step 3: Debug context in driver**
```yaml
# Add to YAML for debugging (temporary)
steps:
  debug:
    handler: "splash"
    step_name: "debug"
    config:
      title: "Debug Context"
      description: "Voucher: {{ voucher }}, Code: {{ voucher.code }}"
```

**Step 4: Check DriverService::processTemplates()**
```bash
# Add logging to see what's being rendered
php artisan tinker
>>> $service = app(LBHurtado\FormFlowManager\Services\DriverService::class);
>>> $result = $service->loadDriver('voucher-redemption', ['voucher' => (object)['code' => 'TEST']]);
>>> $result->steps[0]->config['title'];  // Should show "Redeem TEST"
```

---

### Error 6: Collected Data Not Persisting

**Symptom**:
Data submitted in step X is not available in step X+1 or in callback

**Cause**: Handler not returning data correctly or step_name mismatch

**Solution**:

**Step 1: Verify handler returns data**
```php
// In your handler's handle() method
public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    $validated = validator($inputData, [...])->validate();
    
    // MUST return array, not null or void
    return $validated;
}
```

**Step 2: Check step_name matches**
```yaml
# YAML driver
steps:
  wallet_step:  # YAML key (can be anything)
    handler: "form"
    step_name: "wallet_info"  # <-- This is the storage key
```

```php
// In callback/controller
$collectedData = $state['collected_data'];
$walletData = $collectedData['wallet_info'];  // Use step_name, not YAML key
```

**Step 3: Debug session directly**
```bash
php artisan tinker
>>> session('form_flow.abc123')
# Should show complete state with collected_data array
```

---

## Debugging Tools

### Tool 1: Session Inspector

Add this route for debugging (remove in production):

```php
// routes/web.php
Route::get('/debug/form-flow/{flow_id}', function ($flowId) {
    $state = session("form_flow.{$flowId}");
    
    return response()->json([
        'flow_id' => $flowId,
        'state' => $state,
        'all_flows' => collect(session()->all())
            ->filter(fn($val, $key) => str_starts_with($key, 'form_flow.'))
            ->toArray(),
    ]);
})->middleware('auth'); // Add auth in production!
```

**Usage**:
```bash
# Visit in browser
http://localhost:8000/debug/form-flow/{your_flow_id}
```

---

### Tool 2: YAML Validator

Test YAML syntax before deploying:

```bash
# Option 1: Use online validator
# Copy your YAML to: https://www.yamllint.com/

# Option 2: Use PHP YAML parser directly
php artisan tinker
>>> $yaml = file_get_contents(config_path('form-flow-drivers/voucher-redemption.yaml'));
>>> $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
>>> print_r($parsed);
# Should show array structure without errors
```

---

### Tool 3: Handler Registration Checker

Verify all handlers are registered:

```bash
php artisan tinker
>>> $handlers = config('form-flow.handlers');
>>> print_r($handlers);

# Expected output:
# Array
# (
#     [form] => LBHurtado\FormFlowManager\Handlers\FormHandler
#     [splash] => LBHurtado\FormFlowManager\Handlers\SplashHandler
#     [location] => LBHurtado\LocationHandler\LocationHandler
#     [selfie] => LBHurtado\SelfieHandler\SelfieHandler
#     ...
# )

# Verify specific handler is callable
>>> $handler = app($handlers['location']);
>>> $handler->getName();
# Should return: "location"
```

---

### Tool 4: Driver Context Tester

Test driver loading with sample context:

```bash
php artisan tinker
>>> $service = app(LBHurtado\FormFlowManager\Services\DriverService::class);
>>> $context = [
...     'reference_id' => 'test-123',
...     'voucher' => (object)['code' => 'TEST', 'amount' => 500],
...     'has_kyc' => true,
... ];
>>> $instructions = $service->loadDriver('voucher-redemption', $context);
>>> print_r($instructions->steps);
# Verify template variables are replaced
>>> $instructions->steps[0]->config['title'];
# Should show actual values, not {{ templates }}
```

---

### Tool 5: Frontend Console Logger

Add to Vue components for debugging:

```vue
<script setup>
import { watch } from 'vue'

const props = defineProps<{
  flow_id: string
  step: string
  config: any
}>()

// Log all props on mount and change
watch(() => props, (newProps) => {
  console.log('[FormFlow Debug]', {
    component: 'LocationCapturePage',
    props: newProps,
    timestamp: new Date().toISOString(),
  })
}, { immediate: true, deep: true })
</script>
```

---

## Handler Issues

### Issue: Custom Handler Not Executing

**Symptoms**:
- Handler appears registered but `handle()` never called
- Skips to next step without processing

**Diagnosis**:
```bash
# 1. Check handler name matches YAML
php artisan tinker
>>> config('form-flow.handlers')['myhandler']
# Should return handler class name

# 2. Verify YAML uses correct name
# config/form-flow-drivers/test.yaml
steps:
  my_step:
    handler: "myhandler"  # Must match getName() return value
```

**Solution**:
```php
// Ensure getName() returns exact string used in YAML
public function getName(): string
{
    return 'myhandler';  // NOT 'MyHandler' or 'my_handler'
}
```

---

### Issue: Handler Validation Failing Silently

**Symptoms**:
- Form submits but no error shown to user
- Redirects back to same step without message

**Solution**:

**Backend: Throw ValidationException**
```php
use Illuminate\Validation\ValidationException;

public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    $validated = validator($inputData, [
        'field' => 'required|string',
    ])->validate();  // <-- This throws ValidationException automatically
    
    return $validated;
}
```

**Frontend: Display Validation Errors**
```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  field: ''
})

const submit = () => {
  form.post(url, {
    onError: (errors) => {
      console.error('Validation errors:', errors)
      // errors will contain: { field: ['The field field is required.'] }
    }
  })
}
</script>

<template>
  <div v-if="form.errors.field" class="text-red-500">
    {{ form.errors.field }}
  </div>
</template>
```

---

## YAML Driver Issues

### Issue: Conditional Steps Not Working

**Symptoms**:
- Step always/never shows regardless of condition
- Condition evaluates incorrectly

**Example**:
```yaml
steps:
  kyc_step:
    handler: "kyc"
    step_name: "kyc_verification"
    condition: "{{ has_kyc }}"  # Not working
```

**Solution**:

**Option 1: Boolean conditions**
```yaml
# Use true/false directly (no quotes)
condition: {{ has_kyc }}  # Renders to: true or false
```

**Option 2: String comparison**
```yaml
# For string comparisons
condition: "{{ voucher.status }} == 'active'"
```

**Option 3: Null checks**
```yaml
# Check if variable exists
condition: "{{ voucher.kyc_required ?? false }}"
```

**Debugging**:
```bash
# Test condition rendering
php artisan tinker
>>> $service = app(LBHurtado\FormFlowManager\Services\DriverService::class);
>>> $instructions = $service->loadDriver('voucher-redemption', ['has_kyc' => true]);
>>> collect($instructions->steps)->pluck('condition');
# Should show: [true, null, true, ...]
```

---

### Issue: YAML Parse Error

**Symptom**:
```
ParseException: Unable to parse at line 42 (near "handler: location").
```

**Common Causes**:

**1. Indentation (spaces vs tabs)**
```yaml
# WRONG: Mixed tabs and spaces
steps:
	wallet:  # <-- Tab used here
    handler: "form"  # <-- Spaces used here

# CORRECT: Consistent spaces (2 or 4)
steps:
  wallet:
    handler: "form"
```

**2. Unquoted special characters**
```yaml
# WRONG: Colon in unquoted string
description: Error: Please try again

# CORRECT: Quote strings with colons
description: "Error: Please try again"
```

**3. Missing quotes on template variables**
```yaml
# WRONG: May cause issues if template renders to string with special chars
title: {{ voucher.code }}

# CORRECT: Always quote templates
title: "{{ voucher.code }}"
```

**Quick Fix**:
```bash
# Use YAML linter
npm install -g yaml-lint
yamllint config/form-flow-drivers/voucher-redemption.yaml
```

---

## Session & State Issues

### Issue: Session Data Lost on Redirect

**Symptoms**:
- Data persists during step submission
- Lost after redirect to next step

**Cause**: Session driver doesn't support nested arrays

**Solution**:

**Option 1: Switch to database session driver**
```bash
# .env
SESSION_DRIVER=database

# Create sessions table
php artisan session:table
php artisan migrate
```

**Option 2: Use Redis**
```bash
# .env
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Verification**:
```bash
php artisan tinker
>>> session(['test_nested' => ['a' => ['b' => 'c']]]);
>>> session('test_nested.a.b');
# Should return: "c"
# If returns null, session driver doesn't support nested arrays
```

---

### Issue: Multiple Flows Conflicting

**Symptoms**:
- User starts Flow A, then Flow B
- Flow A data appears in Flow B

**Cause**: Improper flow isolation

**Solution**:

**Ensure unique flow_id generation**
```php
// FormFlowService generates unique ID
$flowId = 'flow-' . Str::uuid();
```

**Verify session namespacing**
```bash
php artisan tinker
>>> session()->all();
# Should show:
# [
#   'form_flow.flow-abc123' => [...],
#   'form_flow.flow-def456' => [...],
# ]
# NOT:
# [
#   'form_flow' => [...],  // <-- BAD: No flow_id namespace
# ]
```

---

## Frontend Issues

### Issue: White Screen / Blank Page

**Diagnosis**:
```bash
# 1. Check browser console (F12)
# Look for errors like:
# - "Failed to fetch dynamically imported module"
# - "Uncaught SyntaxError"

# 2. Check Laravel logs
tail -f storage/logs/laravel.log

# 3. Check Vite is running
npm run dev
# Should show: "VITE vX.X.X ready in XXX ms"
```

**Solutions**:

**Option 1: Vite build error**
```bash
# Clear Vite cache
rm -rf node_modules/.vite
npm run dev
```

**Option 2: Missing import**
```vue
<!-- Check all imports are valid -->
<script setup>
// WRONG: Import doesn't exist
import { nonExistentFunction } from '@/utils'

// CORRECT: Verify import path
import { existingFunction } from '@/utils/helpers'
</script>
```

**Option 3: Inertia misconfiguration**
```js
// resources/js/app.js
import { createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
  // Ensure this resolves correctly
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.vue', { eager: true })
    return pages[`./pages/${name}.vue`]
  },
  // ...
})
```

---

### Issue: Form Submission Does Nothing

**Symptoms**:
- Click submit button
- No network request in DevTools
- No navigation occurs

**Diagnosis**:
```vue
<script setup>
import { router } from '@inertiajs/vue3'

const submit = () => {
  console.log('[Submit] Starting...')
  
  router.post(url, data, {
    onBefore: () => console.log('[Submit] Before send'),
    onStart: () => console.log('[Submit] Started'),
    onSuccess: () => console.log('[Submit] Success'),
    onError: (errors) => console.error('[Submit] Errors:', errors),
  })
}
</script>
```

**Common Causes**:

**1. Missing @submit.prevent**
```vue
<!-- WRONG: Form submits normally (page refresh) -->
<form @submit="submit">

<!-- CORRECT: Prevent default and use Inertia -->
<form @submit.prevent="submit">
```

**2. Button type not set**
```vue
<!-- WRONG: Button defaults to type="submit" in form -->
<button @click="submit">Submit</button>

<!-- CORRECT: Explicit type or use @submit -->
<button type="button" @click="submit">Submit</button>
<!-- OR -->
<form @submit.prevent="submit">
  <button type="submit">Submit</button>
</form>
```

**3. Disabled button**
```vue
<button :disabled="isSubmitting" type="submit">
  Submit
</button>

<script setup>
// Ensure isSubmitting is managed correctly
const isSubmitting = ref(false)

const submit = () => {
  if (isSubmitting.value) return  // Prevent double submission
  
  isSubmitting.value = true
  
  router.post(url, data, {
    onFinish: () => {
      isSubmitting.value = false  // Re-enable button
    }
  })
}
</script>
```

---

## Callback & Webhook Issues

### Issue: Callback URL Not Receiving Data

**Symptoms**:
- Flow completes successfully
- Callback endpoint never called

**Diagnosis**:

**Step 1: Verify callback URL in driver**
```yaml
callbacks:
  on_complete: "{{ app_url }}/redeem/{code}/complete"  # Check template renders
```

**Step 2: Check FormFlowController calls callback**
```bash
# Add logging to see if callback is attempted
tail -f storage/logs/laravel.log | grep -i callback
```

**Step 3: Test callback endpoint directly**
```bash
curl -X POST http://localhost:8000/redeem/TEST/complete \
  -H "Content-Type: application/json" \
  -d '{"flow_id": "test-123", "collected_data": {}}'
```

**Solutions**:

**Option 1: Ensure callback is defined in driver**
```yaml
# Must have on_complete or on_cancel
callbacks:
  on_complete: "{{ app_url }}/callback"
```

**Option 2: Verify callback URL is absolute**
```yaml
# WRONG: Relative URL may not work
on_complete: "/callback"

# CORRECT: Absolute URL
on_complete: "{{ app_url }}/callback"

# CORRECT: Hardcoded (for external webhooks)
on_complete: "https://api.external.com/webhook"
```

---

### Issue: Callback Receives Empty Payload

**Symptoms**:
- Callback executes
- `$request->all()` returns empty array or missing `collected_data`

**Solution**:

**Verify data is sent in request**
```php
// In FormFlowController (or wherever callback is triggered)
Http::post($callbackUrl, [
    'flow_id' => $flowId,
    'reference_id' => $state['reference_id'],
    'collected_data' => $state['collected_data'],  // <-- Include this
]);
```

**In callback endpoint**
```php
Route::post('/callback', function (Request $request) {
    Log::info('Callback received', [
        'all' => $request->all(),
        'flow_id' => $request->input('flow_id'),
        'collected_data' => $request->input('collected_data'),
    ]);
    
    // ...
});
```

---

## Known Issues & Workarounds

### Issue 1: settlement_rail Not Available in Driver Context

**Status**: Known limitation (as of v1.7)

**Problem**: `settlement_rail` field from voucher instructions not passed to driver context

**Workaround**:

**Option A: Hardcode in YAML (temporary)**
```yaml
# config/form-flow-drivers/voucher-redemption.yaml
steps:
  finalize:
    handler: "form"
    config:
      fields:
        - name: "settlement_rail"
          type: "hidden"
          value: "INSTAPAY"  # Hardcoded
```

**Option B: Add to context manually**
```php
// In DisburseController or wherever loadDriver is called
$context = [
    'voucher' => $voucher,
    'settlement_rail' => $voucher->instructions['settlement_rail'] ?? 'INSTAPAY',
];

$instructions = $driverService->loadDriver('voucher-redemption', $context);
```

**Option C: Use voucher.instructions template** (if settlement_rail is in instructions)
```yaml
fields:
  - name: "settlement_rail"
    type: "hidden"
    value: "{{ voucher.instructions.settlement_rail ?? 'INSTAPAY' }}"
```

**Future Fix**: Add `settlement_rail` to VoucherTemplateContextBuilder

---

### Issue 2: Session Timeout on Slow Networks

**Status**: Known issue with mobile users on slow connections

**Problem**: KYC/selfie upload times out session before completion

**Workaround**:

**Option A: Increase session lifetime for specific routes**
```php
// app/Http/Middleware/ExtendFormFlowSession.php
class ExtendFormFlowSession
{
    public function handle($request, Closure $next)
    {
        if (str_starts_with($request->path(), 'form-flow/')) {
            config(['session.lifetime' => 240]);  // 4 hours for form flows
        }
        
        return $next($request);
    }
}

// Register in Kernel.php or route middleware
```

**Option B: Implement progress save**
```js
// Save progress every step
const saveProgress = () => {
  localStorage.setItem(`form-flow-${flowId}`, JSON.stringify({
    step: currentStep,
    data: formData,
    timestamp: Date.now(),
  }))
}

// Restore on mount
onMounted(() => {
  const saved = localStorage.getItem(`form-flow-${flowId}`)
  if (saved) {
    const { step, data } = JSON.parse(saved)
    if (step === currentStep) {
      Object.assign(formData, data)
    }
  }
})
```

---

### Issue 3: Browser Back Button Breaks Flow

**Status**: Known UX issue

**Problem**: User clicks browser back during flow, session state becomes inconsistent

**Workaround**:

**Option A: Disable back button during flow**
```js
// resources/js/pages/form-flow/components/FlowWrapper.vue
import { onMounted, onUnmounted } from 'vue'

onMounted(() => {
  // Push state to prevent back navigation
  history.pushState(null, '', location.href)
  window.addEventListener('popstate', preventBack)
})

const preventBack = () => {
  history.pushState(null, '', location.href)
  alert('Please use the form navigation buttons to go back.')
}

onUnmounted(() => {
  window.removeEventListener('popstate', preventBack)
})
```

**Option B: Add "Previous" button in flow**
```php
// In FormFlowController, add support for going back
Route::post('/form-flow/{flow_id}/back', function ($flowId, FormFlowService $service) {
    $state = $service->getFlowState($flowId);
    $state['current_step'] = max(0, $state['current_step'] - 1);
    $service->updateFlowState($flowId, $state);
    
    return redirect("/form-flow/{$flowId}");
});
```

---

## Edge Cases & Testing

This section documents edge cases discovered through testing and their solutions.

### Edge Case 1: Missing Handler at Runtime

**Scenario**: Handler package was installed but removed/updated, leaving flows mid-execution.

**Symptoms**:
- Flow starts successfully
- Crashes when reaching step with missing handler
- Error: "Handler [location] not registered"

**Test**:
```php
// tests/Feature/FormFlowEdgeCasesTest.php
it('handles missing handler gracefully', function () {
    // Start flow with handler
    $instructions = FormFlowInstructionsData::from([
        'steps' => [
            ['handler' => 'nonexistent', 'config' => []]
        ],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->startFlow($instructions);
    
    // Visit flow URL
    $response = $this->get("/form-flow/{$state['flow_id']}");
    
    // Should render MissingHandler fallback, not crash
    $response->assertOk();
    $response->assertInertia(fn ($page) => 
        $page->component('form-flow/core/MissingHandler')
    );
});
```

**Solution**: Form-flow includes a `MissingHandler` fallback that displays installation instructions.

**Current Behavior**:
- `FormFlowController::show()` detects missing handler
- Falls back to `MissingHandler` instead of throwing exception
- User sees friendly message with `composer require` command

---

### Edge Case 2: Invalid YAML Syntax

**Scenario**: Developer edits YAML driver and introduces syntax error.

**Symptoms**:
- Exception: "ParseException: Unable to parse at line X"
- Flow fails to start

**Test**:
```bash
# Create intentionally broken YAML
echo 'steps:
  invalid:  # Missing handler field
    config: {}' > config/form-flow-drivers/broken.yaml

php artisan tinker
>>> $service = app(LBHurtado\FormFlowManager\Services\DriverService::class);
>>> $service->loadDriver('broken', ['reference_id' => 'test']);
# Should throw ParseException
```

**Prevention**:
```bash
# Add YAML validation to CI/CD
name: Validate YAML Drivers

steps:
  - name: Validate
    run: |
      php artisan tinker --execute="
        collect(glob(config_path('form-flow-drivers/*.yaml')))
          ->each(fn(\$f) => \Symfony\Component\Yaml\Yaml::parseFile(\$f));
      "
```

**Recovery**:
1. Check syntax at https://www.yamllint.com/
2. Look for: tabs instead of spaces, missing quotes, incorrect indentation
3. Validate locally: `php -r "echo yaml_parse_file('config/form-flow-drivers/driver.yaml') ? 'OK' : 'Invalid';"`

---

### Edge Case 3: Template Variable Type Mismatch

**Scenario**: Template expects string but receives object/array.

**Symptoms**:
- Title shows: "Welcome, Array"
- Or: "Catchable Fatal Error: Object of class ... could not be converted to string"

**Test**:
```yaml
# Broken YAML
title: "Welcome, {{ user }}"  # user is object, not string

# Correct YAML
title: "Welcome, {{ user.name }}"  # Explicitly access property
```

**Solution**:
```php
// In context building, ensure scalar types
$context = [
    'user_name' => $user->name,  // String, not object
    'amount' => (int) $voucher->amount,  // Int, not Money object
    'has_kyc' => (bool) $hasKyc,  // Boolean, not truthy value
];
```

---

### Edge Case 4: Session Expired Mid-Flow

**Scenario**: User takes too long (>2 hours default) to complete flow.

**Symptoms**:
- Error 404: "Flow not found"
- Session data cleared by garbage collection

**Test**:
```php
it('handles session expiration gracefully', function () {
    $service = app(FormFlowService::class);
    $state = $service->startFlow($instructions);
    $flowId = $state['flow_id'];
    
    // Simulate session expiration
    session()->forget("form_flow.{$flowId}");
    
    // Try to access flow
    $response = $this->get("/form-flow/{$flowId}");
    
    // Should redirect with error, not crash
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Session expired');
});
```

**Recovery**:
```php
// app/Http/Controllers/FormFlowController.php
public function show(string $flowId)
{
    $state = $this->flowService->getFlowState($flowId);
    
    if (!$state) {
        return redirect('/')->with('error', 'Session expired. Please start over.');
    }
    
    // ... continue
}
```

**Prevention**:
- Increase `SESSION_LIFETIME` for form flows
- Implement session keep-alive pings
- Store progress in database for long flows

---

### Edge Case 5: Callback URL Unreachable

**Scenario**: Callback URL is down, network error, or firewall blocks request.

**Symptoms**:
- Flow completes but callback never executes
- Timeout waiting for callback response
- Data not processed in host application

**Test**:
```php
it('handles callback failures gracefully', function () {
    $instructions = FormFlowInstructionsData::from([
        'steps' => [/* ... */],
        'callbacks' => [
            'on_complete' => 'http://invalid-domain-that-does-not-exist.com/callback',
        ],
    ]);
    
    // Complete flow
    $service->completeFlow($flowId);
    
    // Should log error but not crash
    // Check logs contain "Callback failed"
    expect(file_get_contents(storage_path('logs/laravel.log')))
        ->toContain('Callback failed');
});
```

**Current Behavior**:
```php
// FormFlowController::show() when completing
try {
    Http::timeout(30)->post($callbackUrl, $data);
} catch (\Exception $e) {
    Log::error('[FormFlow] Callback failed', [
        'url' => $callbackUrl,
        'error' => $e->getMessage(),
    ]);
    // Continue anyway - don't block user
}
```

**Best Practice**:
- Implement retry logic in callback handler
- Use queue for asynchronous callbacks
- Store callback payload in database as backup

---

### Edge Case 6: Concurrent Flow Instances

**Scenario**: User opens two browser tabs and starts same voucher redemption twice.

**Symptoms**:
- Two separate flow_ids for same reference_id
- Data collected in Tab A doesn't appear in Tab B
- One flow completes, the other is abandoned

**Test**:
```php
it('handles concurrent flows for same reference', function () {
    $referenceId = 'voucher-TEST';
    
    // Start flow in "Tab A"
    $stateA = $service->startFlow($instructionsA);
    
    // Start flow in "Tab B" (same reference)
    $stateB = $service->startFlow($instructionsB);
    
    // Both flows exist
    expect($service->flowExists($stateA['flow_id']))->toBeTrue();
    expect($service->flowExists($stateB['flow_id']))->toBeTrue();
    
    // Different flow_ids
    expect($stateA['flow_id'])->not->toBe($stateB['flow_id']);
});
```

**Impact**: Last-completed flow wins. If user finishes in Tab A, then finishes in Tab B, Tab B data overwrites Tab A.

**Mitigation**:
```php
// Check if reference already has active flow
$existingFlow = $service->getFlowStateByReference($referenceId);
if ($existingFlow && $existingFlow['status'] === 'active') {
    // Redirect to existing flow instead of creating new one
    return redirect("/form-flow/{$existingFlow['flow_id']}");
}
```

---

### Edge Case 7: Large Collected Data Exceeds Callback Size

**Scenario**: Multiple large base64 images (selfie + signature + location map) exceed HTTP POST limit.

**Symptoms**:
- Callback receives 413 Payload Too Large
- Or 500 error if POST limit exceeded
- Data never reaches callback handler

**Test**:
```php
$largeData = [
    'selfie' => str_repeat('A', 5 * 1024 * 1024),  // 5MB base64
    'signature' => str_repeat('B', 2 * 1024 * 1024),  // 2MB
    'location_map' => str_repeat('C', 3 * 1024 * 1024),  // 3MB
];
// Total: ~10MB payload

Http::post($callbackUrl, ['collected_data' => $largeData]);
// May fail depending on server limits
```

**Solutions**:

**Option A: Store Media Separately**
```php
// Before callback, upload media to storage
foreach ($collectedData as $stepIndex => $data) {
    if (isset($data['selfie'])) {
        $url = $this->uploadToS3($data['selfie']);
        $collectedData[$stepIndex]['selfie_url'] = $url;
        unset($collectedData[$stepIndex]['selfie']);  // Remove large base64
    }
}

// Send smaller payload with URLs
Http::post($callbackUrl, ['collected_data' => $collectedData]);
```

**Option B: Increase Server Limits**
```ini
; php.ini
post_max_size = 50M
upload_max_filesize = 50M
```

---

### Edge Case 8: Conditional Step Causes Empty Flow

**Scenario**: All conditional steps evaluate to false, resulting in 0 steps.

**Symptoms**:
- Flow starts but immediately completes
- No data collected
- User sees "Flow completed" without any input

**Test**:
```yaml
# All steps have failing conditions
steps:
  kyc:
    condition: "{{ has_kyc }}"  # False
  location:
    condition: "{{ has_location }}"  # False
# No steps render!
```

**Detection**:
```php
// After DriverService::processSteps()
if (empty($steps)) {
    throw new \RuntimeException(
        "Driver '{$driverName}' resulted in 0 steps. Check conditions."
    );
}
```

**Prevention**: Always include at least one unconditional step (e.g., splash or confirmation).

---

## Testing Checklist

Before deploying to production, test these scenarios:

### Happy Path
- [ ] User completes all steps successfully
- [ ] Callback receives correct data
- [ ] Voucher marked as redeemed
- [ ] Session cleaned up after completion

### Error Cases
- [ ] Invalid voucher code → Error message
- [ ] Already redeemed voucher → Error message
- [ ] Missing handler → Fallback UI shown
- [ ] Invalid YAML driver → Exception caught
- [ ] Session expires → Redirect with message
- [ ] Callback fails → Error logged, user not blocked

### Edge Cases
- [ ] Concurrent tabs → Last completion wins or detect conflict
- [ ] Large media files → Upload to storage, not session
- [ ] All conditional steps false → At least splash renders
- [ ] Template variable null → Fallback to default (`?? ''`)
- [ ] Browser back button → State remains consistent or back disabled

### Performance
- [ ] Flow with 10+ steps completes in <30s
- [ ] Session size stays under 4KB (or use database driver)
- [ ] Callback timeout set (30s recommended)
- [ ] No N+1 queries during flow execution

---

## Getting Help

### Before Asking for Help

Complete this checklist:

- [ ] Checked [Common Errors](#common-errors) section
- [ ] Ran all [Debugging Tools](#debugging-tools)
- [ ] Verified handler registration
- [ ] Validated YAML syntax
- [ ] Checked browser console for errors
- [ ] Checked Laravel logs for errors
- [ ] Tested with simple 2-step flow
- [ ] Cleared all caches (`config:clear`, `route:clear`, `view:clear`)

### Information to Provide

When seeking help, include:

1. **Error Message**: Complete stack trace
2. **Environment**: PHP version, Laravel version, package versions
3. **YAML Driver**: Sanitized version of your driver file
4. **Code Snippet**: Relevant controller/handler code
5. **Steps to Reproduce**: Minimal example to reproduce issue
6. **What You've Tried**: Solutions attempted from this guide

### Example Help Request

```markdown
## Issue: Handler not found error

**Error**:
```
InvalidArgumentException: Handler [custom] not registered.
```

**Environment**:
- PHP: 8.2.15
- Laravel: 11.0
- 3neti/form-flow: 1.7.0
- 3neti/form-handler-custom: 1.0.0

**YAML Driver**:
```yaml
steps:
  my_step:
    handler: "custom"
    step_name: "custom_data"
```

**Handler Code**:
```php
public function getName(): string {
    return 'custom';
}
```

**What I've Tried**:
- [x] Ran `php artisan config:clear`
- [x] Verified package installed (`composer show 3neti/form-handler-custom`)
- [x] Checked `config('form-flow.handlers')` - returns empty array
- [ ] Verified service provider registered (how to check?)

**Question**: How do I verify the service provider is loading?
```

---

## Related Documentation

- [INTEGRATION.md](./INTEGRATION.md) - Complete integration guide
- [HANDLERS.md](./HANDLERS.md) - Handler development guide
- [INTEGRATION_CHECKLIST.md](./INTEGRATION_CHECKLIST.md) - Setup checklist
- [README.md](./README.md) - Documentation index

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-03  
**Maintained By**: Development Team
