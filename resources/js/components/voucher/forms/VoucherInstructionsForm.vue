<script setup lang="ts">
/**
 * VoucherInstructionsForm - Composite form component for voucher instructions
 * 
 * Maps to VoucherInstructionsData.php DTO
 * 
 * This component combines all atomic form components (CashInstruction, InputFields,
 * Feedback, Rider) into a single comprehensive voucher instructions form.
 * 
 * @component
 * @example
 * <VoucherInstructionsForm
 *   v-model="formData"
 *   :input-field-options="options"
 *   :validation-errors="errors"
 *   :readonly="true"
 *   :show-count-field="false"
 *   :show-json-preview="true"
 * />
 */
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Code, Settings, DollarSign } from 'lucide-vue-next';
import CashInstructionForm from './CashInstructionForm.vue';
import InputFieldsForm from './InputFieldsForm.vue';
import FeedbackInstructionForm from './FeedbackInstructionForm.vue';
import RiderInstructionForm from './RiderInstructionForm.vue';
import LocationValidationForm from './LocationValidationForm.vue';
import TimeValidationForm from './TimeValidationForm.vue';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import type { VoucherInputFieldOption, CashInstruction, InputFields, FeedbackInstruction, RiderInstruction } from '@/types/voucher';

// Debug flag - set to false to suppress console logs
const DEBUG = false;

interface LocationValidation {
    required: boolean;
    target_lat: number | null;
    target_lng: number | null;
    radius_meters: number | null;
    on_failure: 'block' | 'warn';
}

interface TimeWindow {
    start_time: string;
    end_time: string;
    timezone: string;
}

interface TimeValidation {
    window: TimeWindow | null;
    limit_minutes: number | null;
    track_duration: boolean;
}

interface Props {
    modelValue: {
        amount: number;
        count: number;
        prefix: string;
        mask: string;
        ttlDays: number | null;
        selectedInputFields: string[];
        validationSecret: string;
        validationMobile: string;
        feedbackEmail: string;
        feedbackMobile: string;
        feedbackWebhook: string;
        riderMessage: string;
        riderUrl: string;
        riderRedirectTimeout: number | null;
        riderSplash: string;
        riderSplashTimeout: number | null;
        locationValidation: LocationValidation | null;
        timeValidation: TimeValidation | null;
        settlementRail: string | null;
        feeStrategy: string;
    };
    inputFieldOptions: VoucherInputFieldOption[];
    validationErrors?: Record<string, string>;
    showCountField?: boolean;
    showJsonPreview?: boolean;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    validationErrors: () => ({}),
    showCountField: true,
    showJsonPreview: true,
    readonly: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: Props['modelValue']];
}>();

const localValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const showJsonPreviewCollapsible = ref(false);
const showPricingBreakdown = ref(true);

// Transform flat structure to nested structure for atomic components
const cashInstruction = computed<CashInstruction>({
    get: () => ({
        amount: localValue.value.amount,
        currency: 'PHP',
        validation: {
            secret: localValue.value.validationSecret || null,
            mobile: localValue.value.validationMobile || null,
            country: 'PH',
            location: null,
            radius: null,
        },
        settlement_rail: localValue.value.settlementRail || null,
        fee_strategy: localValue.value.feeStrategy || 'absorb',
    }),
    set: (value) => {
        localValue.value = {
            ...localValue.value,
            amount: value.amount,
            validationSecret: value.validation.secret || '',
            validationMobile: value.validation.mobile || '',
            settlementRail: value.settlement_rail || null,
            feeStrategy: value.fee_strategy || 'absorb',
        };
    },
});

const inputFields = computed<InputFields>({
    get: () => ({
        fields: localValue.value.selectedInputFields as any[],
    }),
    set: (value) => {
        if (DEBUG) console.log('[VoucherInstructionsForm] inputFields setter called with:', value);
        localValue.value = {
            ...localValue.value,
            selectedInputFields: value.fields,
        };
        if (DEBUG) console.log('[VoucherInstructionsForm] localValue after inputFields update:', localValue.value);
    },
});

const feedbackInstruction = computed<FeedbackInstruction>({
    get: () => ({
        email: localValue.value.feedbackEmail || null,
        mobile: localValue.value.feedbackMobile || null,
        webhook: localValue.value.feedbackWebhook || null,
    }),
    set: (value) => {
        localValue.value = {
            ...localValue.value,
            feedbackEmail: value.email || '',
            feedbackMobile: value.mobile || '',
            feedbackWebhook: value.webhook || '',
        };
    },
});

const riderInstruction = computed<RiderInstruction>({
    get: () => ({
        message: localValue.value.riderMessage || null,
        url: localValue.value.riderUrl || null,
        redirect_timeout: localValue.value.riderRedirectTimeout ?? null,
        splash: localValue.value.riderSplash || null,
        splash_timeout: localValue.value.riderSplashTimeout ?? null,
    }),
    set: (value) => {
        localValue.value = {
            ...localValue.value,
            riderMessage: value.message || '',
            riderUrl: value.url || '',
            riderRedirectTimeout: value.redirect_timeout ?? null,
            riderSplash: value.splash || '',
            riderSplashTimeout: value.splash_timeout ?? null,
        };
    },
});

