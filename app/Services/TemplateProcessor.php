<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;

/**
 * Template processor for replacing {{ variable }} placeholders with actual values.
 * Supports dot-notation for nested array/object access (e.g., {{ user.profile.name }}).
 */
class TemplateProcessor
{
    /**
     * Process a template string with variable replacements.
     *
     * @param  string  $template  Template string with {{ variable }} placeholders
     * @param  array|object  $context  Data context for variable resolution
     * @param  array  $formatters  Optional custom formatters ['path' => callable]
     * @param  bool  $strict  Whether to throw on missing variables
     * @param  string  $fallback  Fallback value for missing variables
     * @return string Processed string
     *
     * @throws \Exception If strict mode and variable not found
     */
    public static function process(
        string $template,
        array|object $context,
        array $formatters = [],
        bool $strict = false,
        string $fallback = ''
    ): string {
        if (empty($template)) {
            return '';
        }

        // Convert object to array if needed
        $data = is_array($context) ? $context : json_decode(json_encode($context), true);

        // Match {{ variable.path }} patterns (with optional whitespace)
        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            function ($matches) use ($data, $formatters, $strict, $fallback) {
                $path = $matches[1];

                // Try direct path first (dot notation)
                $value = Arr::get($data, $path);

                // If not found and path has no dots, try recursive search
                if ($value === null && ! str_contains($path, '.')) {
                    $value = static::recursiveSearch($data, $path);
                }

                // Handle missing values
                if ($value === null) {
                    if ($strict) {
                        throw new \Exception("Template variable not found: {$path}");
                    }

                    return $fallback;
                }

                // Apply custom formatter if provided
                if (isset($formatters[$path]) && is_callable($formatters[$path])) {
                    return (string) $formatters[$path]($value);
                }

                // Format the value based on type
                return static::formatValue($value);
            },
            $template
        );
    }

    /**
     * Recursively search for a key in nested arrays/objects.
     *
     * @return mixed|null
     */
    protected static function recursiveSearch(array $data, string $key): mixed
    {
        // Check current level
        if (isset($data[$key])) {
            return $data[$key];
        }

        // Search nested arrays/objects recursively
        foreach ($data as $value) {
            if (is_array($value)) {
                $result = static::recursiveSearch($value, $key);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Format a value based on its type.
     */
    protected static function formatValue(mixed $value): string
    {
        // Handle arrays
        if (is_array($value)) {
            return implode(', ', $value);
        }

        // Handle objects with __toString
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        // Handle objects (convert to JSON)
        if (is_object($value)) {
            return json_encode($value);
        }

        // Handle booleans
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Default: convert to string
        return (string) $value;
    }

    /**
     * Check if a template contains any variables.
     */
    public static function hasVariables(string $template): bool
    {
        return preg_match('/\{\{\s*[\w.]+\s*\}\}/', $template) === 1;
    }

    /**
     * Extract all variable paths from a template.
     */
    public static function extractVariables(string $template): array
    {
        preg_match_all('/\{\{\s*([\w.]+)\s*\}\}/', $template, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Validate that all variables in template can be resolved.
     */
    public static function canResolve(string $template, array|object $context): bool
    {
        $variables = static::extractVariables($template);
        $data = is_array($context) ? $context : json_decode(json_encode($context), true);

        foreach ($variables as $path) {
            if (Arr::get($data, $path) === null) {
                return false;
            }
        }

        return true;
    }
}
