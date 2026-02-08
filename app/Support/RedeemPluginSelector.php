<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Determines which redemption plugins are required for a voucher.
 *
 * This class implements the core plugin selection logic:
 * 1. Get voucher's required input fields from instructions
 * 2. Check each enabled plugin's fields
 * 3. Select plugins that have intersecting fields
 *
 * This enables the dynamic redemption flow!
 */
class RedeemPluginSelector
{
    /**
     * Determine the enabled plugins required by a voucher's inputs.
     *
     * Returns a collection of plugin keys (e.g., ['inputs', 'signature'])
     * based on the intersection of voucher's required fields and plugin fields.
     *
     * @param  Voucher  $voucher  The voucher being redeemed
     * @return Collection<int, string> Collection of plugin keys
     */
    public static function fromVoucher(Voucher $voucher): Collection
    {
        // 🧩 Normalize voucher input fields to string values
        $voucherFieldKeys = collect($voucher->instructions->inputs->fields)
            ->map(fn (VoucherInputField $field) => $field->value)
            ->values()
            ->all();

        // 🧠 Determine enabled plugins with intersecting fields
        $plugins = collect(Config::get('x-change.redeem.plugins', []))
            ->filter(function ($pluginConfig) use ($voucherFieldKeys) {
                // Normalize plugin fields to string values
                $pluginFieldKeys = collect($pluginConfig['fields'] ?? [])
                    ->map(fn ($field) => $field instanceof VoucherInputField ? $field->value : $field)
                    ->all();

                // Plugin is selected if:
                // 1. It's enabled
                // 2. It has at least one field that the voucher requires
                return ($pluginConfig['enabled'] ?? false)
                    && count(array_intersect($pluginFieldKeys, $voucherFieldKeys)) > 0;
            })
            ->keys()
            ->values();

        Log::info('[RedeemPluginSelector] Determined eligible plugins for voucher', [
            'voucher' => $voucher->code,
            'required_fields' => $voucherFieldKeys,
            'resolved_plugins' => $plugins->all(),
        ]);

        return $plugins;
    }

    /**
     * Get the fields that a plugin should collect for a specific voucher.
     *
     * Returns only the intersection of plugin fields and voucher's required fields.
     *
     * @param  string  $plugin  Plugin key
     * @param  Voucher  $voucher  The voucher being redeemed
     * @return array<int, string> Array of field names (string values)
     */
    public static function requestedFieldsFor(string $plugin, Voucher $voucher): array
    {
        $pluginFields = RedeemPluginMap::fieldsFor($plugin);
        $pluginFieldKeys = array_map(
            fn (VoucherInputField $field) => $field->value,
            $pluginFields
        );

        $voucherFieldKeys = array_map(
            fn (VoucherInputField $field) => $field->value,
            $voucher->instructions->inputs->fields
        );

        return array_values(array_intersect($pluginFieldKeys, $voucherFieldKeys));
    }

    /**
     * Check if a voucher requires a specific plugin.
     *
     * @param  Voucher  $voucher  The voucher being redeemed
     * @param  string  $plugin  Plugin key to check
     */
    public static function voucherRequiresPlugin(Voucher $voucher, string $plugin): bool
    {
        return self::fromVoucher($voucher)->contains($plugin);
    }

    /**
     * Get the first required plugin for a voucher.
     *
     * @param  Voucher  $voucher  The voucher being redeemed
     * @return string|null First plugin key, or null if no plugins required
     */
    public static function firstPluginFor(Voucher $voucher): ?string
    {
        return self::fromVoucher($voucher)->first();
    }

    /**
     * Get the next plugin in the sequence for a voucher.
     *
     * @param  Voucher  $voucher  The voucher being redeemed
     * @param  string  $current  Current plugin key
     * @return string|null Next plugin key, or null if last
     */
    public static function nextPluginFor(Voucher $voucher, string $current): ?string
    {
        $plugins = self::fromVoucher($voucher);
        $currentIndex = $plugins->search($current);

        if ($currentIndex === false) {
            return null;
        }

        return $plugins->get($currentIndex + 1);
    }
}