const locationValidation = computed<LocationValidation | null>({
    get: () => localValue.value.locationValidation,
    set: (value) => {
        localValue.value = {
            ...localValue.value,
            locationValidation: value,
        };
    },
});

const timeValidation = computed<TimeValidation | null>({
    get: () => localValue.value.timeValidation,
    set: (value) => {
        localValue.value = {
            ...localValue.value,
            timeValidation: value,
        };
    },
});

// Live JSON preview
const jsonPreview = computed(() => {
    const data = {
        cash: {
            amount: localValue.value.amount,
            currency: 'PHP',
            validation: {
                secret: localValue.value.validationSecret || null,
                mobile: localValue.value.validationMobile || null,
                country: 'PH',
                location: null,
                radius: null,
            },
            settlement_rail: localValue.value.settlementRail || null,
            fee_strategy: localValue.value.feeStrategy || 'absorb',
        },
        inputs: {
            fields: localValue.value.selectedInputFields,
        },
        feedback: {
            email: localValue.value.feedbackEmail || null,
            mobile: localValue.value.feedbackMobile || null,
            webhook: localValue.value.feedbackWebhook || null,
        },
        rider: {
            message: localValue.value.riderMessage || null,
            url: localValue.value.riderUrl || null,
            redirect_timeout: localValue.value.riderRedirectTimeout ?? null,
        },
        validation: {
            location: localValue.value.locationValidation || null,
            time: localValue.value.timeValidation || null,
        },
        count: localValue.value.count,
        prefix: localValue.value.prefix || null,
        mask: localValue.value.mask || null,
        ttl: localValue.value.ttlDays ? `P${localValue.value.ttlDays}D` : null,
    };
    
    // Recursively remove null values
    const removeNulls = (obj: any): any => {
        if (Array.isArray(obj)) {
            return obj.map(removeNulls).filter(v => v !== null);
        }
        if (obj !== null && typeof obj === 'object') {
            return Object.entries(obj)
                .filter(([_, v]) => v !== null)
                .reduce((acc, [k, v]) => {
                    const cleaned = removeNulls(v);
                    // Only add if it's not an empty object or array
                    if (cleaned !== null && 
                        !(typeof cleaned === 'object' && Object.keys(cleaned).length === 0) &&
                        !(Array.isArray(cleaned) && cleaned.length === 0)) {
                        acc[k] = cleaned;
                    }
                    return acc;
                }, {} as any);
        }
        return obj;
    };
    
    return removeNulls(data);
});

// Pricing calculation (only if not readonly)
const instructionsForPricing = computed(() => {
    if (DEBUG) console.log('[VoucherInstructionsForm] instructionsForPricing computed called');
    // Explicitly access each property to ensure reactivity tracking
    const amount = localValue.value.amount;
    const selectedInputFields = localValue.value.selectedInputFields;
    const feedbackEmail = localValue.value.feedbackEmail;
    const feedbackMobile = localValue.value.feedbackMobile;
    const feedbackWebhook = localValue.value.feedbackWebhook;
    const riderMessage = localValue.value.riderMessage;
    const riderUrl = localValue.value.riderUrl;
    const locationValidationValue = localValue.value.locationValidation;
    const timeValidationValue = localValue.value.timeValidation;
    const count = localValue.value.count;
    const prefix = localValue.value.prefix;
    const mask = localValue.value.mask;
    const ttlDays = localValue.value.ttlDays;
    
    if (DEBUG) console.log('[VoucherInstructionsForm] Values:', { amount, selectedInputFields, feedbackEmail, feedbackMobile, feedbackWebhook, riderMessage, riderUrl, locationValidationValue, timeValidationValue });
    
    const result = {
        cash: {
            amount,
            currency: 'PHP',
        },
        inputs: {
            fields: selectedInputFields,
        },
        feedback: {
            email: feedbackEmail || null,
            mobile: feedbackMobile || null,
            webhook: feedbackWebhook || null,
        },
        rider: {
            message: riderMessage || null,
            url: riderUrl || null,
            redirect_timeout: localValue.value.riderRedirectTimeout ?? null,
        },
        validation: {
            location: locationValidationValue || null,
            time: timeValidationValue || null,
        },
        count,
        prefix: prefix || null,
        mask: mask || null,
        ttl: ttlDays ? `P${ttlDays}D` : null,
    };
    
    if (DEBUG) console.log('[VoucherInstructionsForm] Returning:', result);
    return result;
});

// Debug: watch localValue changes
watch(localValue, (newVal) => {
    if (DEBUG) console.log('[VoucherInstructionsForm] localValue changed:', newVal);
}, { deep: true });

const { breakdown, loading: pricingLoading, error: pricingError } = props.readonly 
    ? { breakdown: ref(null), loading: ref(false), error: ref(null) }
    : useChargeBreakdown(instructionsForPricing, { debounce: 500, autoCalculate: true });
</script>

