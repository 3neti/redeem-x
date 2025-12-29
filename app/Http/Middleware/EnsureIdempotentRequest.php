<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentRequest
{
    /**
     * Handle an incoming request.
     *
     * Idempotency implementation:
     * - Requires Idempotency-Key header for POST/PUT/PATCH requests
     * - Caches responses for 24 hours
     * - Returns cached response for duplicate requests
     * - Validates key format (UUID recommended)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce idempotency on state-changing methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // Require idempotency key
        if (empty($idempotencyKey)) {
            return response()->json([
                'message' => 'Idempotency-Key header is required for this request.',
                'errors' => [
                    'idempotency_key' => ['The Idempotency-Key header must be provided.']
                ]
            ], 400);
        }

        // Validate key format (basic validation)
        if (strlen($idempotencyKey) < 16 || strlen($idempotencyKey) > 255) {
            return response()->json([
                'message' => 'Invalid Idempotency-Key format.',
                'errors' => [
                    'idempotency_key' => ['The Idempotency-Key must be between 16 and 255 characters.']
                ]
            ], 400);
        }

        // Create cache key (include user ID for multi-tenancy)
        $userId = $request->user()?->id ?? 'guest';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        // Check if this request was already processed
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse !== null) {
            // Return the cached response
            return response()->json(
                $cachedResponse['body'],
                $cachedResponse['status']
            )->withHeaders($cachedResponse['headers']);
        }

        // Process the request
        $response = $next($request);

        // Only cache successful responses (2xx status codes)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $responseData = [
                'body' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
                'headers' => [
                    'X-Idempotent-Replay' => 'false',
                    'Content-Type' => 'application/json',
                ],
            ];

            // Cache for 24 hours (86400 seconds)
            Cache::put($cacheKey, $responseData, now()->addHours(24));
        }

        // Add header to indicate this is an original response
        $response->headers->set('X-Idempotent-Replay', 'false');

        return $response;
    }
}
