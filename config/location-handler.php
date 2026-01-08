<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenCage API Key
    |--------------------------------------------------------------------------
    |
    | API key for OpenCage Geocoding service (reverse geocoding).
    | Sign up at: https://opencagedata.com/
    | Free tier: 2,500 requests/day
    |
    */
    'opencage_api_key' => env('VITE_OPENCAGE_KEY', env('OPENCAGE_API_KEY')),
    
    /*
    |--------------------------------------------------------------------------
    | Map Provider
    |--------------------------------------------------------------------------
    |
    | The map provider to use for static map images.
    | Options: 'mapbox', 'google'
    |
    | - Mapbox: Requires token, 50,000 requests/month free
    | - Google: Requires API key (no free tier without key due to ORB blocking)
    |
    */
    'map_provider' => env('LOCATION_HANDLER_MAP_PROVIDER', 'mapbox'),
    
    /*
    |--------------------------------------------------------------------------
    | Mapbox Access Token
    |--------------------------------------------------------------------------
    |
    | Access token for Mapbox Static Images API.
    | Sign up at: https://account.mapbox.com/auth/signup/
    | Free tier: 50,000 requests/month
    |
    */
    'mapbox_token' => env('VITE_MAPBOX_TOKEN', env('MAPBOX_TOKEN')),
    
    /*
    |--------------------------------------------------------------------------
    | Google Maps API Key
    |--------------------------------------------------------------------------
    |
    | API key for Google Maps Static API (optional).
    | Basic usage works without key, but has rate limits.
    | For production, get a key: https://console.cloud.google.com/
    |
    */
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    
    /*
    |--------------------------------------------------------------------------
    | Capture Snapshot
    |--------------------------------------------------------------------------
    |
    | Whether to capture a base64-encoded snapshot of the static map.
    | Useful for storing a visual representation of the location.
    |
    */
    'capture_snapshot' => env('LOCATION_HANDLER_CAPTURE_SNAPSHOT', true),
    
    /*
    |--------------------------------------------------------------------------
    | Require Address
    |--------------------------------------------------------------------------
    |
    | Whether to require a formatted address (via geocoding).
    | If true, location capture will fail if address cannot be determined.
    |
    */
    'require_address' => env('LOCATION_HANDLER_REQUIRE_ADDRESS', false),
    
    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long to cache location data in browser sessionStorage (seconds).
    | Default: 300 seconds (5 minutes)
    |
    */
    'cache_duration' => env('LOCATION_HANDLER_CACHE_DURATION', 300),
];
