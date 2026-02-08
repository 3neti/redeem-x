<?php

namespace LBHurtado\Merchant\Services;

use Illuminate\Support\Facades\DB;

class VendorAliasService
{
    /**
     * Normalize alias to uppercase and trim whitespace.
     */
    public function normalize(string $alias): string
    {
        return strtoupper(trim($alias));
    }

    /**
     * Validate alias format.
     *
     * Rules:
     * - Must be ASCII only
     * - Must start with a letter (A-Z)
     * - Must be 3-8 characters long
     * - Can contain uppercase letters and digits only
     */
    public function validate(string $alias): bool
    {
        // Must be ASCII only
        if (! mb_check_encoding($alias, 'ASCII')) {
            return false;
        }

        // Regex: starts with letter, 3-8 chars total, uppercase letters/digits only
        // Default pattern - can be overridden by config
        $pattern = '^[A-Z][A-Z0-9]{2,7}$';

        // Try to get from config if Laravel app is available
        try {
            if (function_exists('app') && app()->has('config')) {
                $pattern = app('config')->get('merchant.alias.pattern', $pattern);
            }
        } catch (\Throwable $e) {
            // Fallback to default pattern if config unavailable
        }

        return (bool) preg_match('/'.$pattern.'/', $alias);
    }

    /**
     * Check if alias is in the reserved list.
     * Case-insensitive check.
     */
    public function isReserved(string $alias): bool
    {
        $normalized = $this->normalize($alias);

        return DB::table('reserved_vendor_aliases')
            ->where('alias', $normalized)
            ->exists();
    }

    /**
     * Check if alias is available (not taken and not reserved).
     */
    public function isAvailable(string $alias): bool
    {
        $normalized = $this->normalize($alias);

        // Check if reserved
        if ($this->isReserved($normalized)) {
            return false;
        }

        // Check if already taken
        return ! DB::table('vendor_aliases')
            ->where('alias', $normalized)
            ->exists();
    }
}
