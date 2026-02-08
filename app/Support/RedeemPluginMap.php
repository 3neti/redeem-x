<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Maps plugins to their input fields and configuration.
 *
 * Plugins are defined in config/x-change.php under 'redeem.plugins'.
 * Each plugin has: enabled, fields, page, session_key, validation, etc.
 */
class RedeemPluginMap
{
    /**
     * Get input fields assigned to a plugin.
     *
     * @param  string  $plugin  Plugin key (e.g., 'inputs', 'signature')
     * @return array<int, \LBHurtado\Voucher\Enums\VoucherInputField>
     */
    public static function fieldsFor(string $plugin): array
    {
        $config = Config::get("x-change.redeem.plugins.{$plugin}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return [];
        }

        return $config['fields'] ?? [];
    }

    /**
     * Get the Inertia page component for a plugin.
     *
     * @param  string  $plugin  Plugin key
     * @return string|null Page path (e.g., 'Redeem/Inputs')
     */
    public static function pageFor(string $plugin): ?string
    {
        $config = Config::get("x-change.redeem.plugins.{$plugin}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return null;
        }

        return $config['page'] ?? null;
    }

    /**
     * Get the session key for a plugin.
     *
     * @param  string  $plugin  Plugin key
     * @return string|null Session key (e.g., 'inputs', 'signature')
     */
    public static function sessionKeyFor(string $plugin): ?string
    {
        $config = Config::get("x-change.redeem.plugins.{$plugin}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return null;
        }

        return $config['session_key'] ?? null;
    }

    /**
     * Get validation rules for a plugin.
     *
     * @param  string  $plugin  Plugin key
     * @return array<string, array<string>>
     */
    public static function validationFor(string $plugin): array
    {
        $config = Config::get("x-change.redeem.plugins.{$plugin}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return [];
        }

        return $config['validation'] ?? [];
    }

    /**
     * Return a list of all enabled plugin keys.
     *
     * @return array<int, string>
     */
    public static function allPlugins(): array
    {
        return collect(Config::get('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->toArray();
    }

    /**
     * Return the first enabled plugin, or null if none.
     */
    public static function firstPlugin(): ?string
    {
        return collect(Config::get('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->first();
    }

    /**
     * Return the next plugin in the sequence after the given plugin.
     *
     * @param  string  $current  Current plugin key
     * @return string|null Next plugin key, or null if last
     */
    public static function nextPluginAfter(string $current): ?string
    {
        $plugins = collect(Config::get('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->values();

        $index = $plugins->search($current);

        if ($index === false) {
            return null;
        }

        return $plugins->get($index + 1);
    }

    /**
     * Check if a plugin is enabled.
     *
     * @param  string  $plugin  Plugin key
     */
    public static function isEnabled(string $plugin): bool
    {
        $config = Config::get("x-change.redeem.plugins.{$plugin}");

        return (bool) ($config['enabled'] ?? false);
    }

    /**
     * Get full configuration for a plugin.
     *
     * @param  string  $plugin  Plugin key
     * @return array<string, mixed>|null
     */
    public static function configFor(string $plugin): ?array
    {
        $config = Config::get("x-change.redeem.plugins.{$plugin}");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return null;
        }

        return $config;
    }
}
