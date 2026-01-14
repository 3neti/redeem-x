import { usePage } from '@inertiajs/vue3';

export interface SmartJsonFormatOptions {
  defaultFields?: {
    reference?: boolean;
    date?: boolean;
    issuer?: boolean;
  };
  customFields?: Record<string, any>;
}

export function useSmartJsonFormat(options: SmartJsonFormatOptions = {}) {
  const page = usePage();

  const shouldAutoFormat = (input: string): boolean => {
    const trimmed = input.trim();

    // Skip if empty
    if (!trimmed) return false;

    // Skip if already valid JSON
    try {
      JSON.parse(trimmed);
      return false;
    } catch {}

    // Skip if contains braces (user attempting JSON)
    if (trimmed.includes('{') || trimmed.includes('}')) return false;

    return true;
  };

  const autoFormat = (input: string): string => {
    if (!shouldAutoFormat(input)) return input;

    const trimmed = input.trim();
    const user = page.props.auth?.user;
    const fields = options.defaultFields || {
      reference: true,
      date: true,
      issuer: true,
    };

    const formatted: Record<string, any> = {};

    if (fields.reference) {
      formatted.reference = trimmed;
    }

    if (fields.date) {
      formatted.date = new Date().toISOString().split('T')[0];
    }

    if (fields.issuer) {
      formatted.issuer = user?.name || user?.email || 'Unknown';
    }

    // Merge custom fields if provided
    if (options.customFields) {
      Object.assign(formatted, options.customFields);
    }

    return JSON.stringify(formatted, null, 2);
  };

  return {
    shouldAutoFormat,
    autoFormat,
  };
}
