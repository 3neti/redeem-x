<?php

declare(strict_types=1);

namespace App\Actions\Api\System;

use App\Http\Responses\ApiResponse;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get system health status.
 * 
 * Endpoint: GET /api/v1/health (authenticated)
 * Public endpoint: GET /health (no auth)
 * 
 * Returns health status of critical system components for uptime monitoring
 * and SLA tracking. Used by monitoring services like UptimeRobot or Pingdom.
 */
class GetHealth
{
    use AsAction;

    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {}

    /**
     * Handle API request.
     * 
     * Note: This action supports both authenticated (/api/v1/health) and
     * public (/health) endpoints via route configuration.
     */
    public function asController(): JsonResponse
    {
        $healthData = $this->healthCheckService->checkHealth();

        // Use appropriate HTTP status code based on health status
        $statusCode = match ($healthData['status']) {
            'healthy' => 200,
            'degraded' => 200, // Still operational, but with warnings
            'down' => 503,     // Service unavailable
            default => 200,
        };

        return response()->json([
            'data' => $healthData,
            'meta' => [
                'timestamp' => $healthData['timestamp'],
                'version' => 'v1',
            ],
        ], $statusCode);
    }

    /**
     * Get simple health status (for public endpoint).
     * 
     * Returns minimal response for basic uptime monitoring.
     */
    public function simple(): JsonResponse
    {
        $healthData = $this->healthCheckService->checkHealth();

        $statusCode = $healthData['status'] === 'down' ? 503 : 200;

        return response()->json([
            'status' => $healthData['status'],
            'timestamp' => $healthData['timestamp'],
        ], $statusCode);
    }
}
