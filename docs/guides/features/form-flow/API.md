# Form Flow API Reference

**Version**: 1.0  
**Last Updated**: 2026-02-03  
**Package**: `3neti/form-flow` v1.7+

## Table of Contents

1. [Overview](#overview)
2. [FormFlowService API](#formflowservice-api)
3. [DriverService API](#driverservice-api)
4. [FormFlowController HTTP Endpoints](#formflowcontroller-http-endpoints)
5. [Handler Interface](#handler-interface)
6. [Data Transfer Objects](#data-transfer-objects)
7. [Webhook Callbacks](#webhook-callbacks)
8. [HTTP Status Codes](#http-status-codes)

---

## Overview

The Form Flow API consists of three main components:

1. **FormFlowService**: Session-based state management
2. **DriverService**: YAML driver loading and template processing
3. **FormFlowController**: HTTP endpoints for browser-based flows

**API Patterns**:
- **Server-to-server**: Use FormFlowService directly in PHP
- **Browser-based**: Use FormFlowController HTTP endpoints via Inertia.js
- **Hybrid**: HyperVerge-style pattern (server initiates, browser executes)

---

## FormFlowService API

**Namespace**: `LBHurtado\FormFlowManager\Services\FormFlowService`

**Purpose**: Manages form flow state using Laravel sessions.

### startFlow()

Creates a new form flow and initializes session state.

**Signature**:
```php
public function startFlow(FormFlowInstructionsData $instructions): array
```

**Parameters**:
- `$instructions` (FormFlowInstructionsData): Flow configuration including steps, callbacks, reference_id

**Returns**: `array` - Initial flow state

**Example**:
```php
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

$service = app(FormFlowService::class);

$instructions = FormFlowInstructionsData::from([
    'reference_id' => 'unique-ref-123',
    'steps' => [
        ['handler' => 'splash', 'config' => ['title' => 'Welcome']],
        ['handler' => 'form', 'config' => ['fields' => [...]]],
    ],
    'callbacks' => [
        'on_complete' => 'https://app.test/callback',
    ],
]);

$state = $service->startFlow($instructions);

// Returns:
// [
//     'flow_id' => 'flow-abc123...',
//     'reference_id' => 'unique-ref-123',
//     'instructions' => [...],
//     'current_step' => 0,
//     'completed_steps' => [],
//     'collected_data' => [],
//     'started_at' => '2026-02-03T12:00:00+00:00',
//     'status' => 'active',
// ]
```

**Session Storage**:
- Key: `form_flow.{flow_id}`
- Reference mapping: `form_flow_ref.{reference_id}` → `flow_id`

---

### getFlowState()

Retrieves flow state by flow_id.

**Signature**:
```php
public function getFlowState(string $flowId): ?array
```

**Parameters**:
- `$flowId` (string): Flow identifier (e.g., `flow-abc123...`)

**Returns**: `array|null` - Flow state or null if not found

**Example**:
```php
$state = $service->getFlowState('flow-abc123');

if ($state) {
    echo "Current step: " . $state['current_step'];
    echo "Status: " . $state['status'];
}
```

---

### getFlowStateByReference()

Retrieves flow state by reference_id.

**Signature**:
```php
public function getFlowStateByReference(string $referenceId): ?array
```

**Parameters**:
- `$referenceId` (string): External reference identifier

**Returns**: `array|null` - Flow state or null if not found

**Example**:
```php
// Useful when you only have the reference_id (e.g., voucher code)
$state = $service->getFlowStateByReference('voucher-TEST');
```

---

### updateStepData()

Stores data collected from a step and advances the flow.

**Signature**:
```php
public function updateStepData(
    string $flowId,
    int $stepIndex,
    array $data,
    ?string $stepName = null
): array
```

**Parameters**:
- `$flowId` (string): Flow identifier
- `$stepIndex` (int): Zero-based step index (0, 1, 2, ...)
- `$data` (array): Validated step data from handler
- `$stepName` (string|null): Optional step name for named access

**Returns**: `array` - Updated flow state

**Example**:
```php
$data = [
    'mobile' => '09171234567',
    'email' => 'user@example.com',
];

$state = $service->updateStepData(
    flowId: 'flow-abc123',
    stepIndex: 1,
    data: $data,
    stepName: 'wallet_info'  // Optional
);

// collected_data structure:
// [
//     1 => [
//         'mobile' => '09171234567',
//         'email' => 'user@example.com',
//         '_step_name' => 'wallet_info',
//     ],
// ]
```

**Side Effects**:
- Marks step as completed in `completed_steps` array
- Increments `current_step` if on current step
- Updates `updated_at` timestamp

---

### completeFlow()

Marks flow as completed.

**Signature**:
```php
public function completeFlow(string $flowId): array
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `array` - Final flow state with `status='completed'` and `completed_at` timestamp

**Example**:
```php
$state = $service->completeFlow('flow-abc123');

// State changes:
// - status: 'active' → 'completed'
// - completed_at: '2026-02-03T13:00:00+00:00' (added)
```

**Throws**: `RuntimeException` if flow not found

---

### cancelFlow()

Marks flow as cancelled.

**Signature**:
```php
public function cancelFlow(string $flowId): array
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `array` - Final flow state with `status='cancelled'` and `cancelled_at` timestamp

**Example**:
```php
$state = $service->cancelFlow('flow-abc123');
```

**Throws**: `RuntimeException` if flow not found

---

### clearFlow()

Removes flow from session (cleanup).

**Signature**:
```php
public function clearFlow(string $flowId): void
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `void`

**Example**:
```php
// After processing completion callback
$service->clearFlow('flow-abc123');
```

**Note**: Does not remove reference mapping. Use only after callback processing is complete.

---

### flowExists()

Checks if flow exists in session.

**Signature**:
```php
public function flowExists(string $flowId): bool
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `bool` - True if flow exists, false otherwise

**Example**:
```php
if (!$service->flowExists($flowId)) {
    abort(404, 'Flow not found or expired');
}
```

---

### getCurrentStep()

Gets current step index.

**Signature**:
```php
public function getCurrentStep(string $flowId): int
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `int` - Zero-based step index

**Example**:
```php
$currentStep = $service->getCurrentStep('flow-abc123');
// Returns: 0, 1, 2, ...
```

---

### getCollectedData()

Gets all collected data from all steps.

**Signature**:
```php
public function getCollectedData(string $flowId): array
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `array` - Collected data indexed by step index

**Example**:
```php
$data = $service->getCollectedData('flow-abc123');

// Returns:
// [
//     0 => [],  // Splash step (no data)
//     1 => ['mobile' => '09171234567'],
//     2 => ['selfie' => 'data:image/png;base64,...'],
// ]
```

---

### isComplete()

Checks if flow is completed.

**Signature**:
```php
public function isComplete(string $flowId): bool
```

**Parameters**:
- `$flowId` (string): Flow identifier

**Returns**: `bool` - True if status is 'completed', false otherwise

**Example**:
```php
if ($service->isComplete($flowId)) {
    // Trigger callback or redirect
}
```

---

## DriverService API

**Namespace**: `LBHurtado\FormFlowManager\Services\DriverService`

**Purpose**: Loads and processes YAML drivers with template rendering.

### loadDriver()

Loads YAML driver, processes templates, and returns FormFlowInstructionsData.

**Signature**:
```php
public function loadDriver(string $driverName, array $context): FormFlowInstructionsData
```

**Parameters**:
- `$driverName` (string): Driver filename without `.yaml` extension
- `$context` (array): Variables for template processing

**Returns**: `FormFlowInstructionsData` - Processed flow instructions

**Example**:
```php
use LBHurtado\FormFlowManager\Services\DriverService;

$service = app(DriverService::class);

$context = [
    'reference_id' => 'voucher-TEST',
    'voucher' => $voucherObject,
    'amount' => 500,
    'has_kyc' => true,
];

$instructions = $service->loadDriver('voucher-redemption', $context);

// Returns FormFlowInstructionsData with processed templates:
// - reference_id: "voucher-TEST"
// - steps: [...] (with {{ templates }} replaced)
// - callbacks: [...] (with {{ templates }} replaced)
```

**Template Processing**:
- Replaces `{{ variable }}` with values from `$context`
- Supports dot notation: `{{ voucher.code }}`
- Evaluates conditions: `{{ has_kyc }}` → `true` or `false`

**File Location**: `config/form-flow-drivers/{driver_name}.yaml`

**Throws**: `RuntimeException` if driver file not found

---

### transform()

Transforms Voucher model to FormFlowInstructionsData (legacy method).

**Signature**:
```php
public function transform(Voucher $voucher): FormFlowInstructionsData
```

**Parameters**:
- `$voucher` (Voucher): Voucher model instance

**Returns**: `FormFlowInstructionsData`

**Example**:
```php
$voucher = Voucher::whereCode('TEST')->first();
$instructions = $service->transform($voucher);
```

**Note**: This method builds context from voucher automatically. Prefer `loadDriver()` for more control.

---

### loadConfig()

Loads YAML driver configuration (low-level method).

**Signature**:
```php
public function loadConfig(string $driverName = 'voucher-redemption'): void
```

**Parameters**:
- `$driverName` (string): Driver filename without `.yaml` extension

**Returns**: `void`

**Side Effects**: Parses YAML and stores in `$this->config` property

**Example**:
```php
$service->loadConfig('simple-test');
// Now $service->config contains parsed YAML array
```

---

## FormFlowController HTTP Endpoints

**Namespace**: `LBHurtado\FormFlowManager\Http\Controllers\FormFlowController`

**Base Route**: `/form-flow`

**Middleware**: Configured via `config('form-flow.middleware')` (default: `web`)

---

### POST /form-flow/start

Initiates a new form flow (server-to-server).

**Purpose**: HyperVerge-style pattern - server initiates, returns `flow_url` for browser access.

**Request**:
```http
POST /form-flow/start
Content-Type: application/json

{
  "reference_id": "unique-ref-123",
  "steps": [
    {
      "handler": "splash",
      "config": {
        "title": "Welcome!",
        "description": "Start your journey"
      }
    },
    {
      "handler": "form",
      "config": {
        "fields": [
          {
            "name": "mobile",
            "type": "text",
            "required": true
          }
        ]
      }
    }
  ],
  "callbacks": {
    "on_complete": "https://app.test/callback",
    "on_cancel": "https://app.test"
  },
  "metadata": {
    "user_id": 123,
    "source": "mobile_app"
  }
}
```

**Validation Rules**:
- `reference_id`: required, string, max:255, unique (must not be used before)
- `steps`: required, array, min:1
- `steps.*.handler`: required, string
- `steps.*.config`: nullable, array
- `callbacks`: required, array
- `callbacks.on_complete`: required, url
- `callbacks.on_cancel`: nullable, url
- `metadata`: nullable, array
- `title`: nullable, string
- `description`: nullable, string

**Response** (200 OK):
```json
{
  "success": true,
  "reference_id": "unique-ref-123",
  "flow_url": "https://app.test/form-flow/flow-abc123..."
}
```

**Response** (422 Unprocessable Entity):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "reference_id": ["The reference_id has already been used."]
  }
}
```

**Usage**:
```php
// Server-side initiation
$response = Http::post('https://app.test/form-flow/start', [
    'reference_id' => 'order-456',
    'steps' => [...],
    'callbacks' => [...],
]);

$flowUrl = $response->json('flow_url');

// Redirect user to flow_url
return redirect($flowUrl);
```

**CSRF**: This endpoint should be exempted from CSRF if called server-to-server.

---

### GET /form-flow/{flow_id}

Displays current step or flow state.

**Purpose**: Renders the current step's Vue component via Inertia.js

**Request**:
```http
GET /form-flow/flow-abc123
```

**Response** (Browser - Inertia):
- Renders Vue component via handler's `render()` method
- Component receives props: `flow_id`, `step`, `config`, `collected_data`

**Response** (JSON - API):
```http
GET /form-flow/flow-abc123
Accept: application/json
```

```json
{
  "success": true,
  "state": {
    "flow_id": "flow-abc123",
    "reference_id": "unique-ref-123",
    "current_step": 1,
    "status": "active",
    "collected_data": {
      "0": {},
      "1": {"mobile": "09171234567"}
    },
    "started_at": "2026-02-03T12:00:00+00:00",
    "updated_at": "2026-02-03T12:05:00+00:00"
  }
}
```

**Response** (404 Not Found):
```http
HTTP/1.1 404 Not Found

Flow not found
```

**Special Cases**:
- If all steps completed: Auto-completes flow and renders `form-flow/core/Complete` page
- Triggers `on_complete` callback automatically
- Applies cached KYC results if available (for async handlers)

---

### POST /form-flow/{flow_id}/step/{step}

Submits data for a specific step.

**Purpose**: Handler processes and stores step data, advances flow.

**Request**:
```http
POST /form-flow/flow-abc123/step/1
Content-Type: application/json
X-CSRF-TOKEN: ...

{
  "data": {
    "mobile": "09171234567",
    "email": "user@example.com"
  }
}
```

**Validation**:
- `data`: required, array
- Handler performs additional validation based on step config

**Response** (302 Found - Success):
```http
HTTP/1.1 302 Found
Location: /form-flow/flow-abc123
```
Redirects to next step (or completion page if all steps done)

**Response** (422 Unprocessable Entity - Validation Error):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "data.mobile": ["The mobile field is required."]
  }
}
```

**Handler Processing**:
1. Controller resolves handler class from step config
2. Calls `handler->handle($request, $stepData, $context)`
3. Handler validates and returns processed data
4. Controller stores data via `FormFlowService::updateStepData()`
5. Redirects to next step

---

### POST /form-flow/{flow_id}/complete

Manually completes a flow (if not auto-completed).

**Purpose**: Allows explicit completion and callback triggering.

**Request**:
```http
POST /form-flow/flow-abc123/complete
```

**Response** (200 OK):
```json
{
  "success": true,
  "flow_id": "flow-abc123",
  "status": "completed",
  "collected_data": {...}
}
```

**Side Effects**:
- Marks flow as completed
- Triggers `on_complete` callback

---

### POST /form-flow/{flow_id}/cancel

Cancels an in-progress flow.

**Purpose**: User abandons flow, trigger `on_cancel` callback.

**Request**:
```http
POST /form-flow/flow-abc123/cancel
```

**Response** (200 OK):
```json
{
  "success": true,
  "flow_id": "flow-abc123",
  "status": "cancelled"
}
```

**Side Effects**:
- Marks flow as cancelled
- Triggers `on_cancel` callback (if defined)

---

### DELETE /form-flow/{flow_id}

Clears flow from session.

**Purpose**: Cleanup after processing.

**Request**:
```http
DELETE /form-flow/flow-abc123
```

**Response** (204 No Content):
```http
HTTP/1.1 204 No Content
```

**Side Effects**: Removes flow from session storage

---

## Handler Interface

**Namespace**: `LBHurtado\FormFlowManager\Contracts\FormHandlerInterface`

All handlers must implement this interface.

### getName()

**Signature**:
```php
public function getName(): string
```

**Returns**: Handler identifier used in YAML `handler:` field

**Example**:
```php
public function getName(): string
{
    return 'location';
}
```

---

### handle()

**Signature**:
```php
public function handle(
    Request $request,
    FormFlowStepData $step,
    array $context = []
): array
```

**Parameters**:
- `$request`: HTTP request with user input
- `$step`: Step configuration from driver
- `$context`: Flow context (flow_id, collected_data, etc.)

**Returns**: Processed data array to store in session

**Example**:
```php
public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    $data = $request->input('data', $request->all());
    
    $validated = validator($data, [
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
    ])->validate();
    
    $validated['timestamp'] = now()->toIso8601String();
    
    return $validated;
}
```

---

### validate()

**Signature**:
```php
public function validate(array $data, array $rules): bool
```

**Parameters**:
- `$data`: User input data
- `$rules`: Validation rules

**Returns**: Validation result (most handlers return `true` and validate in `handle()`)

---

### render()

**Signature**:
```php
public function render(FormFlowStepData $step, array $context = [])
```

**Parameters**:
- `$step`: Step configuration
- `$context`: Flow context (flow_id, step_index, collected_data)

**Returns**: Inertia response with Vue component

**Example**:
```php
public function render(FormFlowStepData $step, array $context = [])
{
    return Inertia::render('form-flow/location/LocationCapturePage', [
        'flow_id' => $context['flow_id'] ?? null,
        'step' => (string) ($context['step_index'] ?? 0),
        'config' => array_merge([
            'opencage_api_key' => config('location-handler.opencage_api_key'),
        ], $step->config),
    ]);
}
```

---

### getConfigSchema()

**Signature**:
```php
public function getConfigSchema(): array
```

**Returns**: Validation rules for handler config

**Example**:
```php
public function getConfigSchema(): array
{
    return [
        'opencage_api_key' => 'nullable|string',
        'capture_snapshot' => 'boolean',
    ];
}
```

---

## Data Transfer Objects

### FormFlowInstructionsData

**Namespace**: `LBHurtado\FormFlowManager\Data\FormFlowInstructionsData`

**Properties**:
- `reference_id` (string): External reference identifier
- `steps` (array): Array of FormFlowStepData
- `callbacks` (array): Callback URLs
  - `on_complete` (string): Callback URL on completion
  - `on_cancel` (string|null): Callback URL on cancellation
- `metadata` (array|null): Optional metadata
- `title` (string|null): Flow title
- `description` (string|null): Flow description
- `flow_id` (string|null): Internal flow identifier (auto-generated if null)

**Usage**:
```php
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

$instructions = FormFlowInstructionsData::from([
    'reference_id' => 'order-123',
    'steps' => [...],
    'callbacks' => [...],
]);
```

---

### FormFlowStepData

**Namespace**: `LBHurtado\FormFlowManager\Data\FormFlowStepData`

**Properties**:
- `handler` (string): Handler name (e.g., 'location', 'signature')
- `config` (array): Handler-specific configuration
- `condition` (string|bool|null): Optional condition for step inclusion

**Usage**:
```php
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

$step = FormFlowStepData::from([
    'handler' => 'form',
    'config' => [
        'title' => 'Contact Information',
        'fields' => [...],
    ],
]);
```

---

## Webhook Callbacks

### on_complete Callback

**Trigger**: When all steps are completed

**Request**:
```http
POST https://your-app.test/callback
Content-Type: application/json

{
  "flow_id": "flow-abc123",
  "status": "completed",
  "collected_data": {
    "0": {},
    "1": {"mobile": "09171234567"},
    "2": {"selfie": "data:image/png;base64,..."}
  },
  "completed_at": "2026-02-03T13:00:00+00:00"
}
```

**Expected Response** (200 OK):
```json
{
  "success": true
}
```

**Error Handling**:
- Callback failures are logged but do not block flow completion
- Implement retry logic in your callback endpoint if needed

---

### on_cancel Callback

**Trigger**: When user cancels flow

**Request**:
```http
POST https://your-app.test/cancel-callback
Content-Type: application/json

{
  "flow_id": "flow-abc123",
  "status": "cancelled",
  "collected_data": {...},
  "cancelled_at": "2026-02-03T13:00:00+00:00"
}
```

---

## HTTP Status Codes

### Success Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | OK | GET requests, JSON responses, callback success |
| 201 | Created | Flow created (if using POST /start) |
| 204 | No Content | DELETE flow (cleanup) |
| 302 | Found | Redirect after step submission |

### Client Error Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 400 | Bad Request | Malformed request body |
| 404 | Not Found | Flow not found, invalid flow_id |
| 422 | Unprocessable Entity | Validation errors |
| 419 | Page Expired | CSRF token mismatch or session expired |

### Server Error Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 500 | Internal Server Error | Unexpected errors, handler crashes |

---

## Error Response Format

**Validation Errors** (422):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Error message 1",
      "Error message 2"
    ]
  }
}
```

**Generic Errors** (500):
```json
{
  "message": "Server Error"
}
```

**Development Mode** (with stack trace):
```json
{
  "message": "Call to undefined method...",
  "exception": "BadMethodCallException",
  "file": "/path/to/file.php",
  "line": 123,
  "trace": [...]
}
```

---

## Complete Flow Example

**Step 1: Server initiates flow**
```php
use LBHurtado\FormFlowManager\Services\{DriverService, FormFlowService};

$driverService = app(DriverService::class);
$formFlowService = app(FormFlowService::class);

// Load driver with context
$instructions = $driverService->loadDriver('voucher-redemption', [
    'reference_id' => "voucher-{$voucher->code}",
    'voucher' => $voucher,
    'amount' => 500,
    'has_kyc' => true,
]);

// Start flow
$state = $formFlowService->startFlow($instructions);

// Redirect user to flow URL
return redirect("/form-flow/{$state['flow_id']}");
```

**Step 2: User navigates flow (browser)**
```
GET /form-flow/flow-abc123
→ Renders Splash screen
→ User clicks "Start"

POST /form-flow/flow-abc123/step/0 {"data": {}}
→ Redirects to step 1

GET /form-flow/flow-abc123
→ Renders Form (mobile input)
→ User enters mobile

POST /form-flow/flow-abc123/step/1 {"data": {"mobile": "09171234567"}}
→ Redirects to step 2

... (more steps)

GET /form-flow/flow-abc123
→ All steps complete
→ Auto-completes flow
→ Triggers on_complete callback
→ Renders Complete page
```

**Step 3: Callback processes data**
```php
// routes/web.php
Route::post('/callback', function (Request $request) {
    $flowId = $request->input('flow_id');
    $collectedData = $request->input('collected_data');
    
    // Map data to domain model
    $mappedData = mapFlowDataToRedemption($collectedData);
    
    // Process (e.g., redeem voucher)
    ProcessRedemption::run($voucherCode, $mappedData);
    
    // Clean up session
    app(FormFlowService::class)->clearFlow($flowId);
    
    return response()->json(['success' => true]);
});
```

---

## Related Documentation

- [INTEGRATION.md](./INTEGRATION.md) - Complete integration guide
- [HANDLERS.md](./HANDLERS.md) - Handler development guide
- [ENV_VARS.md](./ENV_VARS.md) - Environment variables
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) - Common issues
- [README.md](./README.md) - Documentation index

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-03  
**Maintained By**: Development Team
