import { ref, watch, computed, type Ref } from 'vue';
import axios from '@/lib/axios';
import { useApiError, type ApiError } from './useApiError';
import { debounce } from 'lodash';

// Debug flag - set to false to suppress console logs
const DEBUG = false;

export interface ChargeItem {
    index: string;
    label: string;
    value: string | number | boolean;
    unit_price: number;
    quantity: number;
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
    validation?: {
        location?: Record<string, unknown> | null;
        time?: Record<string, unknown> | null;
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
        if (DEBUG) console.log('[useChargeBreakdown] payload computed called, instructions:', instructions.value);
        return instructions.value;
    });

    const calculateCharges = async (): Promise<ChargeBreakdown | null> => {
        // Don't calculate if cash amount is not set
        if (!instructions.value.cash?.amount || instructions.value.cash.amount <= 0) {
            if (DEBUG) console.log('[useChargeBreakdown] Skipping calculation - no amount:', instructions.value.cash?.amount);
            breakdown.value = null;
            return null;
        }

        if (DEBUG) {
            console.log('[useChargeBreakdown] Calculating charges for:', instructions.value);
            console.log('[useChargeBreakdown] Payload validation field:', instructions.value.validation);
        }
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/v1/calculate-charges', instructions.value);
            if (DEBUG) {
                console.log('[useChargeBreakdown] Response received:', response.data);
                console.log('[useChargeBreakdown] Breakdown items:', response.data.breakdown);
            }
            const data = response.data;

            // Use the breakdown directly from backend (already formatted)
            const formatted: ChargeBreakdown = {
                breakdown: data.breakdown,
                total: data.total,
                total_formatted: data.total_formatted || `₱${(data.total / 100).toFixed(2)}`,
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
                if (DEBUG) console.log('[useChargeBreakdown] Watch triggered, payload:', payload.value);
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
