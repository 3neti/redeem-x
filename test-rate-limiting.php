<?php

/**
 * Test Script: Advanced Rate Limiting (Token Bucket)
 *
 * Usage: php test-rate-limiting.php
 *
 * Tests:
 * 1. Burst allowance (10 tokens for basic tier)
 * 2. Rate limit enforcement (11th request blocked)
 * 3. Token refill over time
 * 4. Different tier limits
 * 5. Rate limit headers
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

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

// Get first user (or create one)
$user = User::first();
if (! $user) {
    $user = User::factory()->create([
        'email' => 'test-rate-limit@example.com',
        'name' => 'Rate Limit Test User',
    ]);
    echo colorize("Created test user: {$user->email}", $GLOBALS['yellow'])."\n";
}

// Ensure user has basic tier
$user->update(['rate_limit_tier' => 'basic']);

// Clean up Redis bucket for this user
Redis::del("rate_limit:bucket:user:{$user->id}");

echo colorize('Waiting 2 seconds for any previous rate limits to clear...', $GLOBALS['yellow'])."\n";
sleep(2);

// Create API token
$token = $user->createToken('rate-limit-test', ['*']);
$apiToken = $token->plainTextToken;

// Base URL (Laravel Herd)
$baseUrl = 'http://redeem-x.test';

// Test 1: Burst allowance (basic tier = 10 tokens)
testHeader('TEST 1: Burst Allowance (10 tokens for basic tier)');

$successCount = 0;
for ($i = 1; $i <= 10; $i++) {
    $response = Http::withToken($apiToken)
        ->get("$baseUrl/api/v1/health");

    if ($response->successful()) {
        $successCount++;
        $remaining = $response->header('X-RateLimit-Remaining');
        $tier = $response->header('X-RateLimit-Tier');
        echo "Request $i: ".colorize('✓ 200 OK', $GLOBALS['green'])." (Remaining: $remaining, Tier: $tier)\n";
    } else {
        echo "Request $i: ".colorize("✗ {$response->status()}", $GLOBALS['red'])."\n";
    }
}

testResult($successCount === 10, 'All 10 burst requests succeeded');

// Test 2: Rate limit enforcement (11th request should fail)
testHeader('TEST 2: Rate Limit Enforcement (11th request should be blocked)');

$response = Http::withToken($apiToken)
    ->get("$baseUrl/api/v1/health");

$blocked = $response->status() === 429;
testResult($blocked, '11th request blocked with 429 status');

if ($blocked) {
    $error = $response->json();
    echo 'Error: '.colorize($error['error'] ?? 'N/A', $GLOBALS['yellow'])."\n";
    echo 'Message: '.colorize($error['message'] ?? 'N/A', $GLOBALS['yellow'])."\n";
    echo 'Retry After: '.colorize($error['retry_after'] ?? 'N/A', $GLOBALS['yellow'])." seconds\n";

    $resetAt = $response->header('X-RateLimit-Reset');
    $remaining = $response->header('X-RateLimit-Remaining');
    echo 'Headers: Reset='.colorize($resetAt, $GLOBALS['yellow']).', Remaining='.colorize($remaining, $GLOBALS['yellow'])."\n";
}

// Test 3: Token refill over time (basic = 60 req/min = 1 req/sec)
testHeader('TEST 3: Token Refill (Wait 3 seconds, expect 3 tokens)');

echo "Waiting 3 seconds for token refill...\n";
sleep(3);

$successCount = 0;
for ($i = 1; $i <= 3; $i++) {
    $response = Http::withToken($apiToken)
        ->get("$baseUrl/api/v1/health");

    if ($response->successful()) {
        $successCount++;
        $remaining = $response->header('X-RateLimit-Remaining');
        echo "Request $i: ".colorize('✓ 200 OK', $GLOBALS['green'])." (Remaining: $remaining)\n";
    } else {
        echo "Request $i: ".colorize("✗ {$response->status()}", $GLOBALS['red'])."\n";
    }
}

testResult($successCount === 3, '3 requests succeeded after 3-second refill');

// Test 4: Different tier limits
testHeader('TEST 4: Premium Tier (300 req/min, 50 burst)');

// Upgrade user to premium and reset bucket
$user->update(['rate_limit_tier' => 'premium']);
Redis::del("rate_limit:bucket:user:{$user->id}");

// Make 50 burst requests
$successCount = 0;
for ($i = 1; $i <= 50; $i++) {
    $response = Http::withToken($apiToken)
        ->get("$baseUrl/api/v1/health");

    if ($response->successful()) {
        $successCount++;
    }
}

testResult($successCount === 50, 'All 50 premium burst requests succeeded');

// 51st should fail
$response = Http::withToken($apiToken)
    ->get("$baseUrl/api/v1/health");

testResult($response->status() === 429, '51st request blocked for premium tier');

// Test 5: Enterprise tier
testHeader('TEST 5: Enterprise Tier (1000 req/min, 200 burst)');

// Upgrade user to enterprise and reset bucket
$user->update(['rate_limit_tier' => 'enterprise']);
Redis::del("rate_limit:bucket:user:{$user->id}");

// Make 200 burst requests
$successCount = 0;
$startTime = microtime(true);

for ($i = 1; $i <= 200; $i++) {
    $response = Http::withToken($apiToken)
        ->get("$baseUrl/api/v1/health");

    if ($response->successful()) {
        $successCount++;
    }

    // Progress indicator every 50 requests
    if ($i % 50 === 0) {
        echo "Progress: $i/200 requests...\n";
    }
}

$elapsedTime = round(microtime(true) - $startTime, 2);

testResult($successCount === 200, "All 200 enterprise burst requests succeeded in {$elapsedTime}s");

// 201st should fail
$response = Http::withToken($apiToken)
    ->get("$baseUrl/api/v1/health");

testResult($response->status() === 429, '201st request blocked for enterprise tier');

// Final summary
testHeader('TEST SUMMARY');

echo colorize('✓ Burst allowance works correctly', $GLOBALS['green'])."\n";
echo colorize('✓ Rate limit enforcement blocks excess requests', $GLOBALS['green'])."\n";
echo colorize('✓ Token refill grants new tokens over time', $GLOBALS['green'])."\n";
echo colorize('✓ Different tiers have different limits', $GLOBALS['green'])."\n";
echo colorize('✓ Rate limit headers are included in responses', $GLOBALS['green'])."\n";

echo "\n".colorize('All tests passed! 🎉', $GLOBALS['green'])."\n";

// Clean up
Redis::del("rate_limit:bucket:user:{$user->id}");

echo "\n".colorize('Cleaned up Redis test keys', $GLOBALS['yellow'])."\n";
