<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Test suite for A/B testing YAML vs PHP driver implementations
 * 
 * Verifies that:
 * - /disburse uses PHP driver (default)
 * - /disburse-yaml forces YAML driver
 * - Both routes are accessible and functional
 */
class DisburseYamlRouteTest extends TestCase
{
    public function test_disburse_yaml_route_is_registered(): void
    {
        $response = $this->get('/disburse-yaml');
        
        // Should return 200 (Inertia page) or redirect (if logic requires)
        $this->assertContains($response->status(), [200, 302]);
    }
    
    public function test_disburse_yaml_controller_forces_yaml_mode(): void
    {
        // Ensure default is PHP mode
        config(['form-flow.use_yaml_driver' => false]);
        $this->assertFalse(config('form-flow.use_yaml_driver'));
        
        // Instantiate controller (triggers constructor)
        $controller = app('App\Http\Controllers\Disburse\DisburseYamlController');
        
        // Verify YAML mode is forced
        $this->assertTrue(config('form-flow.use_yaml_driver'));
    }
    
    public function test_regular_disburse_route_still_works(): void
    {
        $response = $this->get('/disburse');
        
        // Should return 200 (Inertia page) or redirect
        $this->assertContains($response->status(), [200, 302]);
    }
    
    public function test_parallel_routes_have_same_structure(): void
    {
        // Get all routes
        $routes = collect(app('router')->getRoutes());
        
        // Find disburse routes
        $disburseRoutes = $routes->filter(fn($route) => str_starts_with($route->getName() ?? '', 'disburse.'));
        $disburseYamlRoutes = $routes->filter(fn($route) => str_starts_with($route->getName() ?? '', 'disburse-yaml.'));
        
        // Both should have same number of routes
        $this->assertEquals($disburseRoutes->count(), $disburseYamlRoutes->count());
        
        // Verify key routes exist
        $expectedRoutes = ['start', 'complete', 'redeem', 'cancel', 'success'];
        
        foreach ($expectedRoutes as $routeName) {
            $this->assertTrue($routes->contains(fn($r) => $r->getName() === "disburse.$routeName"));
            $this->assertTrue($routes->contains(fn($r) => $r->getName() === "disburse-yaml.$routeName"));
        }
    }
}
