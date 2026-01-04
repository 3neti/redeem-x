<?php

namespace LBHurtado\Voucher\Specifications;

use Carbon\Carbon;
use LBHurtado\Voucher\Data\RedemptionContext;

/**
 * Validates redemption is within time limit from creation.
 * 
 * Checks if voucher hasn't exceeded max duration since creation.
 * Example: "24h" = voucher expires 24 hours after creation.
 */
class TimeLimitSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $timeValidation = $voucher->instructions->validation->time ?? null;
        
        if (!$timeValidation) {
            return true; // No time validation required
        }
        
        $duration = $timeValidation->duration ?? null;
        
        if (!$duration) {
            return true; // No duration limit
        }
        
        $createdAt = Carbon::parse($voucher->created_at);
        $durationSeconds = $this->parseDuration($duration);
        $expiresAt = $createdAt->addSeconds($durationSeconds);
        
        return Carbon::now()->lessThanOrEqualTo($expiresAt);
    }
    
    /**
     * Parse duration string to seconds.
     * Supports: "24h", "30m", "7d", "86400"
     */
    private function parseDuration(string $duration): int
    {
        $duration = strtolower(trim($duration));
        
        if (str_ends_with($duration, 'd')) {
            return (int) rtrim($duration, 'd') * 86400; // days to seconds
        }
        
        if (str_ends_with($duration, 'h')) {
            return (int) rtrim($duration, 'h') * 3600; // hours to seconds
        }
        
        if (str_ends_with($duration, 'm')) {
            return (int) rtrim($duration, 'm') * 60; // minutes to seconds
        }
        
        if (str_ends_with($duration, 's')) {
            return (int) rtrim($duration, 's'); // seconds
        }
        
        // Assume seconds if no unit
        return (int) $duration;
    }
}
