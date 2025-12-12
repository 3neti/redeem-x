# Plugin Architecture

## Overview

The Form Flow Manager uses a **plugin-based architecture** where handlers can be installed as separate packages. This allows the core to remain lightweight while supporting extensibility.

## Core Concepts

### Core Package (form-flow-manager)
- **Orchestrates** multi-step flows
- **Manages** state and navigation
- **Provides** the `FormHandlerInterface` contract
- **Discovers** plugins automatically
- **Built-in handler**: `form` (basic inputs)

### Plugin Packages (form-handler-*)
- **Implement** `FormHandlerInterface`
- **Self-register** via service providers
- **Independent** packages (optional dependencies)
- **Examples**: location, selfie, signature, kyc

## How Plugin Discovery Works

```
Host App Install
    ↓
composer require lbhurtado/form-handler-location
    ↓
Laravel Package Discovery
    ↓ (reads plugin's composer.json)
LocationHandlerServiceProvider boots
    ↓
registerHandler() updates config
    ↓
FormFlowController reads config
    ↓
'location' handler available!
```

## Creating a New Plugin

### 1. Package Structure

```
form-handler-{name}/
├── composer.json
├── src/
│   ├── {Name}Handler.php          # Implements FormHandlerInterface
│   └── {Name}HandlerServiceProvider.php
├── config/
│   └── {name}-handler.php
├── resources/js/
│   └── {Name}CapturePage.vue      # Frontend component
└── tests/
    └── Unit/
        └── {Name}HandlerTest.php
```

### 2. Implement FormHandlerInterface

```php
<?php

namespace LBHurtado\FormHandler{Name};

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

class {Name}Handler implements FormHandlerInterface
{
    public function getName(): string
    {
        return '{name}';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Extract and validate data
        $inputData = $request->input('data', $request->all());
        
        $validated = validator($inputData, [
            // Your validation rules
        ])->validate();
        
        // Transform/process data
        $validated['timestamp'] = now()->toIso8601String();
        
        return $validated;
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Validation logic
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        return Inertia::render('{Name}CapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'config' => array_merge([
                // Default config
            ], $step->config),
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            // Config validation schema
        ];
    }
}
```

### 3. Create Service Provider

```php
<?php

namespace LBHurtado\FormHandler{Name};

use Illuminate\Support\ServiceProvider;

class {Name}HandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/{name}-handler.php',
            '{name}-handler'
        );
        
        // Register handler as singleton
        $this->app->singleton({Name}Handler::class, function ($app) {
            return new {Name}Handler();
        });
    }
    
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/{name}-handler.php' => config_path('{name}-handler.php'),
        ], '{name}-handler-config');
        
        // Publish frontend assets
        $this->publishes([
            __DIR__.'/../resources/js/{Name}' => resource_path('js/{Name}'),
        ], '{name}-handler-views');
        
        // Auto-register handler
        $this->registerHandler();
    }
    
    protected function registerHandler(): void
    {
        $handlers = config('form-flow.handlers', []);
        $handlers['{name}'] = {Name}Handler::class;
        config(['form-flow.handlers' => $handlers]);
    }
}
```

### 4. Configure composer.json

```json
{
  "name": "lbhurtado/form-handler-{name}",
  "type": "library",
  "require": {
    "php": "^8.2",
    "lbhurtado/form-flow-manager": "dev-main",
    "spatie/laravel-data": "^4.0"
  },
  "autoload": {
    "psr-4": {
      "LBHurtado\\FormHandler{Name}\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "LBHurtado\\FormHandler{Name}\\{Name}HandlerServiceProvider"
      ]
    }
  }
}
```

### 5. Write Tests

```php
<?php

use LBHurtado\FormHandler{Name}\{Name}Handler;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use Illuminate\Http\Request;

test('{name} handler implements form handler interface', function () {
    $handler = new {Name}Handler();
    
    expect($handler)->toBeInstanceOf(
        \LBHurtado\FormFlowManager\Contracts\FormHandlerInterface::class
    );
});

test('{name} handler returns correct name', function () {
    $handler = new {Name}Handler();
    expect($handler->getName())->toBe('{name}');
});

// Add more tests...
```

## Using Plugins in Host App

### Installation

```bash
composer require lbhurtado/form-handler-location
```

That's it! The plugin automatically:
- ✅ Registers its service provider
- ✅ Makes handler available
- ✅ Can be used in flows

### Usage in Flow

```javascript
// public/form-flow-demo.html or any frontend
const response = await fetch('/form-flow/start', {
    method: 'POST',
    body: JSON.stringify({
        reference_id: 'unique-id',
        steps: [
            {
                handler: 'form',  // Built-in
                config: { /* ... */ }
            },
            {
                handler: 'location',  // Plugin!
                config: {
                    require_address: true,
                    capture_snapshot: true
                }
            }
        ],
        callbacks: {
            on_complete: 'https://app.test/callback'
        }
    })
});
```

## Available Handlers

### Built-in
- **form** - Basic inputs (text, email, date, number, select, checkbox, textarea, file)

### Plugins
- **location** - GPS coordinates, reverse geocoding, map snapshots
- **selfie** - Camera capture for identity verification
- **signature** - Digital signature capture
- **kyc** - Know Your Customer verification (HyperVerge integration)

## Plugin Dependencies

### Core Should NOT Know Plugins

```json
// ❌ BAD: Core depends on plugins
{
  "require": {
    "lbhurtado/form-handler-location": "@dev"
  }
}
```

```json
// ✅ GOOD: Plugins in require-dev for testing only
{
  "require-dev": {
    "lbhurtado/form-handler-location": "dev-main"
  }
}
```

### Host App Chooses Plugins

```json
// ✅ Host app decides what to install
{
  "require": {
    "lbhurtado/form-flow-manager": "@dev",
    "lbhurtado/form-handler-location": "@dev",
    "lbhurtado/form-handler-selfie": "@dev"
  }
}
```

## Architecture Benefits

✅ **Lightweight Core** - Only install what you need  
✅ **Extensible** - Add handlers without modifying core  
✅ **Testable** - Each plugin tested independently  
✅ **Reusable** - Plugins work across different apps  
✅ **Maintainable** - Clear separation of concerns

## Example: Real Plugin (form-handler-location)

**Package:** `packages/form-handler-location/`

**Handler:** `LocationHandler.php`
- Captures GPS coordinates
- Reverse geocodes to address
- Generates map snapshots

**Auto-registers as:** `'location' => LocationHandler::class`

**Usage:**
```javascript
{
  handler: 'location',
  config: {
    require_address: true,
    capture_snapshot: true,
    map_provider: 'google'
  }
}
```

## Troubleshooting

### Handler Not Found

**Error:** "Handler not found: location"

**Check:**
1. Package installed? `composer show lbhurtado/form-handler-location`
2. Service provider registered? Check `config/app.php` or run `php artisan package:discover`
3. Config cached? `php artisan config:clear`

### Handler Not Auto-Registering

**Check service provider:**
```php
protected function registerHandler(): void
{
    $handlers = config('form-flow.handlers', []);
    $handlers['location'] = LocationHandler::class;
    config(['form-flow.handlers' => $handlers]);
}
```

Must be called in `boot()` method.

## See Also

- [FormHandlerInterface](src/Contracts/FormHandlerInterface.php)
- [Built-in FormHandler](src/Handlers/FormHandler.php)
- [Example: LocationHandler](../form-handler-location/src/LocationHandler.php)
