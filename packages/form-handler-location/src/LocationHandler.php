<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerLocation;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerLocation\Data\LocationData;

/**
 * Location Handler
 * 
 * Captures user's geographic location using browser geolocation API.
 * Supports reverse geocoding and map snapshots.
 */
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
            'formatted_address' => 'nullable|string|max:500',
            'address_components' => 'nullable|array',
            'snapshot' => 'nullable|string', // base64 encoded image
            'accuracy' => 'nullable|numeric|min:0',
        ]);
        
        $validated['timestamp'] = now()->toIso8601String();
        
        return LocationData::from($validated)->toArray();
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Validation handled in handle() method
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        // Note: After publishing assets, the component is at:
        // resources/js/vendor/form-handler-location/pages/LocationCapturePage.vue
        // But we need to copy it to resources/js/pages/ for Inertia to find it
        
        return Inertia::render('Vendor/FormHandlerLocation/LocationCapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'config' => array_merge([
                'opencage_api_key' => config('location-handler.opencage_api_key'),
                'map_provider' => config('location-handler.map_provider', 'google'),
                'mapbox_token' => config('location-handler.mapbox_token'),
                'google_maps_api_key' => config('location-handler.google_maps_api_key'),
                'capture_snapshot' => config('location-handler.capture_snapshot', true),
                'require_address' => config('location-handler.require_address', false),
            ], $step->config),
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'opencage_api_key' => 'nullable|string',
            'map_provider' => 'required|in:mapbox,google',
            'mapbox_token' => 'required_if:map_provider,mapbox|nullable|string',
            'capture_snapshot' => 'boolean',
            'require_address' => 'boolean',
        ];
    }
}
