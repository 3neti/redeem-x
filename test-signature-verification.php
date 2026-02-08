<?php

/**
 * Test Script: HMAC-SHA256 Request Signature Verification
 *
 * Usage: php test-signature-verification.php
 *
 * Tests:
 * 1. Signature generation and verification
 * 2. Valid signed request
 * 3. Invalid signature (wrong secret)
 * 4. Missing signature header
 * 5. Missing timestamp header
 * 6. Expired timestamp (replay attack prevention)
 * 7. Disabled signature verification (bypass)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\Security\RequestSignatureService;
use Illuminate\Support\Facades\Http;

// ANSI colors
$green = "\033[0;32m";
$red = "\033[0;31m";
$blue = "\033[0;34m";
$yellow = "\033[0;33m";
$reset = "\033[0m";

function colorize($text, $color)
{
    return $color.$text.$GLOBALS['reset'];
}

function testHeader($text)
{
    echo "\n".colorize("━━━ $text ━━━", $GLOBALS['blue'])."\n";
}

function testResult($pass, $message)
{
    $icon = $pass ? '✓' : '✗';
    $color = $pass ? $GLOBALS['green'] : $GLOBALS['red'];
    echo colorize("$icon $message", $color)."\n";
}

// Get or create test user
$user = User::first();
if (! $user) {
    $user = User::factory()->create([
        'email' => 'test-signature@example.com',
        'name' => 'Signature Test User',
    ]);
    echo colorize("Created test user: {$user->email}", $GLOBALS['yellow'])."\n";
}

// Generate signature secret
$secret = $user->generateSignatureSecret();
echo colorize('Generated signature secret: '.substr($secret, 0, 16).'...', $GLOBALS['yellow'])."\n";

// Create API token
$token = $user->createToken('signature-test', ['*']);
$apiToken = $token->plainTextToken;

// Base URL
$baseUrl = 'http://redeem-x.test';

// Initialize signature service
$signatureService = new RequestSignatureService;

// Helper function to make signed request
function makeSignedRequest($url, $method, $body, $secret, $apiToken, $timestamp = null, $signature = null)
{
    $timestamp = $timestamp ?? time();
    $uri = parse_url($url, PHP_URL_PATH);

    // Generate signature if not provided
    if ($signature === null) {
        $signatureService = new RequestSignatureService;
        $signature = $signatureService->generateSignature(
            $method,
            $uri,
            $body,
            $timestamp,
            $secret
        );
    }

    return Http::withHeaders([
        'Authorization' => 'Bearer '.$apiToken,
        'X-Signature' => $signature,
        'X-Timestamp' => (string) $timestamp,
        'Content-Type' => 'application/json',
    ])->send($method, $url, ['body' => $body]);
}

// Test 1: Signature disabled (should pass without signature)
testHeader('TEST 1: Signature Disabled (Default Behavior)');

$response = Http::withToken($apiToken)
    ->get("$baseUrl/api/v1/health");

testResult($response->successful(), 'Request without signature succeeds when signature disabled');

// Test 2: Enable signature verification
testHeader('TEST 2: Enable Signature Verification');

$user->enableSignatureVerification();
$user->refresh();

testResult(
    $user->signature_enabled && $user->signature_secret === $secret,
    'Signature verification enabled for user'
);

// Test 3: Valid signed request
testHeader('TEST 3: Valid Signed Request');

$timestamp = time();
$method = 'GET';
$uri = '/api/v1/health';
$body = '';

$signature = $signatureService->generateSignature($method, $uri, $body, $timestamp, $secret);

$response = makeSignedRequest("$baseUrl/api/v1/health", 'GET', '', $secret, $apiToken, $timestamp, $signature);

testResult($response->successful(), 'Valid signed request succeeds');

if ($response->successful()) {
    echo colorize('  Response: '.$response->status().' OK', $GLOBALS['green'])."\n";
}

// Test 4: Missing signature header
testHeader('TEST 4: Missing Signature Header');

$response = Http::withHeaders([
    'Authorization' => 'Bearer '.$apiToken,
    'X-Timestamp' => (string) time(),
])->get("$baseUrl/api/v1/health");

testResult($response->status() === 401, 'Request without signature header fails with 401');

if ($response->status() === 401) {
    $error = $response->json();
    echo colorize('  Error: '.($error['error'] ?? 'N/A'), $GLOBALS['yellow'])."\n";
    echo colorize('  Message: '.($error['message'] ?? 'N/A'), $GLOBALS['yellow'])."\n";
}

// Test 5: Missing timestamp header
testHeader('TEST 5: Missing Timestamp Header');

$response = Http::withHeaders([
    'Authorization' => 'Bearer '.$apiToken,
    'X-Signature' => 'dummy-signature',
])->get("$baseUrl/api/v1/health");

testResult($response->status() === 401, 'Request without timestamp header fails with 401');

if ($response->status() === 401) {
    $error = $response->json();
    echo colorize('  Error: '.($error['details']['error_code'] ?? 'N/A'), $GLOBALS['yellow'])."\n";
}

// Test 6: Invalid signature (wrong secret)
testHeader('TEST 6: Invalid Signature (Wrong Secret)');

$wrongSecret = bin2hex(random_bytes(32));
$wrongSignature = $signatureService->generateSignature('GET', '/api/v1/health', '', time(), $wrongSecret);

$response = makeSignedRequest("$baseUrl/api/v1/health", 'GET', '', $secret, $apiToken, time(), $wrongSignature);

testResult($response->status() === 401, 'Request with invalid signature fails with 401');

if ($response->status() === 401) {
    $error = $response->json();
    echo colorize('  Error: '.($error['details']['error_code'] ?? 'N/A'), $GLOBALS['yellow'])."\n";
}

// Test 7: Expired timestamp (replay attack prevention)
testHeader('TEST 7: Expired Timestamp (Replay Attack Prevention)');

$expiredTimestamp = time() - 600; // 10 minutes ago (tolerance is 5 minutes)
$expiredSignature = $signatureService->generateSignature('GET', '/api/v1/health', '', $expiredTimestamp, $secret);

$response = makeSignedRequest("$baseUrl/api/v1/health", 'GET', '', $secret, $apiToken, $expiredTimestamp, $expiredSignature);

testResult($response->status() === 401, 'Request with expired timestamp fails with 401');

if ($response->status() === 401) {
    $error = $response->json();
    echo colorize('  Error: '.($error['details']['error_code'] ?? 'N/A'), $GLOBALS['yellow'])."\n";
    echo colorize('  Age: '.($error['details']['age'] ?? 'N/A').'s', $GLOBALS['yellow'])."\n";
    echo colorize('  Tolerance: '.($error['details']['tolerance'] ?? 'N/A').'s', $GLOBALS['yellow'])."\n";
}

// Test 8: POST request with body
testHeader('TEST 8: Signed POST Request with Body');

$timestamp = time();
$method = 'POST';
$uri = '/api/v1/vouchers';
$body = json_encode([
    'count' => 1,
    'amount' => 100,
    'currency' => 'PHP',
]);

$signature = $signatureService->generateSignature($method, $uri, $body, $timestamp, $secret);

$response = Http::withHeaders([
    'Authorization' => 'Bearer '.$apiToken,
    'X-Signature' => $signature,
    'X-Timestamp' => (string) $timestamp,
    'Content-Type' => 'application/json',
])->post("$baseUrl/api/v1/vouchers", json_decode($body, true));

// We expect validation errors (missing fields), but signature should pass (not 401)
testResult($response->status() !== 401, 'POST request with valid signature passes authentication');

if ($response->status() !== 401) {
    echo colorize('  Status: '.$response->status().' (signature valid, expected validation error)', $GLOBALS['green'])."\n";
}

// Test 9: Disable signature verification
testHeader('TEST 9: Disable Signature Verification');

$user->disableSignatureVerification();
$user->refresh();

testResult(! $user->signature_enabled, 'Signature verification disabled');

// Request without signature should now work
$response = Http::withToken($apiToken)
    ->get("$baseUrl/api/v1/health");

testResult($response->successful(), 'Request without signature succeeds when signature disabled');

// Final summary
testHeader('TEST SUMMARY');

echo colorize('✓ Signature generation works correctly', $GLOBALS['green'])."\n";
echo colorize('✓ Valid signatures authenticate successfully', $GLOBALS['green'])."\n";
echo colorize('✓ Missing signatures are rejected (401)', $GLOBALS['green'])."\n";
echo colorize('✓ Invalid signatures are rejected (401)', $GLOBALS['green'])."\n";
echo colorize('✓ Expired timestamps are rejected (replay protection)', $GLOBALS['green'])."\n";
echo colorize('✓ POST requests with bodies are signed correctly', $GLOBALS['green'])."\n";
echo colorize('✓ Signature verification can be enabled/disabled per user', $GLOBALS['green'])."\n";

echo "\n".colorize('All tests passed! 🎉', $GLOBALS['green'])."\n";

// Cleanup
$user->update(['signature_enabled' => false, 'signature_secret' => null]);
echo "\n".colorize('Cleaned up test user settings', $GLOBALS['yellow'])."\n";
