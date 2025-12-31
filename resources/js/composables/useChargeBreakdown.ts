import { ref, watch, computed, type Ref } from 'vue';
import axios from '@/lib/axios';
import { useApiError, type ApiError } from './useApiError';
import { debounce } from 'lodash';

// Debug flag - set to false to suppress console logs
const DEBUG = false;

export interface ChargeItem {
    index: string;
    label: string;
    category: string;
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

export interface ChargeBreakdownOptions {
    debounce?: number;
    autoCalculate?: boolean;
    faceValueLabel?: string; // Customizable label for face value
}

export function useChargeBreakdown(
    instructions: Ref<InstructionsData>,
    options: ChargeBreakdownOptions = {}
) {
    const { debounce: debounceMs = 500, autoCalculate = true, faceValueLabel = 'Face Value (Escrowed)' } = options;

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
            // Always log for debugging
            console.log('[useChargeBreakdown] Request:', instructions.value);
            console.log('[useChargeBreakdown] Response received:', response.data);
            console.log('[useChargeBreakdown] Breakdown items:', response.data.breakdown);
            
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

    // Total deduction = Face value (escrowed) + Charges
    const totalDeduction = computed(() => {
        const faceValue = instructions.value.cash?.amount || 0;
        const count = instructions.value.count || 1;
        const charges = (breakdown.value?.total || 0) / 100; // Convert centavos to pesos
        return (faceValue * count) + charges;
    });
    
    // Backward compatibility alias
    const totalCost = computed(() => totalDeduction.value);
    
    // JSON preview of wallet deduction breakdown (all amounts in major units - pesos)
    const deductionJson = computed(() => {
        if (!breakdown.value) return null;
        
        const faceValue = instructions.value.cash?.amount || 0;
        const count = instructions.value.count || 1;
        const faceValueTotal = faceValue * count;
        const chargesTotal = breakdown.value.total / 100;
        const grandTotal = totalDeduction.value;
        
        // Helper to format currency
        const formatCurrency = (amount: number, currency: string = 'PHP') => {
            return `₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };
        
        return {
            face_value: {
                label: faceValueLabel,
                amount: faceValue, // pesos
                amount_formatted: formatCurrency(faceValue),
                quantity: count,
                total: faceValueTotal, // pesos
                total_formatted: formatCurrency(faceValueTotal),
                currency: instructions.value.cash?.currency || 'PHP',
                description: 'Amount to be disbursed to redeemer (escrowed)',
            },
            charges: {
                items: breakdown.value.breakdown.map(item => ({
                    index: item.index,
                    label: item.label,
                    category: item.category || 'other',
                    unit_price: item.unit_price / 100, // convert centavos to pesos
                    unit_price_formatted: formatCurrency(item.unit_price / 100),
                    quantity: item.quantity,
                    total: item.price / 100, // convert centavos to pesos
                    total_formatted: formatCurrency(item.price / 100),
                    currency: item.currency,
                })),
                total: chargesTotal, // convert centavos to pesos
                total_formatted: breakdown.value.total_formatted,
                description: 'Processing fees and add-on charges',
            },
            summary: {
                face_value_total: faceValueTotal, // pesos
                face_value_total_formatted: formatCurrency(faceValueTotal),
                charges_total: chargesTotal, // pesos
                charges_total_formatted: formatCurrency(chargesTotal),
                grand_total: grandTotal, // pesos
                grand_total_formatted: formatCurrency(grandTotal),
                currency: instructions.value.cash?.currency || 'PHP',
                note: 'All amounts in major units (PHP pesos). Minor units (centavos) stored in database only.',
            },
        };
    });
    
    // Backward compatibility alias
    const costJson = computed(() => deductionJson.value);

    return {
        loading,
        error,
        breakdown,
        // New naming (deduction = more accurate)
        totalDeduction,
        deductionJson,
        // Backward compatibility aliases
        totalCost, // alias for totalDeduction
        costJson, // alias for deductionJson
        calculateCharges,
        refresh: calculateCharges,
    };
}
