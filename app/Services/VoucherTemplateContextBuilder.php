<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\VoucherSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\VoucherData;

/**
 * Build template context from VoucherData for use with TemplateProcessor.
 * Flattens nested structures and formats values for notification templates.
 */
class VoucherTemplateContextBuilder
{
    /**
     * Build template context array from VoucherData.
     *
     * @param  VoucherData  $voucher
     * @return array  Flat array of template variables
     */
    public static function build(VoucherData $voucher): array
    {
        $context = [
            // Basic voucher info
            'code' => $voucher->code,
            'status' => $voucher->status,
            'created_at' => $voucher->created_at?->toDateTimeString(),
            'redeemed_at' => $voucher->redeemed_at?->toDateTimeString(),
        ];

        // Amount and currency
        $context = array_merge($context, static::buildCashContext($voucher));

        // Contact information
        $context = array_merge($context, static::buildContactContext($voucher));

        // Flatten input fields
        $context = array_merge($context, static::buildInputsContext($voucher));

        // Owner information
        $context = array_merge($context, static::buildOwnerContext($voucher));

        // Global settings
        $context = array_merge($context, static::buildSettingsContext());

        return $context;
    }

    /**
     * Build cash/amount related context.
     *
     * @param  VoucherData  $voucher
     * @return array
     */
    protected static function buildCashContext(VoucherData $voucher): array
    {
        $context = [];

        // Get amount from cash object (if redeemed) or instructions
        $cashAmount = $voucher->cash?->amount ?? null;
        $instructionsAmount = $voucher->instructions?->cash?->amount ?? null;

        // Handle Money object from cash
        if ($cashAmount && is_object($cashAmount)) {
            $context['amount'] = $cashAmount->getAmount()->toFloat();
            $context['currency'] = $cashAmount->getCurrency()->getCurrencyCode();
            $context['formatted_amount'] = $cashAmount->formatTo(Number::defaultLocale());
        }
        // Handle float from instructions
        elseif ($instructionsAmount !== null) {
            $context['amount'] = (float) $instructionsAmount;
            $context['currency'] = $voucher->instructions->cash->currency ?? 'PHP';
            // Create Money object for formatting
            $money = \Brick\Money\Money::of($instructionsAmount, $context['currency']);
            $context['formatted_amount'] = $money->formatTo(Number::defaultLocale());
        }
        // No amount available
        else {
            $context['amount'] = null;
            $context['currency'] = $voucher->currency ?? 'PHP';
            $context['formatted_amount'] = 'N/A';
        }

        return $context;
    }

    /**
     * Build contact related context.
     *
     * @param  VoucherData  $voucher
     * @return array
     */
    protected static function buildContactContext(VoucherData $voucher): array
    {
        $context = [];

        if ($voucher->contact) {
            $context['mobile'] = $voucher->contact->mobile;
            $context['contact_name'] = $voucher->contact->name ?? null;
            $context['bank_account'] = $voucher->contact->bank_account ?? null;
            $context['bank_code'] = $voucher->contact->bank_code ?? null;
            $context['account_number'] = $voucher->contact->account_number ?? null;
        } else {
            $context['mobile'] = null;
            $context['contact_name'] = null;
            $context['bank_account'] = null;
            $context['bank_code'] = null;
            $context['account_number'] = null;
        }

        return $context;
    }

    /**
     * Build context from input fields.
     * Flattens inputs collection into individual variables.
     *
     * @param  VoucherData  $voucher
     * @return array
     */
    protected static function buildInputsContext(VoucherData $voucher): array
    {
        $context = [];

        // Flatten inputs collection - each input becomes a variable
        foreach ($voucher->inputs as $input) {
            $context[$input->name] = $input->value;
        }

        // Special handling for location field
        if (isset($context['location'])) {
            $formatted = static::formatLocation($context['location']);
            $context['formatted_address'] = $formatted;
        } else {
            $context['formatted_address'] = null;
        }

        return $context;
    }

    /**
     * Build owner related context.
     *
     * @param  VoucherData  $voucher
     * @return array
     */
    protected static function buildOwnerContext(VoucherData $voucher): array
    {
        $context = [];

        if ($voucher->owner) {
            $context['owner_name'] = $voucher->owner->name;
            $context['owner_email'] = $voucher->owner->email;
            $context['owner_mobile'] = $voucher->owner->mobile;
        } else {
            $context['owner_name'] = null;
            $context['owner_email'] = null;
            $context['owner_mobile'] = null;
        }

        return $context;
    }

    /**
     * Build context from global settings.
     *
     * @return array
     */
    protected static function buildSettingsContext(): array
    {
        try {
            $settings = app(VoucherSettings::class);
            $redemptionEndpoint = $settings->default_redemption_endpoint ?? '/disburse';
        } catch (\Spatie\LaravelSettings\Exceptions\MissingSettings $e) {
            // Fallback if settings not seeded yet
            $redemptionEndpoint = '/disburse';
        }
        
        return [
            'redemption_endpoint' => $redemptionEndpoint,
        ];
    }

    /**
     * Format location JSON to readable address string.
     *
     * @param  string  $locationJson
     * @return string|null
     */
    protected static function formatLocation(string $locationJson): ?string
    {
        try {
            $location = json_decode($locationJson, true);
            return Arr::get($location, 'address.formatted');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all available variable names for documentation/UI purposes.
     *
     * @return array
     */
    public static function getAvailableVariables(): array
    {
        return [
            // Basic
            'code' => 'Voucher code',
            'status' => 'Voucher status (active, redeemed, expired)',
            'created_at' => 'Creation timestamp',
            'redeemed_at' => 'Redemption timestamp',
            
            // Cash
            'amount' => 'Raw amount (e.g., 50.00)',
            'currency' => 'Currency code (e.g., PHP)',
            'formatted_amount' => 'Formatted amount (e.g., ₱50.00)',
            
            // Contact
            'mobile' => 'Contact mobile number',
            'contact_name' => 'Contact name',
            'bank_account' => 'Bank account identifier',
            'bank_code' => 'Bank code (e.g., GXCHPHM2XXX)',
            
            // Location
            'formatted_address' => 'Formatted address from location input',
            
            // Owner
            'owner_name' => 'Voucher owner name',
            'owner_email' => 'Voucher owner email',
            'owner_mobile' => 'Voucher owner mobile',
            
            // Settings
            'redemption_endpoint' => 'Redemption endpoint path (e.g., /disburse)',
            
            // Dynamic inputs
            'signature' => 'Signature data URL (if captured)',
            'location' => 'Raw location JSON (if captured)',
            'account_number' => 'Account number (if captured)',
            '...' => 'Any custom input field by name',
        ];
    }
}
