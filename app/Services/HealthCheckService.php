<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Health check service for monitoring system components.
 * 
 * Checks the health of critical components: database, cache, queue, and payment gateway.
 * Used by health check endpoints for uptime monitoring and SLA tracking.
 */
class HealthCheckService
{
    /**
     * Overall system health status.
     */
    private const STATUS_HEALTHY = 'healthy';
    private const STATUS_DEGRADED = 'degraded';
    private const STATUS_DOWN = 'down';

    /**
     * Component status.
     */
    private const COMPONENT_UP = 'up';
    private const COMPONENT_DOWN = 'down';

    /**
     * Check health of all system components.
     *
     * @return array{status: string, timestamp: string, version: string, checks: array}
     */
    public function checkHealth(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        // Determine overall status
        $overallStatus = $this->determineOverallStatus($checks);

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'checks' => $checks,
        ];
    }

    /**
     * Check database connectivity and latency.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = (microtime(true) - $start) * 1000; // Convert to milliseconds

            return [
                'status' => self::COMPONENT_UP,
                'latency_ms' => round($latency, 2),
            ];
        } catch (Throwable $e) {
            return [
                'status' => self::COMPONENT_DOWN,
                'error' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === 'test') {
                return ['status' => self::COMPONENT_UP];
            }

            return [
                'status' => self::COMPONENT_DOWN,
                'error' => 'Cache read/write failed',
            ];
        } catch (Throwable $e) {
            return [
                'status' => self::COMPONENT_DOWN,
                'error' => 'Cache connection failed',
            ];
        }
    }

    /**
     * Check queue connectivity.
     */
    private function checkQueue(): array
    {
        try {
            // Get queue size (works with database queue driver)
            $connection = Queue::connection();
            $size = $connection->size();

            return [
                'status' => self::COMPONENT_UP,
                'pending_jobs' => $size,
            ];
        } catch (Throwable $e) {
            return [
                'status' => self::COMPONENT_DOWN,
                'error' => 'Queue connection failed',
            ];
        }
    }

    /**
     * Determine overall system status based on component health.
     */
    private function determineOverallStatus(array $checks): string
    {
        $downCount = 0;
        $totalChecks = count($checks);

        foreach ($checks as $check) {
            if ($check['status'] === self::COMPONENT_DOWN) {
                $downCount++;
            }
        }

        // If all components are down, system is down
        if ($downCount === $totalChecks) {
            return self::STATUS_DOWN;
        }

        // If any component is down, system is degraded
        if ($downCount > 0) {
            return self::STATUS_DEGRADED;
        }

        // All components are up
        return self::STATUS_HEALTHY;
    }
}
