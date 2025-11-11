import { ref, watch, computed, type Ref } from 'vue';
import axios from '@/lib/axios';
import { useApiError, type ApiError } from './useApiError';
import { debounce } from 'lodash';

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
    
    // Create computed payload like x-change does - this is key for reactivity!
    const payload = computed(() => {
        console.log('[useChargeBreakdown] payload computed called, instructions:', instructions.value);
        return instructions.value;
    });

    const calculateCharges = async (): Promise<ChargeBreakdown | null> => {
        // Don't calculate if cash amount is not set
        if (!instructions.value.cash?.amount || instructions.value.cash.amount <= 0) {
            console.log('[useChargeBreakdown] Skipping calculation - no amount:', instructions.value.cash?.amount);
            breakdown.value = null;
            return null;
        }

        console.log('[useChargeBreakdown] Calculating charges for:', instructions.value);
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/v1/calculate-charges', instructions.value);
            console.log('[useChargeBreakdown] Response received:', response.data);
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

    // Auto-calculate when payload changes (if enabled)
    if (autoCalculate) {
        watch(
            payload,
            debounce(() => {
                console.log('[useChargeBreakdown] Watch triggered, payload:', payload.value);
                calculateCharges();
            }, debounceMs),
            { deep: true, immediate: true }
        );
    }

    return {
        loading,
        error,
        breakdown,
        calculateCharges,
        refresh: calculateCharges,
    };
}
