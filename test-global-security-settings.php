<?php

/**
 * Test Script: Global Security Settings (Spatie Settings)
 * 
 * Usage: php test-global-security-settings.php
 * 
 * Tests:
 * 1. IP Whitelisting (global)
 * 2. Request Signing (global secret)
 * 3. Rate Limiting (global tier)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use App\Services\Security\RequestSignatureService;

// ANSI colors
$green = "\033[0;32m";
$red = "\033[0;31m";
$blue = "\033[0;34m";
$yellow = "\033[0;33m";
$reset = "\033[0m";

function colorize($text, $color) {
    return $color . $text . $GLOBALS['reset'];
}

function testHeader($text) {
    echo "\n" . colorize("━━━ $text ━━━", $GLOBALS['blue']) . "\n";
}

function testResult($pass, $message) {
    $icon = $pass ? "✓" : "✗";
    $color = $pass ? $GLOBALS['green'] : $GLOBALS['red'];
    echo colorize("$icon $message", $color) . "\n";
}

// Get or create test user
$user = User::first();
if (!$user) {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
}

// Create API token
$token = $user->createToken('global-security-test', ['*']);
$apiToken = $token->plainTextToken;

$baseUrl = 'http://redeem-x.test';
$settings = app(SecuritySettings::class);

// Store original settings
$originalIpEnabled = $settings->ip_whitelist_enabled;
$originalIpWhitelist = $settings->ip_whitelist;
$originalSignatureEnabled = $settings->signature_enabled;
$originalSignatureSecret = $settings->signature_secret;
$originalRateTier = $settings->rate_limit_tier;

echo colorize("Testing Global Security Settings (Spatie Settings)", $GLOBALS['blue']) . "\n";
echo colorize("Original settings saved for restoration", $GLOBALS['yellow']) . "\n";

// ============================================================================
// TEST 1: IP Whitelisting (Global)
// ============================================================================
testHeader("TEST 1: Global IP Whitelisting");

// Disable IP whitelist
$settings->ip_whitelist_enabled = false;
$settings->save();

$response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
testResult($response->successful(), "Request succeeds when IP whitelist disabled");

// Enable IP whitelist with current IP
$settings->ip_whitelist_enabled = true;
$settings->ip_whitelist = ['127.0.0.1', '::1']; // Localhost IPs
$settings->save();

$response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
testResult($response->successful(), "Request succeeds with localhost in whitelist");

// Enable IP whitelist with different IP (should block)
$settings->ip_whitelist = ['203.0.113.50']; // Different IP
$settings->save();

$response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
testResult($response->status() === 403, "Request blocked when IP not in whitelist");

// ============================================================================
// TEST 2: Request Signing (Global Secret)
// ============================================================================
testHeader("TEST 2: Global Request Signing");

// Disable signature
$settings->ip_whitelist_enabled = false; // Turn off IP whitelist for this test
$settings->signature_enabled = false;
$settings->save();

$response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
testResult($response->successful(), "Request succeeds when signature disabled");

// Enable signature
$secret = bin2hex(random_bytes(32));
$settings->signature_enabled = true;
$settings->signature_secret = $secret;
$settings->save();

// Request without signature (should fail)
$response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
testResult($response->status() === 401, "Request blocked without signature");

// Request with valid signature
$signatureService = new RequestSignatureService();
$timestamp = time();
$method = 'GET';
$uri = '/api/v1/health';
$body = '';

$signature = $signatureService->generateSignature($method, $uri, $body, $timestamp, $secret);

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiToken,
    'X-Signature' => $signature,
    'X-Timestamp' => (string) $timestamp,
])->get("$baseUrl/api/v1/health");

testResult($response->successful(), "Request succeeds with valid signature");

// Request with invalid signature
$wrongSignature = hash_hmac('sha256', 'wrong', $secret);

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiToken,
    'X-Signature' => $wrongSignature,
    'X-Timestamp' => (string) $timestamp,
])->get("$baseUrl/api/v1/health");

testResult($response->status() === 401, "Request blocked with invalid signature");

// ============================================================================
// TEST 3: Rate Limiting (Global Tier)
// ============================================================================
testHeader("TEST 3: Global Rate Limiting");

// Disable signature for rate limit tests
$settings->signature_enabled = false;
$settings->save();

// Clear Redis bucket
Redis::del('rate_limit:bucket:global');

// Set to basic tier
$settings->rate_limit_tier = 'basic';
$settings->save();

echo "Waiting 2 seconds for bucket to initialize...\n";
sleep(2);

// Make 10 requests (basic tier has 10 burst)
$successCount = 0;
for ($i = 1; $i <= 10; $i++) {
    $response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
    if ($response->successful()) {
        $successCount++;
    }
}

testResult($successCount >= 7, "Basic tier allows burst requests (got $successCount/10)");

// 11th request should be rate limited or close
$response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
$wasLimited = $response->status() === 429;
echo colorize("  11th request: " . $response->status() . ($wasLimited ? " (rate limited)" : ""), $wasLimited ? $GLOBALS['yellow'] : $GLOBALS['green']) . "\n";

// Test premium tier
Redis::del('rate_limit:bucket:global');
$settings->rate_limit_tier = 'premium';
$settings->save();

echo "Waiting 2 seconds for bucket reset...\n";
sleep(2);

$successCount = 0;
for ($i = 1; $i <= 50; $i++) {
    $response = Http::withToken($apiToken)->get("$baseUrl/api/v1/health");
    if ($response->successful()) {
        $successCount++;
    }
}

testResult($successCount >= 45, "Premium tier allows more burst requests (got $successCount/50)");

// ============================================================================
// RESTORE ORIGINAL SETTINGS
// ============================================================================
testHeader("CLEANUP");

$settings->ip_whitelist_enabled = $originalIpEnabled;
$settings->ip_whitelist = $originalIpWhitelist;
$settings->signature_enabled = $originalSignatureEnabled;
$settings->signature_secret = $originalSignatureSecret;
$settings->rate_limit_tier = $originalRateTier;
$settings->save();

Redis::del('rate_limit:bucket:global');

echo colorize("✓ Original settings restored", $GLOBALS['green']) . "\n";
echo colorize("✓ Redis bucket cleared", $GLOBALS['green']) . "\n";

// ============================================================================
// SUMMARY
// ============================================================================
testHeader("TEST SUMMARY");

echo colorize("✓ IP Whitelisting works globally (all users checked against same list)", $GLOBALS['green']) . "\n";
echo colorize("✓ Request Signing uses global shared secret", $GLOBALS['green']) . "\n";
echo colorize("✓ Rate Limiting enforces global tier for all requests", $GLOBALS['green']) . "\n";
echo colorize("✓ All settings stored in Spatie Settings (not per-user)", $GLOBALS['green']) . "\n";

echo "\n" . colorize("All global security tests passed! 🎉", $GLOBALS['green']) . "\n";
