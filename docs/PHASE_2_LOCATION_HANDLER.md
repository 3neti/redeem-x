# Phase 2: Location Handler Package - Implementation Guide

## Context

**Status**: Phase 0 & 1 Complete, Phase 2 In Progress
- Phase 0: Driver/mapping system (47 tests passing)
- Phase 1: Core Manager Package (54 tests passing)
- Phase 2: Extract Location Handler into standalone package

## Objective

Extract location capture logic from the monolithic voucher redemption flow into a reusable, standalone handler package that implements `FormHandlerInterface`.

## Current State Analysis

### Existing Implementation
**File**: `app/Http/Controllers/Redeem/RedeemController.php`
```php
public function location(Voucher $voucher): Response
{
    return Inertia::render('redeem/Location', [
        'voucher_code' => $voucher->code,
    ]);
}
```

**File**: `resources/js/pages/redeem/Location.vue` (300+ lines)
- Uses `useBrowserLocation` composable
- Captures GPS coordinates via browser Geolocation API
- Reverse geocodes using OpenCage API
- Generates static map snapshot (Mapbox/Google Maps)
- Stores map snapshot as base64 in location data
- Tightly coupled to voucher redemption flow
- Hardcoded navigation to selfie/signature/finalize pages

### Dependencies to Extract
- `@/composables/useBrowserLocation` - GPS capture & geocoding
- `@/components/GeoPermissionAlert.vue` - Permission denied UI
- Map snapshot capture logic
- OpenCage API integration (env: `VITE_OPENCAGE_KEY`)
- Mapbox API integration (env: `VITE_MAPBOX_TOKEN`)

## Package Structure

```
packages/form-handler-location/
├── composer.json
├── README.md
├── src/
│   ├── LocationHandler.php          # Main handler class
│   ├── Data/
│   │   └── LocationData.php         # Location DTO
│   ├── Http/
│   │   └── Controllers/
│   │       └── LocationController.php
│   └── LocationHandlerServiceProvider.php
├── config/
│   └── location-handler.php         # Config: API keys, map providers
├── resources/
│   └── js/
│       ├── components/
│       │   ├── LocationCapture.vue  # Generic location UI
│       │   └── GeoPermissionAlert.vue
│       └── composables/
│           └── useBrowserLocation.js
└── tests/
    ├── Unit/
    │   └── LocationHandlerTest.php
    └── Feature/
        └── LocationCaptureTest.php
```

## Implementation Steps

### Step 1: Create Package composer.json

```json
{
    "name": "lbhurtado/form-handler-location",
    "description": "Location capture handler for form flow system",
    "type": "library",
    "require": {
        "php": "^8.2",
        "lbhurtado/form-flow-manager": "dev-main"
    },
    "require-dev": {
        "orchestra/testbench": "^10.3",
        "pestphp/pest": "^3.8"
    },
    "autoload": {
        "psr-4": {
            "LBHurtado\\FormHandlerLocation\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "LBHurtado\\FormHandlerLocation\\LocationHandlerServiceProvider"
            ]
        }
    }
}
```

### Step 2: Create LocationData DTO

```php
<?php

namespace LBHurtado\FormHandlerLocation\Data;

use Spatie\LaravelData\Data;

class LocationData extends Data
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $formatted_address = null,
        public ?array $address_components = null,
        public ?string $snapshot = null, // base64 map image
        public ?string $timestamp = null,
        public ?float $accuracy = null,
    ) {}
}
```

### Step 3: Create LocationHandler implementing FormHandlerInterface

```php
<?php

namespace LBHurtado\FormHandlerLocation;

use Illuminate\Http\Request;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerLocation\Data\LocationData;
use Inertia\Inertia;

class LocationHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'location';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'formatted_address' => 'nullable|string',
            'address_components' => 'nullable|array',
            'snapshot' => 'nullable|string', // base64
            'accuracy' => 'nullable|numeric',
        ]);
        
        return LocationData::from($validated)->toArray();
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Validation already done in handle()
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        return Inertia::render('FormHandlerLocation::LocationCapture', [
            'config' => $step->config,
            'flow_id' => $context['flow_id'] ?? null,
            'step_index' => $context['step_index'] ?? 0,
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'opencage_api_key' => 'required|string',
            'map_provider' => 'required|in:mapbox,google',
            'mapbox_token' => 'required_if:map_provider,mapbox',
            'capture_snapshot' => 'boolean',
            'require_address' => 'boolean',
        ];
    }
}
```

### Step 4: Create Generic LocationCapture.vue

