import axios from 'axios';

export function useVoucherTiming() {
    /**
     * Track when a voucher link is clicked.
     * Idempotent - only tracks the first click.
     */
    const trackClick = async (voucherCode: string): Promise<void> => {
        try {
            await axios.post(`/api/v1/vouchers/${voucherCode}/timing/click`);
            console.log('[Timing] Click tracked for voucher:', voucherCode);
        } catch (error) {
            // Silent failure - timing tracking shouldn't block user flow
            console.warn('[Timing] Failed to track click:', error);
        }
    };

    /**
     * Track when the redemption wizard starts (first step).
     */
    const trackRedemptionStart = async (voucherCode: string): Promise<void> => {
        try {
            await axios.post(`/api/v1/vouchers/${voucherCode}/timing/start`);
            console.log('[Timing] Redemption start tracked for voucher:', voucherCode);
        } catch (error) {
            // Silent failure - timing tracking shouldn't block user flow
            console.warn('[Timing] Failed to track redemption start:', error);
        }
    };

    return {
        trackClick,
        trackRedemptionStart,
    };
}
