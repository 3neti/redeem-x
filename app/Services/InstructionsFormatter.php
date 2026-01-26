<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;

/**
 * Format VoucherInstructionsData for display in notifications.
 * Supports JSON and human-readable text formats.
 */
class InstructionsFormatter
{
    /**
     * SMS character limit for single segment (Unicode with emoji)
     */
    private const SMS_SINGLE_SEGMENT = 70;

    /**
     * SMS character limit for reasonable length (2-3 segments)
     */
    private const SMS_REASONABLE_LENGTH = 200;

    /**
     * Format instructions as pretty-printed JSON.
     */
    public static function formatAsJson(VoucherInstructionsData $instructions): string
    {
        $data = [
            'cash' => [
                'amount' => $instructions->cash->amount,
                'currency' => $instructions->cash->currency,
            ],
        ];

        // Add settlement rail if set
        if ($instructions->cash->settlement_rail) {
            $rail = is_object($instructions->cash->settlement_rail) 
                ? $instructions->cash->settlement_rail->value 
                : $instructions->cash->settlement_rail;
            $data['cash']['settlement_rail'] = $rail;
            $data['cash']['fee_strategy'] = $instructions->cash->fee_strategy ?? 'absorb';
        }

        // Add input fields if any
        if ($instructions->inputs && $instructions->inputs->fields) {
            $data['inputs'] = array_map(fn($f) => $f->value, $instructions->inputs->fields);
        }

        // Add validations if present
        if ($instructions->validation) {
            if ($instructions->validation->location) {
                $data['validation']['location'] = [
                    'required' => $instructions->validation->location->required,
                    'target_lat' => $instructions->validation->location->target_lat,
                    'target_lng' => $instructions->validation->location->target_lng,
                    'radius_meters' => $instructions->validation->location->radius_meters,
                    'on_failure' => $instructions->validation->location->on_failure,
                ];
            }

            if ($instructions->validation->time) {
                $time = $instructions->validation->time;
                if ($time->window) {
                    $data['validation']['time']['window'] = [
                        'start_time' => $time->window->start_time,
                        'end_time' => $time->window->end_time,
                        'timezone' => $time->window->timezone,
                    ];
                }
                if ($time->limit_minutes) {
                    $data['validation']['time']['limit_minutes'] = $time->limit_minutes;
                }
            }
        }

        // Add rider if present
        if ($instructions->rider && ($instructions->rider->message || $instructions->rider->url)) {
            if ($instructions->rider->message) {
                $data['rider']['message'] = $instructions->rider->message;
            }
            if ($instructions->rider->url) {
                $data['rider']['url'] = $instructions->rider->url;
            }
        }

        // Add TTL/expiry info
        if ($instructions->ttl) {
            $data['ttl'] = $instructions->ttl->spec();
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Format instructions as human-readable text.
     */
    public static function formatAsHuman(VoucherInstructionsData $instructions): string
    {
        $lines = [];

        // Amount
        $money = \Brick\Money\Money::of($instructions->cash->amount, $instructions->cash->currency);
        $lines[] = "Amount: " . $money->formatTo(Number::defaultLocale());

        // Settlement rail
        if ($instructions->cash->settlement_rail) {
            $rail = is_object($instructions->cash->settlement_rail) 
                ? $instructions->cash->settlement_rail->value 
                : $instructions->cash->settlement_rail;
            $feeStrategy = $instructions->cash->fee_strategy ?? 'absorb';
            $feeText = match($feeStrategy) {
                'absorb' => 'fee absorbed by issuer',
                'include' => 'fee deducted from amount',
                'add' => 'fee added to disbursement',
                default => $feeStrategy,
            };
            $lines[] = "Rail: {$rail} ({$feeText})";
        }

        // Required inputs
        if ($instructions->inputs && $instructions->inputs->fields) {
            $fieldLabels = array_map(
                fn($field) => static::getInputFieldLabel($field),
                $instructions->inputs->fields
            );
            $lines[] = "Inputs: " . implode(', ', $fieldLabels);
        }

        // Location validation
        if ($instructions->validation?->location) {
            $loc = $instructions->validation->location;
            $action = $loc->on_failure === 'block' ? 'Required' : 'Warning';
            $lines[] = "Location: Within {$loc->radius_meters}m of ({$loc->target_lat}, {$loc->target_lng}). {$action}.";
        }

        // Time validation
        if ($instructions->validation?->time) {
            $time = $instructions->validation->time;
            if ($time->window) {
                $lines[] = "Time: {$time->window->start_time} - {$time->window->end_time} ({$time->window->timezone})";
            }
            if ($time->limit_minutes) {
                $lines[] = "Duration: Complete within {$time->limit_minutes} minutes";
            }
        }

        // Rider message
        if ($instructions->rider?->message) {
            $lines[] = "Message: {$instructions->rider->message}";
        }

        // TTL
        if ($instructions->ttl) {
            $lines[] = "TTL: {$instructions->ttl->spec()}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format instructions for SMS (with truncation).
     */
    public static function formatForSms(VoucherInstructionsData $instructions, string $format): ?string
    {
        if ($format === 'none') {
            return null;
        }

        $formatted = $format === 'json'
            ? static::formatAsJson($instructions)
            : static::formatAsHuman($instructions);

        // If already reasonable length, return as-is
        if (mb_strlen($formatted) <= static::SMS_REASONABLE_LENGTH) {
            return $formatted;
        }

        // Too long - create compact version
        $compact = static::formatCompact($instructions);
        
        // If still too long, truncate
        if (mb_strlen($compact) > static::SMS_REASONABLE_LENGTH) {
            return mb_substr($compact, 0, static::SMS_REASONABLE_LENGTH - 3) . '...';
        }

        return $compact;
    }

    /**
     * Format instructions for email (no truncation).
     */
    public static function formatForEmail(VoucherInstructionsData $instructions, string $format): ?string
    {
        if ($format === 'none') {
            return null;
        }

        return $format === 'json'
            ? static::formatAsJson($instructions)
            : static::formatAsHuman($instructions);
    }

    /**
     * Create ultra-compact format for SMS.
     */
    protected static function formatCompact(VoucherInstructionsData $instructions): string
    {
        $parts = [];

        // Inputs (priority 1)
        if ($instructions->inputs && $instructions->inputs->fields) {
            $fields = array_map(fn($f) => ucfirst($f->value), $instructions->inputs->fields);
            $parts[] = "Inputs: " . implode(', ', $fields);
        }

        // Rail (priority 2)
        if ($instructions->cash->settlement_rail) {
            $rail = is_object($instructions->cash->settlement_rail) 
                ? $instructions->cash->settlement_rail->value 
                : $instructions->cash->settlement_rail;
            $parts[] = "Rail: {$rail}";
        }

        // Location validation (priority 3)
        if ($instructions->validation?->location) {
            $loc = $instructions->validation->location;
            $parts[] = "Location: {$loc->radius_meters}m radius";
        }

        // TTL (priority 4)
        if ($instructions->ttl) {
            $parts[] = "TTL: {$instructions->ttl->spec()}";
        }

        return implode("\n", $parts);
    }

    /**
     * Get friendly label for input field.
     */
    protected static function getInputFieldLabel(VoucherInputField $field): string
    {
        return match ($field) {
            VoucherInputField::NAME => 'Name',
            VoucherInputField::EMAIL => 'Email',
            VoucherInputField::MOBILE => 'Mobile',
            VoucherInputField::REFERENCE_CODE => 'Reference',
            VoucherInputField::SIGNATURE => 'Signature',
            VoucherInputField::ADDRESS => 'Address',
            VoucherInputField::BIRTH_DATE => 'Birth Date',
            VoucherInputField::GROSS_MONTHLY_INCOME => 'Income',
            VoucherInputField::LOCATION => 'Location',
            VoucherInputField::OTP => 'OTP',
            VoucherInputField::SELFIE => 'Selfie',
            VoucherInputField::KYC => 'KYC',
            default => ucfirst($field->value),
        };
    }
}