<template>
    <div class="space-y-6">
        <!-- Basic Settings -->
        <Card>
            <CardHeader>
                <div class="flex items-center gap-2">
                    <Settings class="h-5 w-5" />
                    <CardTitle>Basic Settings</CardTitle>
                </div>
                <CardDescription>
                    Configure voucher quantity, code format, and expiry
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <slot name="before-basic-fields" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-if="showCountField" class="space-y-2">
                        <Label for="count">Quantity</Label>
                        <Input
                            id="count"
                            type="number"
                            v-model.number="localValue.count"
                            :min="1"
                            required
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.count" />
                    </div>

                    <div class="space-y-2">
                        <Label for="ttl_days">Expiry (Days)</Label>
                        <Input
                            id="ttl_days"
                            type="number"
                            v-model.number="localValue.ttlDays"
                            :min="1"
                            placeholder="30"
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.ttl_days" />
                        <p class="text-xs text-muted-foreground">
                            Leave empty for non-expiring vouchers
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="prefix">Code Prefix (Optional)</Label>
                        <Input
                            id="prefix"
                            v-model="localValue.prefix"
                            placeholder="e.g., PROMO"
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.prefix" />
                    </div>

                    <div class="space-y-2">
                        <Label for="mask">Code Mask (Optional)</Label>
                        <Input
                            id="mask"
                            v-model="localValue.mask"
                            placeholder="e.g., ****-****"
                            :readonly="readonly"
                        />
                        <InputError :message="validationErrors.mask" />
                        <p class="text-xs text-muted-foreground">
                            Use * for random chars, - for separators (4-6 asterisks)
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Cash Instruction (includes amount, currency, validation rules) -->
        <CashInstructionForm
            v-model="cashInstruction"
            :validation-errors="validationErrors"
            :readonly="readonly"
        />

        <!-- Input Fields -->
        <InputFieldsForm
            v-model="inputFields"
            :input-field-options="inputFieldOptions"
            :validation-errors="validationErrors"
            :readonly="readonly"
        />

        <!-- Feedback Channels -->
        <FeedbackInstructionForm
            v-model="feedbackInstruction"
            :validation-errors="validationErrors"
            :readonly="readonly"
        />

        <!-- Rider Information -->
        <RiderInstructionForm
            v-model="riderInstruction"
            :validation-errors="validationErrors"
            :readonly="readonly"
        />

        <!-- Location Validation -->
        <LocationValidationForm
            v-model="locationValidation"
            :validation-errors="validationErrors"
            :readonly="readonly"
        />

        <!-- Time Validation -->
        <TimeValidationForm
            v-model="timeValidation"
            :validation-errors="validationErrors"
            :readonly="readonly"
        />

        <!-- Pricing Breakdown -->
        <Collapsible v-if="!readonly" v-model:open="showPricingBreakdown">
            <Card>
                <CollapsibleTrigger class="w-full">
                    <CardHeader class="cursor-pointer hover:bg-muted/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <DollarSign class="h-5 w-5" />
                                <CardTitle>Pricing Breakdown</CardTitle>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="breakdown" class="text-sm font-semibold text-primary">
                                    {{ breakdown.total_formatted }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ showPricingBreakdown ? '▼' : '▶' }}
                                </span>
                            </div>
                        </div>
                        <CardDescription>
                            Real-time cost calculation
                        </CardDescription>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent>
                        <div v-if="pricingLoading" class="text-sm text-muted-foreground">
                            Calculating charges...
                        </div>
                        <div v-else-if="pricingError" class="text-sm text-destructive">
                            Error calculating charges. Please try again.
                        </div>
                        <div v-else-if="breakdown" class="space-y-3">
                            <div class="space-y-2">
                                <div 
                                    v-for="item in breakdown.breakdown" 
                                    :key="item.index"
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="text-muted-foreground">{{ item.label }}</span>
                                    <span class="font-medium">{{ item.price_formatted }}</span>
                                </div>
                            </div>
                            <div class="border-t pt-2">
                                <div class="flex items-center justify-between text-base font-semibold">
                                    <span>Total Cost</span>
                                    <span class="text-primary">{{ breakdown.total_formatted }}</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Per voucher: {{ (breakdown.total / (localValue.count || 1) / 100).toFixed(2) }} PHP
                                </p>
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            Fill in the form to see pricing
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>

        <!-- JSON Preview -->
        <Collapsible v-if="showJsonPreview" v-model:open="showJsonPreviewCollapsible">
            <Card>
                <CollapsibleTrigger class="w-full">
                    <CardHeader class="cursor-pointer hover:bg-muted/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Code class="h-5 w-5" />
                                <CardTitle>Live JSON Preview</CardTitle>
                            </div>
                            <span class="text-sm text-muted-foreground">
                                {{ showJsonPreviewCollapsible ? '▼' : '▶' }}
                            </span>
                        </div>
                        <CardDescription>
                            Real-time voucher instructions JSON
                        </CardDescription>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent>
                        <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(jsonPreview, null, 2) }}</code></pre>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    </div>
</template>
