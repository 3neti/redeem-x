# Testing Location Handler

This guide shows how to test the Location Handler package with real credentials.

## Overview

The Location Handler provides a REST API via FormFlowController that you can test using:
1. **Artisan Commands** (recommended for quick testing)
2. **Browser/Postman** (for full UI testing)
3. **Automated Tests** (for CI/CD)

## Credential Management

### Required API Keys

The Location Handler uses external services that require API keys:

1. **OpenCage API** (geocoding - lat/lng → address)
   - Sign up: https://opencagedata.com/
   - Free tier: 2,500 requests/day
   - Get your key and add to `.env`

2. **Mapbox API** (static map images)
   - Sign up: https://account.mapbox.com/auth/signup/
   - Free tier: 50,000 requests/month
   - Get your key and add to `.env`

3. **Google Maps API** (alternative to Mapbox)
   - Sign up: https://console.cloud.google.com/
   - Enable: Static Maps API
   - Get your key and add to `.env`

### Add to `.env`

```bash
# OpenCage API Key (for geocoding)
VITE_OPENCAGE_KEY=your_opencage_key_here

# Mapbox API Key (for static map images)
VITE_MAPBOX_TOKEN=your_mapbox_token_here

# Google Maps API Key (alternative)
GOOGLE_MAPS_API_KEY=your_google_maps_key_here
```

### Update `.env.example`

Add these lines to `.env.example` for documentation:

```bash
# Location Handler Configuration
LOCATION_HANDLER_OPENCAGE_KEY="${VITE_OPENCAGE_KEY}"
LOCATION_HANDLER_MAP_PROVIDER=mapbox
LOCATION_HANDLER_MAPBOX_TOKEN="${VITE_MAPBOX_TOKEN}"
LOCATION_HANDLER_GOOGLE_MAPS_KEY="${GOOGLE_MAPS_API_KEY}"
LOCATION_HANDLER_CAPTURE_SNAPSHOT=true
LOCATION_HANDLER_REQUIRE_ADDRESS=false
```

## Quick Start Testing

### 1. Install & Configure

```bash
# Install the package (if not in mono-repo)
composer require lbhurtado/form-handler-location

# Publish config
php artisan vendor:publish --tag=location-handler-config

# Add your API keys to .env (see above)

# Clear config cache
php artisan config:clear
```

### 2. Create Test Command

Let me create an Artisan command for easy testing:

```bash
php artisan test:location-handler
```

This command will:
- Start a form flow with location step
- Return the flow_id and next URL
- Show you how to test via browser or API

## Testing Methods

### Method 1: Artisan Command (Recommended)

Create a test command to start a flow programmatically:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

class TestLocationHandler extends Command
{
    protected $signature = 'test:location-handler 
                            {--show-config : Show current configuration}';
    
    protected $description = 'Test the Location Handler with real credentials';
    
    public function handle(FormFlowService $flowService)
    {
        if ($this->option('show-config')) {
            $this->showConfig();
            return 0;
        }
        
        $this->info('🧪 Testing Location Handler...');
        $this->newLine();
        
        // Create test flow
        $instructions = FormFlowInstructionsData::from([
            'flow_id' => 'test-location-' . uniqid(),
            'title' => 'Test Location Capture',
            'description' => 'Testing location handler with real credentials',
            'steps' => [
                [
                    'handler' => 'location',
                    'config' => [
                        'opencage_api_key' => config('location-handler.opencage_api_key'),
                        'map_provider' => config('location-handler.map_provider'),
                        'mapbox_token' => config('location-handler.mapbox_token'),
                        'capture_snapshot' => true,
                        'require_address' => true,
                    ],
                ],
            ],
            'callbacks' => [
                'on_complete' => route('api.test.location.complete'),
            ],
        ]);
        
        $state = $flowService->startFlow($instructions);
        
        $this->info('✅ Flow started successfully!');
        $this->newLine();
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Flow ID', $state['flow_id']],
                ['Status', $state['status']],
                ['Current Step', $state['current_step']],
                ['API Endpoint', url("/form-flow/{$state['flow_id']}/step/0")],
            ]
        );
        
        $this->newLine();
        $this->comment('📍 Next Steps:');
        $this->line('1. Open browser: ' . url("/form-flow/{$state['flow_id']}"));
        $this->line('2. Or test via API (see below)');
        $this->newLine();
        
        $this->comment('📡 API Testing Example:');
        $this->line('');
        $this->line('curl -X POST \\');
        $this->line("  " . url("/form-flow/{$state['flow_id']}/step/0") . " \\");
        $this->line("  -H 'Content-Type: application/json' \\");
        $this->line("  -d '{");
        $this->line('    "data": {');
        $this->line('      "latitude": 14.5995,');
        $this->line('      "longitude": 120.9842,');
        $this->line('      "timestamp": "' . now()->toIso8601String() . '",');
        $this->line('      "formatted_address": "Manila, Philippines"');
        $this->line('    }');
        $this->line("  }'");
        $this->newLine();
        
        return 0;
    }
    
    protected function showConfig(): void
    {
        $this->info('🔧 Location Handler Configuration');
        $this->newLine();
        
        $config = [
            ['OpenCage Key', config('location-handler.opencage_api_key') ? '✅ Configured' : '❌ Missing'],
            ['Map Provider', config('location-handler.map_provider', 'not set')],
            ['Mapbox Token', config('location-handler.mapbox_token') ? '✅ Configured' : '⚠️  Optional'],
            ['Google Maps Key', config('location-handler.google_maps_api_key') ? '✅ Configured' : '⚠️  Optional'],
            ['Capture Snapshot', config('location-handler.capture_snapshot') ? 'Yes' : 'No'],
            ['Require Address', config('location-handler.require_address') ? 'Yes' : 'No'],
        ];
        
        $this->table(['Setting', 'Value'], $config);
        
        if (!config('location-handler.opencage_api_key')) {
            $this->newLine();
            $this->error('⚠️  OpenCage API key is missing!');
            $this->line('Add to .env: VITE_OPENCAGE_KEY=your_key_here');
        }
    }
}
```

### Method 2: Browser Testing

1. Start your dev server:
```bash
composer dev
```

2. Open your browser's console and run:
```javascript
// Start a flow
fetch('/form-flow/start', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        flow_id: 'test-location-' + Date.now(),
        steps: [
            {
                handler: 'location',
                config: {
                    capture_snapshot: true,
                    require_address: true
                }
            }
        ]
    })
})
.then(r => r.json())
.then(data => {
    console.log('Flow started:', data);
    // Navigate to the flow
    window.location.href = data.next_url;
});
```

### Method 3: Postman/cURL

1. **Start Flow**
```bash
curl -X POST http://localhost:8000/form-flow/start \
  -H "Content-Type: application/json" \
  -d '{
    "flow_id": "test-location-123",
    "steps": [
      {
        "handler": "location",
        "config": {
          "capture_snapshot": true,
          "require_address": true
        }
      }
    ],
    "callbacks": {
      "on_complete": "http://localhost:8000/api/test/callback"
    }
  }'
