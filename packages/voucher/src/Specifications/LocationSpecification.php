<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;

/**
 * Validates GPS location is within required radius.
 * 
 * Uses Haversine formula to calculate distance between two GPS coordinates.
 */
class LocationSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $locationValidation = $voucher->instructions->validation->location ?? null;
        
        if (!$locationValidation) {
            return true; // No location validation required
        }
        
        // Get required location and radius
        $requiredLocation = $locationValidation->coordinates ?? null;
        $requiredRadius = $locationValidation->radius ?? null;
        
        if (!$requiredLocation || !$requiredRadius) {
            return true; // Incomplete config, pass
        }
        
        // Get provided location from context
        $providedLocation = $context->inputs['location'] ?? null;
        
        if (!$providedLocation) {
            return false; // Location required but not provided
        }
        
        $providedLat = $providedLocation['lat'] ?? $providedLocation['latitude'] ?? null;
        $providedLng = $providedLocation['lng'] ?? $providedLocation['longitude'] ?? null;
        
        if ($providedLat === null || $providedLng === null) {
            return false; // Invalid location data
        }
        
        // Calculate distance
        $distance = $this->calculateDistance(
            $requiredLocation['lat'],
            $requiredLocation['lng'],
            $providedLat,
            $providedLng
        );
        
        // Parse radius (supports "1000m" or "2km")
        $radiusMeters = $this->parseRadius($requiredRadius);
        
        return $distance <= $radiusMeters;
    }
    
    /**
     * Calculate distance between two GPS coordinates using Haversine formula.
     * 
     * @return float Distance in meters
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Earth's radius in meters
        
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLatRad = deg2rad($lat2 - $lat1);
        $deltaLngRad = deg2rad($lng2 - $lng1);
        
        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLngRad / 2) * sin($deltaLngRad / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Parse radius string to meters.
     * Supports: "1000m", "2km", "500"
     */
    private function parseRadius(string $radius): float
    {
        $radius = strtolower(trim($radius));
        
        if (str_ends_with($radius, 'km')) {
            return (float) rtrim($radius, 'km') * 1000;
        }
        
        if (str_ends_with($radius, 'm')) {
            return (float) rtrim($radius, 'm');
        }
        
        // Assume meters if no unit
        return (float) $radius;
    }
}
