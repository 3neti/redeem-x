import { ref, watch, type Ref } from 'vue';
import axios from '@/lib/axios';
import { useApiError, type ApiError } from './useApiError';
import { useDebounce } from './useDebounce';

export interface ChargeItem {
    index: string;
    label: string;
    value: string | number | boolean;
    price: number;
    price_formatted: string;
    currency: string;
}

export interface ChargeBreakdown {
    breakdown: ChargeItem[];
    total: number;
    total_formatted: string;
}

export interface InstructionsData {
    cash?: {
        amount?: number;
        currency?: string;
        validation?: Record<string, unknown>;
    };
    inputs?: {
        fields?: string[];
    };
    feedback?: {
        email?: string;
        mobile?: string;
        webhook?: string;
    };
    rider?: {
        message?: string;
        url?: string;
    };
    count?: number;
    prefix?: string;
    mask?: string;
    ttl?: string;
}

export function useChargeBreakdown(
    instructions: Ref<InstructionsData>,
    options: { debounce?: number; autoCalculate?: boolean } = {}
) {
    const { debounce: debounceMs = 500, autoCalculate = true } = options;

    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const breakdown = ref<ChargeBreakdown | null>(null);
    const { handleError } = useApiError();

    const calculateCharges = async (): Promise<ChargeBreakdown | null> => {
        // Don't calculate if cash amount is not set
        if (!instructions.value.cash?.amount || instructions.value.cash.amount <= 0) {
            breakdown.value = null;
            return null;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/v1/calculate-charges', instructions.value);
            const data = response.data;

            // Format the breakdown for display
            const formatted: ChargeBreakdown = {
                breakdown: data.breakdown.map((item: ChargeItem) => ({
                    ...item,
                    price_formatted: `₱${(item.price / 100).toFixed(2)}`,
                })),
                total: data.total,
                total_formatted: `₱${(data.total / 100).toFixed(2)}`,
            };

            breakdown.value = formatted;
            return formatted;
        } catch (err) {
            error.value = handleError(err);
            breakdown.value = null;
            return null;
        } finally {
            loading.value = false;
        }
    };

    // Debounced calculation function
    const debouncedCalculate = useDebounce(calculateCharges, debounceMs);

    // Auto-calculate when instructions change (if enabled)
    if (autoCalculate) {
        watch(
            instructions,
            () => {
                debouncedCalculate();
            },
            { deep: true }
        );
    }

    return {
        loading,
        error,
        breakdown,
        calculateCharges,
        refresh: debouncedCalculate,
    };
}
