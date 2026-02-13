/**
 * Phone number formatting utilities
 * 
 * Formats Philippine mobile numbers in a readable format:
 * - Input: 09171234567 or 639171234567 or +639171234567
 * - Output: (917) 301-1987
 */

export function usePhoneFormat() {
    /**
     * Format phone number with parentheses: (917) 301-1987
     */
    const formatWithParentheses = (number: string): string => {
        if (!number) return '';
        
        // Remove any existing formatting and country codes
        let digitsOnly = number.replace(/\D/g, '');
        
        // Remove country code if present (63 for Philippines)
        if (digitsOnly.startsWith('63') && digitsOnly.length === 12) {
            digitsOnly = digitsOnly.substring(2); // Remove '63'
        }
        
        // Remove leading 0 if present (for consistent formatting)
        if (digitsOnly.startsWith('0')) {
            digitsOnly = digitsOnly.substring(1); // Remove '0'
        }
        
        if (digitsOnly.length >= 10) {
            // Full format: (917) 301-1987
            const firstThree = digitsOnly.substring(0, 3);
            const middleThree = digitsOnly.substring(3, 6);
            const lastFour = digitsOnly.substring(6, 10);
            return `(${firstThree}) ${middleThree}-${lastFour}`;
        } else if (digitsOnly.length >= 6) {
            // Partial format: (917) 301-xxx
            const firstThree = digitsOnly.substring(0, 3);
            const middleThree = digitsOnly.substring(3, 6);
            const rest = digitsOnly.substring(6);
            return `(${firstThree}) ${middleThree}${rest ? '-' + rest : ''}`;
        } else if (digitsOnly.length >= 3) {
            // Partial format: (917) xxx
            const firstThree = digitsOnly.substring(0, 3);
            const rest = digitsOnly.substring(3);
            return `(${firstThree})${rest ? ' ' + rest : ''}`;
        }
        
        return number;
    };
    
    /**
     * Format phone number for display with optional prefix
     * Examples:
     * - formatForDisplay('09171234567') => '(917) 301-1987'
     * - formatForDisplay('639171234567') => '(917) 301-1987'
     * - formatForDisplay('+639171234567') => '(917) 301-1987'
     */
    const formatForDisplay = (number: string): string => {
        return formatWithParentheses(number);
    };
    
    return {
        formatWithParentheses,
        formatForDisplay,
    };
}