**Key Changes from Original**:
- Remove voucher-specific code
- Accept `flowId`, `stepIndex` as props
- Use FormFlowService API instead of redemption API
- Remove hardcoded navigation logic
- Make it emit events instead of direct navigation

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

interface Props {
    config: {
        opencage_api_key: string;
        map_provider: 'mapbox' | 'google';
        mapbox_token?: string;
        capture_snapshot?: boolean;
        require_address?: boolean;
    };
    flow_id: string;
    step_index: number;
}

const props = defineProps<Props>();

// Use useBrowserLocation with props.config.opencage_api_key
// Capture location
// Generate snapshot based on props.config.map_provider
// Submit to /form-flow/{flow_id}/step/{step_index}
</script>
```

### Step 5: Create LocationHandlerServiceProvider

```php
<?php

namespace LBHurtado\FormHandlerLocation;

use Illuminate\Support\ServiceProvider;
use LBHurtado\FormFlowManager\Services\FormFlowService;

class LocationHandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/location-handler.php', 'location-handler'
        );
        
        // Register handler
        $this->app->singleton(LocationHandler::class);
    }
    
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/location-handler.php' => config_path('location-handler.php'),
        ], 'location-handler-config');
        
        // Publish assets
        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/form-handler-location'),
        ], 'location-handler-assets');
    }
}
```

### Step 6: Create config/location-handler.php

```php
<?php

return [
    'opencage_api_key' => env('OPENCAGE_API_KEY'),
    'map_provider' => env('MAP_PROVIDER', 'google'),
    'mapbox_token' => env('MAPBOX_TOKEN'),
    'capture_snapshot' => env('LOCATION_CAPTURE_SNAPSHOT', true),
    'require_address' => env('LOCATION_REQUIRE_ADDRESS', false),
    'cache_duration' => env('LOCATION_CACHE_DURATION', 180), // seconds
];
```

### Step 7: Write Tests

```php
<?php

use LBHurtado\FormHandlerLocation\LocationHandler;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

it('returns correct handler name', function () {
    $handler = new LocationHandler();
    expect($handler->getName())->toBe('location');
});

it('validates and handles location data', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [],
    ]);
    
    $request = Request::create('/test', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'formatted_address' => 'Manila, Philippines',
    ]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toHaveKey('latitude');
    expect($result['latitude'])->toBe(14.5995);
});
```

## Integration with Form Flow System

### Update voucher-redemption.yaml Driver

```yaml
driver:
  name: "voucher-redemption"
  version: "1.0"
  source: "LBHurtado\\Voucher\\Data\\VoucherInstructionsData"
  target: "LBHurtado\\FormFlowManager\\Data\\FormFlowInstructionsData"

mappings:
  flow_id: "voucher_{{ source.code }}"
  
  steps:
    source: "instructions.inputs.fields"
    transform: "array_map"
    handler:
      handler: "{{ item }}"
      config:
        # For location handler
        when: "{{ item }} == 'location'"
        then:
          opencage_api_key: "{{ config('location-handler.opencage_api_key') }}"
          map_provider: "{{ config('location-handler.map_provider') }}"
          capture_snapshot: true
      priority: "{{ constants.priorities[item] }}"
      required: "{{ item in source.required_inputs }}"

constants:
  priorities:
    location: 10
    selfie: 20
    signature: 30
    kyc: 40
```

### Register Handler in form-flow.php

```php
return [
    'handlers' => [
        'location' => \LBHurtado\FormHandlerLocation\LocationHandler::class,
    ],
];
```

## Testing Checklist

- [ ] Handler returns correct name
- [ ] Handler validates coordinates
- [ ] Handler rejects invalid lat/lng
- [ ] Handler accepts optional fields
- [ ] Config schema is correct
- [ ] ServiceProvider registers handler
- [ ] Vue component captures location
- [ ] Map snapshot generates correctly
- [ ] Integration with FormFlowService works

## Migration Path

1. **Install package** in main app
2. **Publish assets** and config
3. **Update driver** config
4. **Test in isolation** (unit tests)
5. **Test integration** with voucher flow
6. **Feature flag** old vs new
7. **Gradual rollout**
8. **Deprecate old** RedeemController::location()

## Common Pitfalls

1. **Don't hardcode navigation** - Let FormFlowService handle it
2. **Don't assume voucher context** - Use generic flow_id
3. **Don't duplicate useBrowserLocation** - Keep it in the package
4. **Don't forget API keys** - Make them configurable
5. **Don't skip snapshot in tests** - Mock map APIs

## Next Steps

After Location Handler is complete:
- Phase 3: Selfie Handler
- Phase 4: Signature Handler  
- Phase 5: KYC Handler (HyperVerge integration)

Each follows the same pattern established here.