```

2. **Get Flow State**
```bash
curl http://localhost:8000/form-flow/test-location-123
```

3. **Submit Location Data**
```bash
curl -X POST http://localhost:8000/form-flow/test-location-123/step/0 \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "latitude": 14.5995,
      "longitude": 120.9842,
      "timestamp": "2025-12-11T13:50:00Z",
      "accuracy": 10.5,
      "formatted_address": "Manila, Philippines",
      "address_components": {
        "city": "Manila",
        "country": "Philippines"
      },
      "snapshot": "data:image/png;base64,..."
    }
  }'
```

4. **Complete Flow**
```bash
curl -X POST http://localhost:8000/form-flow/test-location-123/complete
```

## Verifying API Keys Work

### Test OpenCage API
```bash
curl "https://api.opencagedata.com/geocode/v1/json?q=14.5995,120.9842&key=YOUR_KEY"
```

Expected response:
```json
{
  "results": [
    {
      "formatted": "Manila, Philippines",
      "components": {
        "city": "Manila",
        "country": "Philippines"
      }
    }
  ]
}
```

### Test Mapbox API
```bash
curl "https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/120.9842,14.5995,16,0/600x300@2x?access_token=YOUR_TOKEN"
```

Should return a PNG image.

### Test Google Maps API
```bash
curl "https://maps.googleapis.com/maps/api/staticmap?center=14.5995,120.9842&zoom=16&size=600x300&markers=color:red%7C14.5995,120.9842&key=YOUR_KEY"
```

Should return a PNG image.

## Troubleshooting

### Issue: "OpenCage API key missing"
**Solution**: Add `VITE_OPENCAGE_KEY` to `.env`

### Issue: "Map not loading"
**Solutions**:
1. Check if Mapbox token is valid
2. Try Google Maps as fallback (no key needed for basic usage)
3. Disable snapshot: `capture_snapshot: false`

### Issue: "CORS errors"
**Solution**: External APIs are called client-side. Ensure your browser allows the requests.

### Issue: "Permission denied"
**Solution**: Browser must be on HTTPS (or localhost) for geolocation to work.

## Best Practices

### 1. **Use Environment Variables**
Never hardcode API keys in code or config files.

### 2. **Rate Limiting**
- OpenCage: 2,500 requests/day (free tier)
- Mapbox: 50,000 requests/month (free tier)
- Cache location data when possible

### 3. **Fallback Strategy**
```php
'map_provider' => env('LOCATION_HANDLER_MAP_PROVIDER', 'google'), // Google works without key
'capture_snapshot' => env('LOCATION_HANDLER_CAPTURE_SNAPSHOT', false), // Disable if no key
```

### 4. **Security**
- Use HTTPS in production
- Restrict API keys to your domain
- Monitor usage via provider dashboards

## Next Steps

1. **Complete Setup**: Run Steps 4-6 from Phase 2
2. **Create Test Route**: Add `/test/location` route for easy testing
3. **Write Tests**: Add PHPUnit tests with mocked API responses
4. **Document**: Update main README with usage examples

## Related Files

- Config: `packages/form-handler-location/config/location-handler.php`
- Handler: `packages/form-handler-location/src/LocationHandler.php`
- Component: `packages/form-handler-location/resources/js/components/LocationCapture.vue`
- Routes: `packages/form-flow-manager/routes/form-flow.php`
