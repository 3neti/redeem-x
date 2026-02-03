<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * InputFormatter
 * 
 * Formats voucher input fields for display in notifications.
 * Excludes image fields (signature, selfie, location) and KYC data.
 * Handles truncation and formatting for SMS/email constraints.
 */
class InputFormatter
{
    /**
     * Fields to exclude from formatting (images, sensitive data).
     */
    private const EXCLUDED_FIELDS = [
        'signature',
        'selfie',
        'location',
        'kyc',
        '_step_name',
        'splash_viewed',
        'viewed_at',
    ];
    
    /**
     * Max length for individual field values.
     */
    private const MAX_VALUE_LENGTH = 50;
    
    /**
     * Max total length for SMS formatting.
     */
    private const MAX_SMS_LENGTH = 100;
    
    /**
     * Format inputs for SMS display.
     * 
     * Returns a compact string like: "otp: 123456 | ref: ABC-001"
     * Truncates to MAX_SMS_LENGTH chars if needed.
     *
     * @param  Collection  $inputs  Collection of InputData objects
     * @return string
     */
    public static function formatForSms(Collection $inputs): string
    {
        $filtered = static::filterInputs($inputs);
        
        if ($filtered->isEmpty()) {
            return '';
        }
        
        // Format as: field: value | field: value
        $formatted = $filtered
            ->map(function ($input) {
                $label = Str::title(str_replace('_', ' ', $input->name));
                $value = static::truncateValue($input->value, self::MAX_VALUE_LENGTH);
                return "{$label}: {$value}";
            })
            ->join(' | ');
        
        // Truncate if too long
        if (strlen($formatted) > self::MAX_SMS_LENGTH) {
            $formatted = substr($formatted, 0, self::MAX_SMS_LENGTH - 3) . '...';
        }
        
        return $formatted;
    }
    
    /**
     * Format inputs for email display.
     * 
     * Returns an array of [label => value] pairs for use in email template.
     *
     * @param  Collection  $inputs  Collection of InputData objects
     * @return array
     */
    public static function formatForEmail(Collection $inputs): array
    {
        $filtered = static::filterInputs($inputs);
        
        if ($filtered->isEmpty()) {
            return [];
        }
        
        return $filtered
            ->mapWithKeys(function ($input) {
                $label = Str::title(str_replace('_', ' ', $input->name));
                $value = static::truncateValue($input->value, 200); // Longer limit for email
                return [$label => $value];
            })
            ->toArray();
    }
    
    /**
     * Filter inputs to exclude images and sensitive fields.
     *
     * @param  Collection  $inputs
     * @return Collection
     */
    protected static function filterInputs(Collection $inputs): Collection
    {
        return $inputs->filter(function ($input) {
            // Exclude special fields
            if (in_array($input->name, self::EXCLUDED_FIELDS)) {
                return false;
            }
            
            // Exclude empty values
            if (empty($input->value)) {
                return false;
            }
            
            return true;
        });
    }
    
    /**
     * Truncate long values with ellipsis.
     *
     * @param  mixed  $value
     * @param  int  $maxLength
     * @return string
     */
    protected static function truncateValue(mixed $value, int $maxLength): string
    {
        // Convert to string
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        $value = (string) $value;
        
        // Truncate if needed
        if (strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength - 3) . '...';
        }
        
        return $value;
    }
    
    /**
     * Check if inputs collection has custom (non-image) fields.
     *
     * @param  Collection  $inputs
     * @return bool
     */
    public static function hasCustomInputs(Collection $inputs): bool
    {
        return static::filterInputs($inputs)->isNotEmpty();
    }
}
