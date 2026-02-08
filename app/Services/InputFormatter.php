<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\DataCollection;

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
        'map',  // Location map snapshot
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
     * @param  Collection|DataCollection  $inputs  Collection of InputData objects
     */
    public static function formatForSms(Collection|DataCollection $inputs): string
    {
        $filtered = static::filterInputs($inputs);

        if ($filtered->isEmpty()) {
            return '';
        }

        // Format as: field: value | field: value
        $formatted = $filtered
            ->map(function ($input) {
                // Handle both objects and arrays (after DataCollection conversion)
                $name = is_array($input) ? $input['name'] : $input->name;
                $value = is_array($input) ? $input['value'] : $input->value;

                $label = Str::title(str_replace('_', ' ', $name));
                $formattedValue = static::truncateValue($value, self::MAX_VALUE_LENGTH);

                return "{$label}: {$formattedValue}";
            })
            ->join(' | ');

        // Truncate if too long
        if (strlen($formatted) > self::MAX_SMS_LENGTH) {
            $formatted = substr($formatted, 0, self::MAX_SMS_LENGTH - 3).'...';
        }

        return $formatted;
    }

    /**
     * Format inputs for email display.
     *
     * Returns an array of [label => value] pairs for use in email template.
     *
     * @param  Collection|DataCollection  $inputs  Collection of InputData objects
     */
    public static function formatForEmail(Collection|DataCollection $inputs): array
    {
        $filtered = static::filterInputs($inputs);

        if ($filtered->isEmpty()) {
            return [];
        }

        return $filtered
            ->mapWithKeys(function ($input) {
                // Handle both objects and arrays (after DataCollection conversion)
                $name = is_array($input) ? $input['name'] : $input->name;
                $value = is_array($input) ? $input['value'] : $input->value;

                $label = Str::title(str_replace('_', ' ', $name));
                $formattedValue = static::truncateValue($value, 200); // Longer limit for email

                return [$label => $formattedValue];
            })
            ->toArray();
    }

    /**
     * Filter inputs to exclude images and sensitive fields.
     */
    protected static function filterInputs(Collection|DataCollection $inputs): Collection
    {
        $filtered = $inputs->filter(function ($input) {
            // Handle both objects and arrays
            $name = is_array($input) ? $input['name'] : $input->name;
            $value = is_array($input) ? $input['value'] : $input->value;

            // Exclude special fields
            if (in_array($name, self::EXCLUDED_FIELDS)) {
                return false;
            }

            // Exclude empty values
            if (empty($value)) {
                return false;
            }

            return true;
        });

        // Convert DataCollection to Collection if needed
        if ($filtered instanceof DataCollection) {
            return collect($filtered->toArray());
        }

        return $filtered;
    }

    /**
     * Truncate long values with ellipsis.
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
            return substr($value, 0, $maxLength - 3).'...';
        }

        return $value;
    }

    /**
     * Check if inputs collection has custom (non-image) fields.
     */
    public static function hasCustomInputs(Collection|DataCollection $inputs): bool
    {
        return static::filterInputs($inputs)->isNotEmpty();
    }
}
