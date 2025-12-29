<?php

namespace App\Services\Security;

use Illuminate\Http\Request;

class RequestSignatureService
{
    /**
     * Generate HMAC-SHA256 signature for a request.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Request URI (e.g., /api/v1/vouchers)
     * @param string $body Request body (raw JSON string)
     * @param int $timestamp Unix timestamp
     * @param string $secret Signing secret
     * @return string HMAC-SHA256 signature
     */
    public function generateSignature(
        string $method,
        string $uri,
        string $body,
        int $timestamp,
        string $secret
    ): string {
        // Build signing string: METHOD\nURI\nTIMESTAMP\nBODY
        $signingString = implode("\n", [
            strtoupper($method),
            $uri,
            $timestamp,
            $body,
        ]);

        // Generate HMAC-SHA256 signature
        return hash_hmac('sha256', $signingString, $secret);
    }

    /**
     * Verify request signature.
     *
     * @param Request $request
     * @param string $secret Signing secret
     * @param int $tolerance Maximum age of request in seconds (default: 300s = 5 min)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function verifySignature(Request $request, string $secret, int $tolerance = 300): array
    {
        // Get signature from header
        $providedSignature = $request->header('X-Signature');
        if (!$providedSignature) {
            return ['valid' => false, 'error' => 'missing_signature'];
        }

        // Get timestamp from header
        $timestamp = $request->header('X-Timestamp');
        if (!$timestamp || !is_numeric($timestamp)) {
            return ['valid' => false, 'error' => 'missing_timestamp'];
        }

        // Check timestamp is within tolerance (prevent replay attacks)
        $now = time();
        $age = abs($now - (int) $timestamp);
        if ($age > $tolerance) {
            return [
                'valid' => false,
                'error' => 'timestamp_expired',
                'age' => $age,
                'tolerance' => $tolerance,
            ];
        }

        // Generate expected signature
        $method = $request->method();
        $uri = $request->path(); // e.g., api/v1/vouchers
        $body = $request->getContent(); // Raw body

        $expectedSignature = $this->generateSignature(
            $method,
            '/' . $uri, // Ensure leading slash
            $body,
            (int) $timestamp,
            $secret
        );

        // Compare signatures (timing-safe comparison)
        if (!hash_equals($expectedSignature, $providedSignature)) {
            return ['valid' => false, 'error' => 'invalid_signature'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generate a new random signing secret.
     *
     * @return string 64-character hex string
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32)); // 32 bytes = 64 hex chars
    }
}
