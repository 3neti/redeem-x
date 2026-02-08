<?php

namespace App\Http\Middleware;

use App\Services\RateLimiter\TokenBucketRateLimiter;
use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdvancedRateLimiting
{
    protected TokenBucketRateLimiter $limiter;

    public function __construct(TokenBucketRateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if advanced rate limiting is disabled
        if (! config('api.rate_limiting.enabled', true)) {
            return $next($request);
        }

        // Get global rate limit tier
        $settings = app(SecuritySettings::class);
        $tier = $settings->rate_limit_tier;
        $tierConfig = config("api.rate_limiting.tiers.{$tier}", config('api.rate_limiting.tiers.basic'));

        // Calculate token bucket parameters
        $requestsPerMinute = $tierConfig['requests_per_minute'];
        $burstAllowance = $tierConfig['burst_allowance'];
        $maxTokens = $burstAllowance;
        $refillRate = $requestsPerMinute / 60; // Tokens per second

        // Create unique key (global for all users since tier is global)
        $key = 'global';

        // Attempt to consume a token
        $result = $this->limiter->attempt($key, $maxTokens, $refillRate);

        // Check if rate limited
        if (! $result['allowed']) {
            return response()->json([
                'error' => 'rate_limit_exceeded',
                'message' => 'Too many requests. Please retry after '.($result['reset_at'] - time()).' seconds.',
                'retry_after' => $result['reset_at'] - time(),
            ], 429, $this->buildHeaders(
                $requestsPerMinute,
                $result['remaining'],
                $result['reset_at'],
                $tier
            ));
        }

        // Process request and add rate limit headers to response
        $response = $next($request);

        return $this->addHeaders(
            $response,
            $requestsPerMinute,
            $result['remaining'],
            $result['reset_at'],
            $tier
        );
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $limit, int $remaining, int $resetAt, string $tier): Response
    {
        $response->headers->add($this->buildHeaders($limit, $remaining, $resetAt, $tier));

        return $response;
    }

    /**
     * Build rate limit headers array.
     */
    protected function buildHeaders(int $limit, int $remaining, int $resetAt, string $tier): array
    {
        return [
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => $resetAt,
            'X-RateLimit-Tier' => $tier,
        ];
    }
}
