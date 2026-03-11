/**
 * Phone number formatting utilities
 *
 * Formats phone numbers for display: +63 (917) 301-1987
 * Accepts: 09171234567, 639171234567, +639171234567
 */

/**
 * Extract subscriber digits from any PH phone format.
 * Returns 10 digits (e.g. 9173011987) or the raw digits if unparseable.
 */
function extractSubscriber(number: string): { dialCode: string; subscriber: string } {
    const digitsOnly = number.replace(/\D/g, '');

    // +63XXXXXXXXXX or 63XXXXXXXXXX (12 digits)
    if (digitsOnly.startsWith('63') && digitsOnly.length === 12) {
        return { dialCode: '63', subscriber: digitsOnly.substring(2) };
    }

    // 0XXXXXXXXXX (11 digits, national)
    if (digitsOnly.startsWith('0') && digitsOnly.length === 11) {
        return { dialCode: '63', subscriber: digitsOnly.substring(1) };
    }

    // 9XXXXXXXXX (10 digits, already subscriber)
    if (digitsOnly.startsWith('9') && digitsOnly.length === 10) {
        return { dialCode: '63', subscriber: digitsOnly };
    }

    return { dialCode: '63', subscriber: digitsOnly };
}

export function usePhoneFormat() {
    /**
     * Format as +63 (917) 301-1987
     */
    const formatForDisplay = (number: string): string => {
        if (!number) return '';

        const { dialCode, subscriber } = extractSubscriber(number);

        if (subscriber.length === 10) {
            const area = subscriber.substring(0, 3);
            const mid = subscriber.substring(3, 6);
            const last = subscriber.substring(6, 10);
            return `+${dialCode} (${area}) ${mid}-${last}`;
        }

        // Fallback: return as-is
        return number;
    };

    return {
        formatForDisplay,
    };
}
