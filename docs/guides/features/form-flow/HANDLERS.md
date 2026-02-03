# Form Flow Handler Development Guide

**Version**: 1.0  
**Last Updated**: 2026-02-03  
**Package**: `3neti/form-flow` v1.7+

## Table of Contents

1. [Overview](#overview)
2. [FormHandlerInterface Contract](#formhandlerinterface-contract)
3. [Creating a Custom Handler](#creating-a-custom-handler)
4. [Service Provider Auto-Registration](#service-provider-auto-registration)
5. [Vue Component Integration](#vue-component-integration)
6. [Publishing Frontend Assets](#publishing-frontend-assets)
7. [Handler Lifecycle](#handler-lifecycle)
8. [Data Collection Format](#data-collection-format)
9. [Examples from Built-in Handlers](#examples-from-built-in-handlers)
10. [Testing Your Handler](#testing-your-handler)

---

## Overview

Form flow handlers are **pluggable components** that handle specific types of user input collection. Each handler is a separate package that:

1. Implements `FormHandlerInterface`
2. Provides backend processing (validation, data transformation)
3. Includes frontend UI (Vue components via Inertia.js)
4. Auto-registers via Laravel Package Discovery

**Available Handlers**:
- `form` (core) - Generic multi-field forms
- `splash` (core) - Welcome/intro screens
- `location` (plugin) - GPS + geocoding
- `selfie` (plugin) - Camera capture
- `signature` (plugin) - Digital signature pad
- `kyc` (plugin) - Identity verification (HyperVerge)
- `otp` (plugin) - SMS verification

---

## FormHandlerInterface Contract

All handlers must implement this interface:

```php
namespace LBHurtado\FormFlowManager\Contracts;

use Illuminate\Http\Request;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

interface FormHandlerInterface
{
    /**
     * Get handler name (used in YAML drivers)
     * @return string Handler identifier (e.g., 'location', 'signature')
     */
    public function getName(): string;
    
    /**
     * Process submitted step data
     * @param Request $request HTTP request with user input
     * @param FormFlowStepData $step Step configuration from driver
     * @param array $context Flow context (flow_id, collected_data, etc.)
     * @return array Processed data to store in collected_data
     */
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array;
    
    /**
     * Validate step data (optional - can return true if handle() validates)
     * @param array $data User input data
     * @param array $rules Validation rules
     * @return bool Validation result
     */
    public function validate(array $data, array $rules): bool;
    
    /**
     * Render step UI
     * @param FormFlowStepData $step Step configuration from driver
     * @param array $context Flow context (flow_id, step_index, etc.)
     * @return \Inertia\Response Inertia response with Vue component
     */
    public function render(FormFlowStepData $step, array $context = []);
    
    /**
     * Get configuration schema (optional)
     * @return array Schema for handler-specific config validation
     */
    public function getConfigSchema(): array;
}
```

**Method Responsibilities**:
- `getName()`: Returns handler identifier used in YAML `handler:` field
- `handle()`: Processes and validates submitted data, returns array for storage
- `validate()`: Optional pre-validation (most handlers do validation in `handle()`)
- `render()`: Returns Inertia response to display Vue component
- `getConfigSchema()`: Optional validation rules for handler config

---

## Creating a Custom Handler

### Step 1: Package Structure

Create a new package using standard Laravel package structure:

```
packages/form-handler-myhandler/
├── composer.json
├── config/
│   └── myhandler.php
├── src/
│   ├── MyHandler.php
│   ├── MyHandlerServiceProvider.php
│   └── Data/
│       └── MyHandlerData.php
└── stubs/
    └── resources/
        └── js/
            └── pages/
                └── form-flow/
                    └── myhandler/
                        ├── MyHandlerPage.vue
                        └── components/
                            └── ...
```

### Step 2: Create Handler Class

```php
<?php

declare(strict_types=1);

namespace YourVendor\FormHandlerMyHandler;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

class MyHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'myhandler';  // Used in YAML: handler: "myhandler"
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // 1. Extract input data
        $inputData = $request->input('data', $request->all());
        
        // 2. Validate
        $validated = validator($inputData, [
            'field1' => 'required|string|max:255',
            'field2' => 'required|integer|min:0',
        ])->validate();
        
        // 3. Transform/process data
        $validated['processed_at'] = now()->toIso8601String();
        
        // 4. Return data to store in collected_data[step_name]
        return $validated;
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Most handlers return true here and validate in handle()
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        // Render Vue component with Inertia
        return Inertia::render('form-flow/myhandler/MyHandlerPage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'config' => array_merge([
                // Default config values
                'option1' => config('myhandler.option1', 'default'),
            ], $step->config),  // Merge with YAML config
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'option1' => 'nullable|string',
            'option2' => 'boolean',
        ];
    }
}
```

### Step 3: Create Data DTO (Optional)

Use Spatie Laravel Data for type-safe data handling:

```php
<?php

namespace YourVendor\FormHandlerMyHandler\Data;

use Spatie\LaravelData\Data;

class MyHandlerData extends Data
{
    public function __construct(
        public string $field1,
        public int $field2,
        public string $processed_at,
    ) {}
}
```

---

## Service Provider Auto-Registration

### Create Service Provider

```php
<?php

declare(strict_types=1);

namespace YourVendor\FormHandlerMyHandler;

use Illuminate\Support\ServiceProvider;

class MyHandlerServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/myhandler.php',
            'myhandler'
        );
        
        // Register handler as singleton
        $this->app->singleton(MyHandler::class, function ($app) {
            return new MyHandler();
        });
    }
    
    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/myhandler.php' => config_path('myhandler.php'),
        ], 'myhandler-config');
        
        // Publish frontend assets (Vue components)
        $this->publishes([
            __DIR__.'/../stubs/resources/js/pages/form-flow/myhandler' 
                => resource_path('js/pages/form-flow/myhandler'),
        ], 'myhandler-stubs');
        
        // Auto-register handler with form-flow-manager
        $this->registerHandler();
    }
    
    /**
     * Register handler with form-flow-manager
     */
    protected function registerHandler(): void
    {
        $handlers = config('form-flow.handlers', []);
        $handlers['myhandler'] = MyHandler::class;
        config(['form-flow.handlers' => $handlers]);
    }
}
```

### composer.json

```json
{
    "name": "yourvendor/form-handler-myhandler",
    "description": "My custom handler for form flow",
    "type": "library",
    "require": {
        "php": "^8.2",
        "3neti/form-flow": "^1.7",
        "spatie/laravel-data": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\FormHandlerMyHandler\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "YourVendor\\FormHandlerMyHandler\\MyHandlerServiceProvider"
            ]
        }
    }
}
```

**Key Points**:
- `extra.laravel.providers` enables Laravel Package Discovery
- Handler auto-registers on boot (no manual config needed)
- Vue components published to host app via `vendor:publish`

---

## Vue Component Integration

### Vue Component Structure

```vue
<!-- resources/js/pages/form-flow/myhandler/MyHandlerPage.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

// Props from Inertia (passed by render() method)
interface Props {
  flow_id: string
  step: string
  config: {
    option1?: string
    option2?: boolean
  }
}

const props = defineProps<Props>()

// Component state
const field1 = ref('')
const field2 = ref(0)
const isSubmitting = ref(false)

// Submit handler
const submit = () => {
  isSubmitting.value = true
  
  // POST to form-flow controller
  router.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
    data: {
      field1: field1.value,
      field2: field2.value,
    }
  }, {
    onSuccess: () => {
      // Form flow controller handles navigation to next step
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
      isSubmitting.value = false
    },
    onFinish: () => {
      isSubmitting.value = false
    }
  })
}
</script>

<template>
  <div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">My Handler Step</h1>
    
    <form @submit.prevent="submit">
      <div class="mb-4">
        <label for="field1" class="block mb-2">Field 1</label>
        <input
          id="field1"
          v-model="field1"
          type="text"
          class="w-full px-3 py-2 border rounded"
          required
        />
      </div>
      
      <div class="mb-4">
        <label for="field2" class="block mb-2">Field 2</label>
        <input
          id="field2"
          v-model.number="field2"
          type="number"
          class="w-full px-3 py-2 border rounded"
          required
        />
      </div>
      
      <button
        type="submit"
        :disabled="isSubmitting"
        class="px-4 py-2 bg-blue-600 text-white rounded"
      >
        {{ isSubmitting ? 'Processing...' : 'Continue' }}
      </button>
    </form>
  </div>
</template>
```

**Key Patterns**:
1. Props match what `render()` method passes
2. Form submits to `/form-flow/{flow_id}/step/{step}` with `data` object
3. Inertia router handles success/error/navigation automatically
4. Handler's `handle()` method receives `data` field

---

## Publishing Frontend Assets

### Publishing Strategy

**Option 1: Direct Publish to Host App**
```php
// In ServiceProvider::boot()
$this->publishes([
    __DIR__.'/../stubs/resources/js/pages/form-flow/myhandler' 
        => resource_path('js/pages/form-flow/myhandler'),
], 'myhandler-stubs');
```

**Installation**:
```bash
# Manual publish
php artisan vendor:publish --tag=myhandler-stubs

# Or auto-publish via composer post-update-cmd
composer.json:
"scripts": {
    "post-update-cmd": [
        "@php artisan vendor:publish --tag=myhandler-stubs --ansi --force"
    ]
}
```

**Option 2: Symlink (Development)**
```bash
# Create symlink for development
ln -s packages/form-handler-myhandler/stubs/resources/js/pages/form-flow/myhandler \
      resources/js/pages/form-flow/myhandler
```

### Component Path Resolution

Inertia resolves components from `resources/js/pages/`:
```php
// Handler renders this:
Inertia::render('form-flow/myhandler/MyHandlerPage', [...])

// Resolves to:
resources/js/pages/form-flow/myhandler/MyHandlerPage.vue
```

---

## Handler Lifecycle

### Complete Request Flow

```
1. User navigates to /form-flow/{flow_id}
   ↓
2. FormFlowController::show()
   - Loads session state
   - Determines current step (e.g., step 3)
   - Resolves handler from config (e.g., 'myhandler')
   ↓
3. Handler::render($step, $context)
   - Returns Inertia response
   - Passes config + context to Vue
   ↓
4. Vue component renders in browser
   - User interacts with form
   - Submits data
   ↓
5. POST /form-flow/{flow_id}/step/3
   - FormFlowController::updateStep()
   ↓
6. Handler::handle($request, $step, $context)
   - Validates input
   - Transforms data
   - Returns processed array
   ↓
7. FormFlowController stores data
   - session('form_flow.{flow_id}.collected_data.{step_name}' = $processedData)
   - Increments current_step
   - Redirects to next step OR complete page
```

### Session State During Lifecycle

```php
// Before step
session('form_flow.abc123') = [
    'flow_id' => 'abc123',
    'current_step' => 2,
    'collected_data' => [
        'wallet_info' => [...],
        'kyc_verification' => [...],
    ],
]

// After handler processes step 3
session('form_flow.abc123') = [
    'flow_id' => 'abc123',
    'current_step' => 3,  // Incremented
    'collected_data' => [
        'wallet_info' => [...],
        'kyc_verification' => [...],
        'myhandler_step' => [  // NEW: Handler's return value
            'field1' => 'value',
            'field2' => 123,
            'processed_at' => '2026-02-03T13:00:00+00:00',
        ],
    ],
]
```

---

## Data Collection Format

### Handler Return Format

Handlers return **flat associative arrays**:

```php
// Good: Flat structure
public function handle(...)
{
    return [
        'latitude' => 14.646,
        'longitude' => 121.028,
        'accuracy' => 107,
        'timestamp' => '2026-02-03T12:00:00+08:00',
    ];
}

// Also good: Nested structure (if needed)
public function handle(...)
{
    return [
        'latitude' => 14.646,
        'longitude' => 121.028,
        'address' => [
            'formatted' => 'Makati City, Philippines',
            'components' => [...]
        ],
    ];
}
```

### Storage Location

Data is stored by `step_name` from YAML driver:

```yaml
# YAML driver
steps:
  myhandler_step:
    handler: "myhandler"
    step_name: "custom_data"  # KEY: This is the storage key
```

```php
// Stored in session
collected_data['custom_data'] = [/* handler return value */]
```

### Accessing Collected Data

**In subsequent handlers**:
```php
public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    // Access previous step data
    $previousData = $context['collected_data']['custom_data'] ?? [];
    
    // Use in processing
    $myField = $previousData['field1'] ?? 'default';
}
```

**In controller (after completion)**:
```php
$state = $this->formFlowService->getFlowState($flowId);
$allData = $state['collected_data'];

// Flatten all steps
$flatData = [];
foreach ($allData as $stepData) {
    $flatData = array_merge($flatData, $stepData);
}
```

---

## Examples from Built-in Handlers

### Example 1: Location Handler

**Handler** (`LocationHandler.php`):
```php
public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    $inputData = $request->input('data', $request->all());
    
    $validated = validator($inputData, [
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'formatted_address' => 'nullable|string|max:500',
        'map' => 'nullable|string', // base64 image
        'accuracy' => 'nullable|numeric|min:0',
    ])->validate();
    
    $validated['timestamp'] = now()->toIso8601String();
    
    return $validated;
}

public function render(FormFlowStepData $step, array $context = [])
{
    return Inertia::render('form-flow/location/LocationCapturePage', [
        'flow_id' => $context['flow_id'] ?? null,
        'step' => (string) ($context['step_index'] ?? 0),
        'config' => array_merge([
            'opencage_api_key' => config('location-handler.opencage_api_key'),
            'capture_snapshot' => config('location-handler.capture_snapshot', true),
            'require_address' => config('location-handler.require_address', false),
        ], $step->config),
    ]);
}
```

**Vue Component** (simplified):
```vue
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  flow_id: string
  step: string
  config: { opencage_api_key?: string }
}>()

const location = ref(null)

const getLocation = () => {
  navigator.geolocation.getCurrentPosition((position) => {
    location.value = {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy: position.coords.accuracy,
    }
  })
}

const submit = () => {
  router.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
    data: location.value
  })
}
</script>

<template>
  <div>
    <button @click="getLocation">Get My Location</button>
    <button @click="submit" :disabled="!location">Continue</button>
  </div>
</template>
```

### Example 2: Signature Handler

**Handler** (`SignatureHandler.php`):
```php
public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    $inputData = $request->input('data', $request->all());
    
    $validated = validator($inputData, [
        'signature' => 'required|string', // base64 PNG
    ])->validate();
    
    // Optional: Validate signature is not empty canvas
    $imageData = $validated['signature'];
    if ($this->isEmptySignature($imageData)) {
        throw ValidationException::withMessages([
            'signature' => 'Please provide a signature',
        ]);
    }
    
    return $validated;
}

protected function isEmptySignature(string $base64): bool
{
    // Decode and check if mostly transparent
    // Implementation details...
    return false;
}
```

**Vue Component** (simplified with signature pad library):
```vue
<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import SignaturePad from 'signature_pad'

const props = defineProps<{
  flow_id: string
  step: string
  config: { width?: number; height?: number }
}>()

const canvas = ref<HTMLCanvasElement | null>(null)
let signaturePad: SignaturePad | null = null

onMounted(() => {
  signaturePad = new SignaturePad(canvas.value!)
})

const clear = () => signaturePad?.clear()

const submit = () => {
  const dataURL = signaturePad?.toDataURL('image/png')
  
  router.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
    data: { signature: dataURL }
  })
}
</script>

<template>
  <div>
    <canvas ref="canvas" :width="config.width || 600" :height="config.height || 256" />
    <button @click="clear">Clear</button>
    <button @click="submit">Continue</button>
  </div>
</template>
```

### Example 3: KYC Handler (HyperVerge Integration)

**Handler** (`KYCHandler.php`):
```php
public function render(FormFlowStepData $step, array $context = [])
{
    // Generate HyperVerge onboarding URL
    $transactionId = "formflow-{$context['flow_id']}-kyc-" . time();
    $onboardingUrl = $this->hyperverge->createOnboardingLink($transactionId);
    
    // Redirect to HyperVerge (external flow)
    return redirect($onboardingUrl);
}

public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    // Called after HyperVerge callback
    $transactionId = $request->input('transaction_id');
    
    // Fetch results from HyperVerge API
    $kycResults = $this->hyperverge->getResults($transactionId);
    
    // Flatten HyperVerge data structure
    return $this->flattenKYCData($kycResults);
}

protected function flattenKYCData(array $results): array
{
    return [
        'status' => $results['status'],
        'transaction_id' => $results['transactionId'],
        'name' => $results['result']['name'] ?? null,
        'date_of_birth' => $results['result']['dateOfBirth'] ?? null,
        'address' => $results['result']['address'] ?? null,
        'id_type' => $results['result']['idType'] ?? null,
        'id_number' => $results['result']['idNumber'] ?? null,
    ];
}
```

---

## Testing Your Handler

### Unit Tests (Pest PHP)

```php
// tests/Unit/MyHandlerTest.php
use YourVendor\FormHandlerMyHandler\MyHandler;

it('validates input correctly', function () {
    $handler = new MyHandler();
    
    $request = Request::create('/test', 'POST', [
        'data' => [
            'field1' => 'test value',
            'field2' => 123,
        ]
    ]);
    
    $step = FormFlowStepData::from([
        'handler' => 'myhandler',
        'step_name' => 'test_step',
        'config' => [],
    ]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toHaveKeys(['field1', 'field2', 'processed_at']);
    expect($result['field1'])->toBe('test value');
});

it('rejects invalid input', function () {
    $handler = new MyHandler();
    
    $request = Request::create('/test', 'POST', [
        'data' => ['field1' => ''] // Missing required field
    ]);
    
    $step = FormFlowStepData::from([
        'handler' => 'myhandler',
        'step_name' => 'test_step',
        'config' => [],
    ]);
    
    expect(fn() => $handler->handle($request, $step))
        ->toThrow(ValidationException::class);
});
```

### Integration Tests

```php
// tests/Feature/MyHandlerIntegrationTest.php
it('integrates with form flow system', function () {
    // Start a flow
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'test-123',
        'steps' => [
            ['handler' => 'myhandler', 'step_name' => 'test_step', 'config' => []]
        ],
        'callbacks' => [
            'on_complete' => 'http://localhost/callback',
        ],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->startFlow($instructions);
    
    // Submit step data
    $response = $this->post("/form-flow/{$state['flow_id']}/step/0", [
        'data' => [
            'field1' => 'test',
            'field2' => 456,
        ]
    ]);
    
    $response->assertRedirect(); // Redirects to next step
    
    // Verify data stored
    $updatedState = $service->getFlowState($state['flow_id']);
    expect($updatedState['collected_data']['test_step'])->toHaveKey('field1');
});
```

### Manual Testing with Test Command

```php
// src/Console/TestMyHandlerCommand.php
class TestMyHandlerCommand extends Command
{
    protected $signature = 'test:myhandler';
    
    public function handle()
    {
        $instructions = FormFlowInstructionsData::from([
            'reference_id' => 'manual-test-' . time(),
            'steps' => [
                [
                    'handler' => 'myhandler',
                    'step_name' => 'manual_test',
                    'config' => ['option1' => 'test'],
                ]
            ],
            'callbacks' => [
                'on_complete' => url('/test/callback'),
            ],
        ]);
        
        $service = app(FormFlowService::class);
        $state = $service->startFlow($instructions);
        
        $url = url("/form-flow/{$state['flow_id']}");
        
        $this->info("Flow started: {$url}");
        $this->info("Open this URL in your browser to test the handler");
    }
}
```

---

## Best Practices

### 1. Handler Naming

```php
// Good: Simple, descriptive
public function getName(): string { return 'location'; }
public function getName(): string { return 'signature'; }

// Bad: Generic, unclear
public function getName(): string { return 'handler1'; }
public function getName(): string { return 'custom'; }
```

### 2. Data Validation

```php
// Good: Validate in handle(), return clean data
public function handle(...)
{
    $validated = validator($inputData, [...])->validate();
    return $validated;
}

// Bad: No validation, trust user input
public function handle(...)
{
    return $request->all(); // Unsafe!
}
```

### 3. Config Defaults

```php
// Good: Merge defaults with step config
public function render(...)
{
    return Inertia::render('...', [
        'config' => array_merge([
            'option1' => config('myhandler.default_option1'),
        ], $step->config),
    ]);
}

// Bad: No defaults, config required
public function render(...)
{
    return Inertia::render('...', [
        'config' => $step->config, // Missing keys cause errors
    ]);
}
```

### 4. Error Handling

```php
// Good: Descriptive validation errors
throw ValidationException::withMessages([
    'field1' => 'Field 1 must be at least 3 characters',
]);

// Good: Log errors for debugging
catch (\Exception $e) {
    Log::error('[MyHandler] Processing failed', [
        'error' => $e->getMessage(),
        'flow_id' => $context['flow_id'] ?? null,
    ]);
    throw $e;
}
```

### 5. Type Safety

```php
// Good: Use DTOs for structured data
$data = MyHandlerData::from($validated);
return $data->toArray();

// Good: Type-hint return values
public function handle(...): array
{
    return ['field' => 'value'];
}
```

---

## Next Steps

After creating your custom handler:

1. **Package It**: Publish on Packagist for reuse across projects
2. **Document It**: Add README with installation and usage instructions
3. **Test It**: Write comprehensive unit and integration tests
4. **Share It**: Contribute back to the 3neti/form-flow ecosystem

**Related Documentation**:
- [INTEGRATION.md](./INTEGRATION.md) - Complete integration guide
- [INTEGRATION_CHECKLIST.md](./INTEGRATION_CHECKLIST.md) - Quick setup reference
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) - Common issues
- [README.md](./README.md) - Documentation index

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-03  
**Maintained By**: Development Team
