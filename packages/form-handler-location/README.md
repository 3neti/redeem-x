# Location Handler Plugin

A Form Flow Manager plugin for capturing user location using browser geolocation API.

## Features

✅ Browser geolocation capture (GPS coordinates)  
✅ Reverse geocoding (coordinates → address)  
✅ Map snapshot generation (Google Maps / Mapbox)  
✅ Address component extraction (street, city, region, etc.)  
✅ Accuracy measurement  
✅ Auto-registration with Form Flow Manager

## Installation

```bash
composer require lbhurtado/form-handler-location
```

That's it! The handler automatically registers itself with the Form Flow Manager.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=location-handler-config
```

Edit `config/location-handler.php`:

```php
return [
    'opencage_api_key' => env('VITE_OPENCAGE_KEY'),  // For reverse geocoding
    'map_provider' => env('LOCATION_HANDLER_MAP_PROVIDER', 'google'),
    'mapbox_token' => env('VITE_MAPBOX_TOKEN'),
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    'capture_snapshot' => true,
    'require_address' => false,
];
```

### Environment Variables

Add to `.env`:

```bash
# OpenCage API (reverse geocoding)
VITE_OPENCAGE_KEY=your_opencage_api_key

# Map Provider (optional)
LOCATION_HANDLER_MAP_PROVIDER=google  # or 'mapbox'

# Mapbox (if using Mapbox)
VITE_MAPBOX_TOKEN=your_mapbox_token

# Google Maps (optional, for static maps)
GOOGLE_MAPS_API_KEY=your_google_maps_key
```

## Usage

### In a Form Flow

```javascript
const response = await fetch('/form-flow/start', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        reference_id: 'unique-id',
        steps: [
            {
                handler: 'location',
                config: {
                    title: 'Your Location',
                    description: 'We need your location to continue',
                    require_address: true,
                    capture_snapshot: true,
                    map_provider: 'google'
                }
            }
        ],
        callbacks: {
            on_complete: 'https://your-app.test/callback'
        }
    })
});
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `require_address` | boolean | `false` | Require reverse geocoded address |
| `capture_snapshot` | boolean | `true` | Capture map image as base64 |
| `map_provider` | string | `'google'` | Map provider: `'google'` or `'mapbox'` |
| `title` | string | - | Custom step title |
| `description` | string | - | Custom step description |

## Collected Data

The handler returns the following data structure:

```php
[
    'latitude' => 14.5995,
    'longitude' => 120.9842,
    'formatted_address' => 'Makati City, Metro Manila, Philippines',
    'address_components' => [
        'street' => 'Ayala Avenue',
        'barangay' => 'Poblacion',
        'city' => 'Makati City',
        'region' => 'Metro Manila',
        'country' => 'Philippines',
        'postal_code' => '1200'
    ],
    'snapshot' => 'data:image/png;base64,iVBORw0KG...',  // Map image
    'accuracy' => 10.5,  // meters
    'timestamp' => '2024-12-12T10:30:00+08:00'
]
```

## Testing

```bash
cd packages/form-handler-location
composer test
```

### Test Coverage

- ✅ Interface implementation
- ✅ Coordinate validation
- ✅ Address handling
- ✅ Map snapshot support
- ✅ Accuracy metrics
- ✅ Config schema
- ✅ Inertia rendering

## How It Works

### 1. Plugin Auto-Registration

```php
// LocationHandlerServiceProvider::boot()
protected function registerHandler(): void
{
    $handlers = config('form-flow.handlers', []);
    $handlers['location'] = LocationHandler::class;
    config(['form-flow.handlers' => $handlers]);
}
```

### 2. Browser Geolocation

The Vue component (`LocationCapturePage.vue`) uses:

```javascript
navigator.geolocation.getCurrentPosition((position) => {
    const coords = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy
    };
    // Submit to handler...
});
```

### 3. Reverse Geocoding

Uses OpenCage API to convert coordinates to address:

```
(14.5995, 120.9842) → "Makati City, Metro Manila, Philippines"
```

### 4. Map Snapshot

Generates static map image via Google Maps or Mapbox API.

## Architecture

This is a **plugin package** for Form Flow Manager:

```
form-handler-location/     (Plugin)
├── Implements FormHandlerInterface
├── Self-registers via service provider
└── Optional dependency

form-flow-manager/         (Core)
├── Discovers plugins automatically
└── Orchestrates flow with registered handlers

redeem-x/                  (Host App)
└── Installs: core + chosen plugins
```

### Plugin Benefits

✅ **Optional** - Install only if needed  
✅ **Independent** - Tested separately  
✅ **Reusable** - Works across different apps  
✅ **Maintainable** - Clean separation of concerns

## Requirements

- PHP 8.2+
- Laravel 12+
- Form Flow Manager (`lbhurtado/form-flow-manager`)
- Browser with geolocation support

## API Keys

### OpenCage (Free Tier)
- Sign up: https://opencagedata.com/
- Free: 2,500 requests/day
- Used for: Reverse geocoding

### Mapbox (Optional)
- Sign up: https://account.mapbox.com/
- Free: 50,000 requests/month
- Used for: Static map images

### Google Maps (Optional)
- Sign up: https://console.cloud.google.com/
- Free: Basic usage (with limits)
- Used for: Static map images

## Troubleshooting

### "Handler not found: location"

**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

### Location not captured

**Check:**
1. HTTPS enabled? (required for geolocation)
2. User granted permission?
3. Browser supports geolocation?

### Reverse geocoding fails

**Check:**
1. OpenCage API key configured?
2. API key valid?
3. Request quota not exceeded?

## Related Packages

- [form-flow-manager](../form-flow-manager) - Core orchestration
- [form-handler-selfie](../form-handler-selfie) - Camera capture
- [form-handler-signature](../form-handler-signature) - Digital signature
- [form-handler-kyc](../form-handler-kyc) - Identity verification

## License

Proprietary

## Author

Lester Hurtado <lester@hurtado.ph>
