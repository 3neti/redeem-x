<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

class TestLocationHandler extends Command
{
    protected $signature = 'test:location-handler 
                            {--show-config : Show current configuration}
                            {--api : Show API testing examples}';
    
    protected $description = 'Test the Location Handler with real credentials';
    
    public function handle(FormFlowService $flowService)
    {
        if ($this->option('show-config')) {
            $this->showConfig();
            return 0;
        }
        
        if ($this->option('api')) {
            $this->showApiExamples();
            return 0;
        }
        
        $this->info('🧪 Testing Location Handler...');
        $this->newLine();
        
        // Check if package is installed
        if (!class_exists(\LBHurtado\FormHandlerLocation\LocationHandler::class)) {
            $this->error('❌ Location Handler package not installed!');
            $this->line('Run: composer require lbhurtado/form-handler-location');
            return 1;
        }
        
        // Check credentials
        $opencageKey = env('VITE_OPENCAGE_KEY');
        $mapboxToken = env('VITE_MAPBOX_TOKEN');
        
        if (!$opencageKey) {
            $this->warn('⚠️  VITE_OPENCAGE_KEY not set in .env');
            $this->line('Geocoding (address lookup) will not work.');
            $this->newLine();
        }
        
        if (!$mapboxToken) {
            $this->warn('⚠️  VITE_MAPBOX_TOKEN not set in .env');
            $this->line('Using Google Maps as fallback (basic usage, no key needed).');
            $this->newLine();
        }
        
        // Create test flow
        try {
            $flowId = 'test-location-' . uniqid();
            
            $instructions = FormFlowInstructionsData::from([
                'flow_id' => $flowId,
                'title' => 'Test Location Capture',
                'description' => 'Testing location handler with real credentials',
                'steps' => [
                    [
                        'handler' => 'location',
                        'config' => [
                            'opencage_api_key' => $opencageKey,
                            'map_provider' => $mapboxToken ? 'mapbox' : 'google',
                            'mapbox_token' => $mapboxToken,
                            'capture_snapshot' => true,
                            'require_address' => false,
                        ],
                    ],
                ],
                'callbacks' => [
                    'on_complete' => url('/api/test/location/complete'),
                    'on_cancel' => url('/api/test/location/cancel'),
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
                    ['Total Steps', count($state['instructions']['steps'])],
                ]
            );
            
            $this->newLine();
            $this->comment('📍 Testing Options:');
            $this->newLine();
            
            $this->line('1️⃣  Browser Testing (Recommended):');
            $this->line('   Open: ' . url("/form-flow/{$flowId}"));
            $this->line('   Then use your browser\'s geolocation to capture location.');
            $this->newLine();
            
            $this->line('2️⃣  API Testing:');
            $this->line('   Run: php artisan test:location-handler --api');
            $this->newLine();
            
            $this->line('3️⃣  Check Flow State:');
            $this->line('   curl ' . url("/form-flow/{$flowId}"));
            $this->newLine();
            
            // Show how to view session data
            $this->comment('💡 Debug Tips:');
            $this->line('• Session key: form_flow_' . $flowId);
            $this->line('• Check logs: storage/logs/laravel.log');
            $this->line('• Tinker: php artisan tinker → session()->get(\'form_flow_' . $flowId . '\')');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to start flow: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    protected function showConfig(): void
    {
        $this->info('🔧 Location Handler Configuration');
        $this->newLine();
        
        $opencageKey = env('VITE_OPENCAGE_KEY');
        $mapboxToken = env('VITE_MAPBOX_TOKEN');
        $googleKey = env('GOOGLE_MAPS_API_KEY');
        
        $config = [
            ['OpenCage Key', $opencageKey ? '✅ ' . substr($opencageKey, 0, 10) . '...' : '❌ Not set'],
            ['Mapbox Token', $mapboxToken ? '✅ ' . substr($mapboxToken, 0, 10) . '...' : '⚠️  Not set (will use Google)'],
            ['Google Maps Key', $googleKey ? '✅ ' . substr($googleKey, 0, 10) . '...' : '⚠️  Not set (basic usage)'],
        ];
        
        $this->table(['Setting', 'Value'], $config);
        $this->newLine();
        
        if (!$opencageKey) {
            $this->error('⚠️  OpenCage API key is missing!');
            $this->line('1. Sign up: https://opencagedata.com/');
            $this->line('2. Get your API key');
            $this->line('3. Add to .env: VITE_OPENCAGE_KEY=your_key_here');
            $this->newLine();
        }
        
        if (!$mapboxToken) {
            $this->warn('⚠️  Mapbox token is optional but recommended');
            $this->line('1. Sign up: https://account.mapbox.com/auth/signup/');
            $this->line('2. Get your access token');
            $this->line('3. Add to .env: VITE_MAPBOX_TOKEN=your_token_here');
            $this->newLine();
        }
        
        $this->comment('📖 Full guide: docs/TESTING_LOCATION_HANDLER.md');
    }
    
    protected function showApiExamples(): void
    {
        $this->info('📡 API Testing Examples');
        $this->newLine();
        
        $flowId = 'test-location-' . time();
        $baseUrl = url('');
        
        $this->comment('1. Start a Flow:');
        $this->line('');
        $this->line('curl -X POST ' . $baseUrl . '/form-flow/start \\');
        $this->line('  -H "Content-Type: application/json" \\');
        $this->line('  -H "Accept: application/json" \\');
        $this->line('  -d \'{');
        $this->line('    "flow_id": "' . $flowId . '",');
        $this->line('    "steps": [');
        $this->line('      {');
        $this->line('        "handler": "location",');
        $this->line('        "config": {');
        $this->line('          "capture_snapshot": true,');
        $this->line('          "require_address": false');
        $this->line('        }');
        $this->line('      }');
        $this->line('    ]');
        $this->line('  }\'');
        $this->newLine();
        
        $this->comment('2. Submit Location Data (Manila coordinates):');
        $this->line('');
        $this->line('curl -X POST ' . $baseUrl . '/form-flow/' . $flowId . '/step/0 \\');
        $this->line('  -H "Content-Type: application/json" \\');
        $this->line('  -H "Accept: application/json" \\');
        $this->line('  -d \'{');
        $this->line('    "data": {');
        $this->line('      "latitude": 14.5995,');
        $this->line('      "longitude": 120.9842,');
        $this->line('      "timestamp": "' . now()->toIso8601String() . '",');
        $this->line('      "accuracy": 10.5,');
        $this->line('      "formatted_address": "Manila, Philippines"');
        $this->line('    }');
        $this->line('  }\'');
        $this->newLine();
        
        $this->comment('3. Get Flow State:');
        $this->line('');
        $this->line('curl ' . $baseUrl . '/form-flow/' . $flowId);
        $this->newLine();
        
        $this->comment('4. Complete Flow:');
        $this->line('');
        $this->line('curl -X POST ' . $baseUrl . '/form-flow/' . $flowId . '/complete');
        $this->newLine();
        
        $this->comment('💡 Test Coordinates:');
        $this->table(
            ['Location', 'Latitude', 'Longitude'],
            [
                ['Manila, PH', '14.5995', '120.9842'],
                ['Makati, PH', '14.5547', '121.0244'],
                ['Quezon City, PH', '14.6760', '121.0437'],
                ['New York, US', '40.7128', '-74.0060'],
                ['London, UK', '51.5074', '-0.1278'],
            ]
        );
    }
}
