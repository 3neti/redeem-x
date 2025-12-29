<?php

namespace App\Http\Middleware;

use App\Services\Security\RequestSignatureService;
use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyRequestSignature
{
    protected RequestSignatureService $signatureService;

    public function __construct(RequestSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SecuritySettings::class);

        // Skip if signature verification is disabled globally
        if (!$settings->signature_enabled || !$settings->signature_secret) {
            return $next($request);
        }

        // Get signature tolerance from config
        $tolerance = config('api.signature.tolerance', 300); // 5 minutes default

        // Verify signature
        $result = $this->signatureService->verifySignature(
            $request,
            $settings->signature_secret,
            $tolerance
        );

        if (!$result['valid']) {
            // Log signature verification failure
            Log::warning('[SignatureVerification] Request signature validation failed', [
                'user_id' => $request->user()?->id,
                'error' => $result['error'],
                'method' => $request->method(),
                'uri' => $request->path(),
                'ip' => $request->ip(),
                'age' => $result['age'] ?? null,
                'tolerance' => $result['tolerance'] ?? null,
            ]);

            // Return error response
            return response()->json([
                'error' => 'signature_verification_failed',
                'message' => $this->getErrorMessage($result['error']),
                'details' => [
                    'error_code' => $result['error'],
                    'age' => $result['age'] ?? null,
                    'tolerance' => $result['tolerance'] ?? null,
                ],
            ], 401);
        }

        // Signature valid, proceed with request
        return $next($request);
    }

    /**
     * Get human-readable error message.
     */
    protected function getErrorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'missing_signature' => 'Request signature is required. Include X-Signature header.',
            'missing_timestamp' => 'Request timestamp is required. Include X-Timestamp header.',
            'timestamp_expired' => 'Request timestamp is too old. Ensure clocks are synchronized.',
            'invalid_signature' => 'Request signature is invalid. Verify signing algorithm and secret.',
            default => 'Signature verification failed.',
        };
    }
}
