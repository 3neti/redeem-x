<?php

namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

class TokenBucketRateLimiter
{
    /**
     * Check if request is allowed and consume a token if so.
     *
     * @param string $key Unique identifier (e.g., "user:123")
     * @param int $maxTokens Maximum tokens in bucket (burst allowance)
     * @param float $refillRate Tokens added per second
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function attempt(string $key, int $maxTokens, float $refillRate): array
    {
        $now = microtime(true);
        $bucketKey = "rate_limit:bucket:{$key}";
        
        // Get current bucket state
        $data = Redis::hgetall($bucketKey);
        
        if (empty($data)) {
            // Initialize new bucket
            $tokens = $maxTokens - 1; // Consume 1 token for this request
            $lastRefill = $now;
            
            Redis::hmset($bucketKey, [
                'tokens' => $tokens,
                'last_refill' => $lastRefill,
            ]);
            Redis::expire($bucketKey, 3600); // Expire after 1 hour of inactivity
            
            return [
                'allowed' => true,
                'remaining' => (int) $tokens,
                'reset_at' => (int) ($now + ($maxTokens / $refillRate)),
            ];
        }
        
        // Calculate tokens to add since last refill
        $tokens = (float) $data['tokens'];
        $lastRefill = (float) $data['last_refill'];
        $timePassed = $now - $lastRefill;
        $tokensToAdd = $timePassed * $refillRate;
        
        // Refill tokens (capped at max)
        $tokens = min($maxTokens, $tokens + $tokensToAdd);
        $lastRefill = $now;
        
        // Check if request is allowed
        if ($tokens < 1) {
            // Not enough tokens - request denied
            $resetAt = (int) ($now + ((1 - $tokens) / $refillRate));
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $resetAt,
            ];
        }
        
        // Consume token
        $tokens -= 1;
        
        // Update bucket state
        Redis::hmset($bucketKey, [
            'tokens' => $tokens,
            'last_refill' => $lastRefill,
        ]);
        Redis::expire($bucketKey, 3600);
        
        return [
            'allowed' => true,
            'remaining' => (int) floor($tokens),
            'reset_at' => (int) ($now + (($maxTokens - $tokens) / $refillRate)),
        ];
    }
    
    /**
     * Get current bucket status without consuming a token.
     *
     * @param string $key
     * @param int $maxTokens
     * @param float $refillRate
     * @return array
     */
    public function status(string $key, int $maxTokens, float $refillRate): array
    {
        $now = microtime(true);
        $bucketKey = "rate_limit:bucket:{$key}";
        
        $data = Redis::hgetall($bucketKey);
        
        if (empty($data)) {
            return [
                'remaining' => $maxTokens,
                'reset_at' => (int) $now,
            ];
        }
        
        $tokens = (float) $data['tokens'];
        $lastRefill = (float) $data['last_refill'];
        $timePassed = $now - $lastRefill;
        $tokensToAdd = $timePassed * $refillRate;
        
        $tokens = min($maxTokens, $tokens + $tokensToAdd);
        
        return [
            'remaining' => (int) floor($tokens),
            'reset_at' => (int) ($now + (($maxTokens - $tokens) / $refillRate)),
        ];
    }
    
    /**
     * Reset bucket for a key.
     *
     * @param string $key
     * @return void
     */
    public function reset(string $key): void
    {
        Redis::del("rate_limit:bucket:{$key}");
    }
}
