<?php

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class CheckIpWhitelist
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SecuritySettings::class);

        // Skip if IP whitelisting is globally disabled
        if (! $settings->ip_whitelist_enabled) {
            return $next($request);
        }

        // Skip for local development
        if (config('api.ip_whitelist.bypass_local') && app()->environment('local')) {
            return $next($request);
        }

        // Get global IP whitelist
        $whitelist = $settings->ip_whitelist;

        // If whitelist is empty, allow all (safety fallback)
        if (empty($whitelist)) {
            return $next($request);
        }

        // Get client IP (handle proxies)
        $clientIp = $this->getClientIp($request);

        // Check if IP is whitelisted (supports CIDR notation)
        if (! $this->isIpWhitelisted($clientIp, $whitelist)) {
            Log::warning('IP whitelist violation', [
                'user_id' => $request->user()?->id,
                'client_ip' => $clientIp,
                'whitelist' => $whitelist,
                'uri' => $request->getRequestUri(),
            ]);

            return response()->json([
                'error' => 'ip_not_whitelisted',
                'message' => 'Your IP address is not authorized to access this resource.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Get the client's IP address, handling proxies.
     */
    protected function getClientIp(Request $request): string
    {
        // Check X-Forwarded-For header (behind proxy/load balancer)
        if ($request->header('X-Forwarded-For')) {
            $ips = explode(',', $request->header('X-Forwarded-For'));

            return trim($ips[0]); // First IP is the original client
        }

        // Check X-Real-IP header (nginx)
        if ($request->header('X-Real-IP')) {
            return $request->header('X-Real-IP');
        }

        // Fall back to direct connection IP
        return $request->ip();
    }

    /**
     * Check if IP is in whitelist (supports CIDR notation).
     */
    protected function isIpWhitelisted(string $ip, array $whitelist): bool
    {
        foreach ($whitelist as $allowedIp) {
            // Use Symfony's IpUtils for robust IP/CIDR matching
            if (IpUtils::checkIp($ip, $allowedIp)) {
                return true;
            }
        }

        return false;
    }
}
