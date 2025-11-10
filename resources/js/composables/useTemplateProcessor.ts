/**
 * Composable for processing template strings with {{ variable }} placeholders.
 * Supports dot-notation for nested object access (e.g., {{ voucher.contact.mobile }}).
 * 
 * @example
 * const { processTemplate } = useTemplateProcessor(props, {
 *   formatters: {
 *     'amount': (val) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(val)
 *   }
 * });
 * const result = processTemplate('Amount: {{ amount }}');
 */

/**
 * Resolve a dot-notation path in an object
 * @param obj - The object to traverse
 * @param path - Dot-notation path (e.g., 'voucher.contact.mobile')
 * @returns The resolved value or undefined
 */
const resolvePath = (obj: any, path: string): any => {
    return path.split('.').reduce((current, prop) => {
        return current?.[prop];
    }, obj);
};

/**
 * Recursively search for a key in nested objects
 * @param obj - The object to search
 * @param key - The key to find
 * @returns The first matching value or undefined
 */
const recursiveSearch = (obj: any, key: string): any => {
    // Check current level
    if (obj && typeof obj === 'object' && key in obj) {
        return obj[key];
    }

    // Search nested objects/arrays recursively
    if (obj && typeof obj === 'object') {
        for (const prop in obj) {
            if (obj.hasOwnProperty(prop)) {
                const value = obj[prop];
                if (value && typeof value === 'object') {
                    const result = recursiveSearch(value, key);
                    if (result !== undefined) {
                        return result;
                    }
                }
            }
        }
    }

    return undefined;
};

/**
 * Format a value based on its type
 * @param value - The raw value
 * @returns Formatted value as string
 */
const formatValue = (value: any): string => {
    // Handle null/undefined
    if (value === null || value === undefined) {
        return '';
    }

    // Handle arrays
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    // Handle dates
    if (value instanceof Date) {
        return value.toISOString();
    }

    // Handle objects (convert to JSON)
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    // Handle booleans
    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    // Default: convert to string
    return String(value);
};

export interface TemplateProcessorOptions {
    /**
     * Custom formatters for specific paths
     * @example { 'amount': (val) => `₱${val.toFixed(2)}` }
     */
    formatters?: Record<string, (value: any) => string>;

    /**
     * Whether to throw on missing variables (default: false)
     */
    strict?: boolean;

    /**
     * Fallback value for missing variables (default: '')
     */
    fallback?: string;
}

export const useTemplateProcessor = (context: any, options?: TemplateProcessorOptions) => {
    const {
        formatters = {},
        strict = false,
        fallback = '',
    } = options || {};

    /**
     * Process a template string with {{ variable }} placeholders
     * @param template - Template string with {{ }} placeholders
     * @returns Processed string with variables replaced
     */
    const processTemplate = (template: string): string => {
        if (!template) return '';

        // Match {{ variable.path }} patterns (with optional whitespace)
        return template.replace(/\{\{\s*([\w.]+)\s*\}\}/g, (match, path) => {
            // Try direct path first (dot notation)
            let value = resolvePath(context, path);

            // If not found and path has no dots, try recursive search
            if ((value === undefined || value === null) && !path.includes('.')) {
                value = recursiveSearch(context, path);
            }

            // Handle missing values
            if (value === undefined || value === null) {
                if (strict) {
                    throw new Error(`Template variable not found: ${path}`);
                }
                return fallback;
            }

            // Apply custom formatter if provided
            if (formatters[path]) {
                return formatters[path](value);
            }

            // Format and return the value
            return formatValue(value);
        });
    };

    /**
     * Check if a template contains any variables
     * @param template - Template string to check
     * @returns True if template contains {{ }} patterns
     */
    const hasVariables = (template: string): boolean => {
        return /\{\{\s*[\w.]+\s*\}\}/.test(template);
    };

    /**
     * Extract all variable paths from a template
     * @param template - Template string
     * @returns Array of variable paths found
     */
    const extractVariables = (template: string): string[] => {
        const matches = template.matchAll(/\{\{\s*([\w.]+)\s*\}\}/g);
        return Array.from(matches, match => match[1]);
    };

    /**
     * Validate that all variables in template can be resolved
     * @param template - Template string
     * @returns True if all variables can be resolved
     */
    const canResolve = (template: string): boolean => {
        const variables = extractVariables(template);
        return variables.every(path => resolvePath(context, path) !== undefined);
    };

    return {
        processTemplate,
        hasVariables,
        extractVariables,
        canResolve,
    };
};
