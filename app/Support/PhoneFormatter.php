<?php

declare(strict_types=1);

namespace App\Support;

use Propaganistas\LaravelPhone\PhoneNumber;

class PhoneFormatter
{
    /**
     * Format a phone number for display.
     *
     * Reads format from config('app.phone_display_format').
     * Default: 'international_grouped' → +63 (917) 301-1987
     */
    public static function forDisplay(string $number, ?string $country = null): string
    {
        $format = config('app.phone_display_format', 'international_grouped');

        try {
            // Try auto-detect (works for +639... E.164 input)
            $phone = $country ? phone($number, $country) : phone($number);

            // getCountry() can return empty string without throwing
            if (empty($phone->getCountry())) {
                throw new \RuntimeException('No country detected');
            }
        } catch (\Throwable) {
            try {
                // Fallback to PH for national (09...) and stripped E.164 (639...)
                $phone = phone($number, 'PH');
            } catch (\Throwable) {
                return $number;
            }
        }

        try {
            return match ($format) {
                'international_grouped' => self::internationalGrouped($phone),
                'international' => $phone->formatInternational(),
                'national' => $phone->formatForMobileDialingInCountry($phone->getCountry()),
                'e164' => $phone->formatE164(),
                default => self::internationalGrouped($phone),
            };
        } catch (\Throwable) {
            return $number;
        }
    }

    /**
     * Format as +63 (917) 301-1987
     *
     * Derives from formatInternational() which returns "+63 917 301 1987",
     * then regroups the subscriber digits.
     */
    private static function internationalGrouped(PhoneNumber $phone): string
    {
        $intl = $phone->formatInternational(); // +63 917 301 1987

        // Extract dial code and subscriber digits
        // formatInternational returns: +{dialCode} {spaced digits}
        if (preg_match('/^\+(\d+)\s+(.+)$/', $intl, $m)) {
            $dialCode = $m[1];
            $subscriberDigits = preg_replace('/\D/', '', $m[2]);

            if (strlen($subscriberDigits) === 10) {
                $area = substr($subscriberDigits, 0, 3);
                $mid = substr($subscriberDigits, 3, 3);
                $last = substr($subscriberDigits, 6, 4);

                return "+{$dialCode} ({$area}) {$mid}-{$last}";
            }
        }

        // Fallback for non-standard formats
        return $intl;
    }
}
