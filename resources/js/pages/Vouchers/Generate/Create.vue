<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { VoucherInputFieldOption } from '@/types/voucher';
import { Head, router } from '@inertiajs/vue3';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { AlertCircle, Banknote, Code, FileText, Send, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useVoucherApi } from '@/composables/useVoucherApi';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import { useWalletBalance } from '@/composables/useWalletBalance';
import LocationValidationForm from '@/components/voucher/forms/LocationValidationForm.vue';
import TimeValidationForm from '@/components/voucher/forms/TimeValidationForm.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertTriangle, Info } from 'lucide-vue-next';
import { AMOUNT_LIMITS } from '@/config/bank-restrictions';
import axios from 'axios';

// Debug flag - set to false to suppress console logs
const DEBUG = false;

interface Props {
    input_field_options: VoucherInputFieldOption[];
    config: any;
}

const props = defineProps<Props>();

// Config loaded successfully

// Use reactive wallet balance with real-time updates
const { balance: walletBalance, formattedBalance, realtimeNote, realtimeTime } = useWalletBalance();

// Track balance updates for debugging
watch([walletBalance, realtimeNote], ([newBalance, newNote]) => {
    if (DEBUG) {
        console.log('[Generate Vouchers] Wallet balance updated:', {
            balance: newBalance,
            formatted: formattedBalance.value,
            note: newNote,
            timestamp: realtimeTime.value
        });
    }
});

const { loading, error, generateVouchers } = useVoucherApi();
const validationErrors = ref<Record<string, string>>({});

// Campaign selection
interface Campaign {
    id: number;
    name: string;
    slug: string;
    instructions: any;
}

const campaigns = ref<Campaign[]>([]);
const selectedCampaignId = ref<string>('');
const selectedCampaign = ref<Campaign | null>(null);

// Load campaigns on mount
axios.get('/api/v1/campaigns').then(response => {
    campaigns.value = response.data;
}).catch(err => console.error('Failed to load campaigns:', err));

// Watch campaign selection and populate form
watch(selectedCampaignId, async (campaignId) => {
    if (!campaignId) {
        selectedCampaign.value = null;
        return;
    }

    try {
        const response = await axios.get(`/api/v1/campaigns/${campaignId}`);
        const campaign = response.data;
        selectedCampaign.value = campaign;

        // Populate form from campaign instructions
        const inst = campaign.instructions;
        
        if (inst.cash) {
            amount.value = inst.cash.amount || props.config.basic_settings.amount.default;
        }
        
        if (inst.inputs?.fields) {
            selectedInputFields.value = [...inst.inputs.fields];
        }
        
        if (inst.cash?.validation) {
            validationSecret.value = inst.cash.validation.secret || '';
            validationMobile.value = inst.cash.validation.mobile || '';
        }
        
        if (inst.feedback) {
            feedbackEmail.value = inst.feedback.email || '';
            feedbackMobile.value = inst.feedback.mobile || '';
            feedbackWebhook.value = inst.feedback.webhook || '';
        }
        
        if (inst.rider) {
            riderMessage.value = inst.rider.message || '';
            riderUrl.value = inst.rider.url || props.config.rider.url.default;
        }
        
        // Populate settlement rail and fee strategy from campaign
        if (inst.cash) {
            settlementRail.value = inst.cash.settlement_rail || null;
            feeStrategy.value = inst.cash.fee_strategy || 'absorb';
        }
        
        // Populate validation fields from campaign
        if (inst.validation) {
            locationValidation.value = inst.validation.location || null;
            timeValidation.value = inst.validation.time || null;
        } else {
            locationValidation.value = null;
            timeValidation.value = null;
        }
        
        count.value = inst.count || props.config.basic_settings.quantity.default;
        prefix.value = inst.prefix || '';
        mask.value = inst.mask || '';
        
        // Parse TTL if present (format: P30D)
        if (inst.ttl) {
            const match = inst.ttl.match(/P(\d+)D/);
            ttlDays.value = match ? parseInt(match[1]) : props.config.basic_settings.ttl.default;
        }
    } catch (err) {
        console.error('Failed to load campaign:', err);
    }
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '#' },
    { title: 'Generate', href: '#' },
];

// Form state - initialized from config defaults
const amount = ref(props.config.basic_settings.amount.default);
const count = ref(props.config.basic_settings.quantity.default);
const prefix = ref('');
const mask = ref('');
const ttlDays = ref<number | null>(props.config.basic_settings.ttl.default);

const selectedInputFields = ref<string[]>([]);

const validationSecret = ref('');
const validationMobile = ref('');

const feedbackEmail = ref('');
const feedbackMobile = ref('');
const feedbackWebhook = ref('');

const riderMessage = ref('');
const riderUrl = ref(props.config.rider.url.default);

// Settlement rail and fee strategy
const settlementRail = ref<string | null>(null);
const feeStrategy = ref<string>('absorb');

// Rail fees for preview (must match config/omnipay.php)
const railFees = { INSTAPAY: 10, PESONET: 25 };

// Computed values for fee preview
const selectedRailValue = computed(() => {
    if (settlementRail.value) return settlementRail.value;
    return amount.value < 50000 ? 'INSTAPAY' : 'PESONET';
});

const estimatedFee = computed(() => {
    return railFees[selectedRailValue.value as keyof typeof railFees] || 0;
});

const adjustedAmount = computed(() => {
    if (feeStrategy.value === 'include') {
        return amount.value - estimatedFee.value;
    }
    return amount.value;
});

const totalCost = computed(() => {
    if (feeStrategy.value === 'add') {
        return amount.value + estimatedFee.value;
    }
    return amount.value;
});

// Rail validation for EMI warnings
const railValidation = computed(() => {
    const railValue = selectedRailValue.value;
    
    // Check if PESONET is selected
    if (railValue === 'PESONET') {
        // Check if amount exceeds INSTAPAY limit
        if (amount.value > AMOUNT_LIMITS.INSTAPAY.max) {
            return {
                type: 'warning',
                message: `Note: EMIs (GCash, PayMaya, etc.) do not support PESONET. Redeemers with EMI accounts cannot claim this voucher as the amount (₱${amount.value.toLocaleString()}) exceeds the INSTAPAY limit (₱${AMOUNT_LIMITS.INSTAPAY.max.toLocaleString()}).`
            };
        }
        return {
            type: 'info',
            message: 'Note: EMIs (GCash, PayMaya, etc.) do not support PESONET. Redeemers with EMI accounts will need to provide a traditional bank account.'
        };
    }
    
    return null;
});

// Validation fields - Initialize from config defaults
const locationValidation = ref<any>(
    props.config.location_validation.default_enabled ? {
        required: true,
        target_lat: null,
        target_lng: null,
        radius_meters: (props.config.location_validation.default_radius_km ?? 1) * 1000,
        on_failure: props.config.location_validation.default_on_failure ?? 'block',
    } : null
);

const timeValidation = ref<any>(
    props.config.time_validation.default_enabled ? {
        window: props.config.time_validation.default_window_enabled ? {
            start_time: props.config.time_validation.default_start_time ?? '09:00',
            end_time: props.config.time_validation.default_end_time ?? '17:00',
            timezone: props.config.time_validation.default_timezone ?? 'Asia/Manila',
        } : null,
        limit_minutes: props.config.time_validation.default_duration_enabled 
            ? (props.config.time_validation.default_limit_minutes ?? 10) 
            : null,
        track_duration: true,
    } : null
);

// Validation fields initialized from config

// Build instructions payload for pricing API
const instructionsForPricing = computed(() => {
    return {
        cash: {
            amount: amount.value,
            currency: 'PHP',
            settlement_rail: settlementRail.value || null,
            fee_strategy: feeStrategy.value || 'absorb',
        },
        inputs: {
            fields: selectedInputFields.value,
        },
        feedback: {
            email: feedbackEmail.value || null,
            mobile: feedbackMobile.value || null,
            webhook: feedbackWebhook.value || null,
        },
        rider: {
            message: riderMessage.value || null,
            url: riderUrl.value || null,
        },
        validation: {
            location: locationValidation.value || null,
            time: timeValidation.value || null,
        },
        count: count.value,
        prefix: prefix.value || null,
        mask: mask.value || null,
        ttl: ttlDays.value ? `P${ttlDays.value}D` : null,
    };
});

// Debug watchers (after all refs are defined)
if (DEBUG) {
    watch(locationValidation, (newVal) => {
        console.log('[Generate] locationValidation changed:', newVal);
    }, { deep: true });
    
    watch(timeValidation, (newVal) => {
        console.log('[Generate] timeValidation changed:', newVal);
    }, { deep: true });
    
    watch(instructionsForPricing, (newVal) => {
        console.log('[Generate] instructionsForPricing changed:', JSON.stringify(newVal, null, 2));
    }, { deep: true });
}

// Use live pricing API
const { breakdown: apiBreakdown, loading: pricingLoading, error: pricingError } = useChargeBreakdown(
    instructionsForPricing,
    { debounce: 500, autoCalculate: true }
);

// Cost breakdown computed from API response with fallback during loading
const costBreakdown = computed(() => {
    // If API has returned a breakdown, use it
    if (apiBreakdown.value) {
        return {
            breakdown: apiBreakdown.value.breakdown,
            total: apiBreakdown.value.total / 100, // Convert centavos to pesos
            total_formatted: apiBreakdown.value.total_formatted,
        };
    }
    
    // Fallback: simple calculation while loading or if API fails
    const baseCharge = amount.value * count.value;
    return {
        breakdown: [],
        total: baseCharge,
        total_formatted: `₱${baseCharge.toFixed(2)}`,
    };
});

const insufficientFunds = computed(
    () => walletBalance.value !== null && costBreakdown.value.total > walletBalance.value,
);

// Form submission data
const formData = computed(() => ({
    amount: amount.value,
    count: count.value,
    prefix: prefix.value || undefined,
    mask: mask.value || undefined,
    ttl_days: ttlDays.value,
    input_fields: selectedInputFields.value,
    validation_secret: validationSecret.value || undefined,
    validation_mobile: validationMobile.value || undefined,
    settlement_rail: settlementRail.value || undefined,
    fee_strategy: feeStrategy.value || undefined,
    feedback_email: feedbackEmail.value || undefined,
    feedback_mobile: feedbackMobile.value || undefined,
    feedback_webhook: feedbackWebhook.value || undefined,
    rider_message: riderMessage.value || undefined,
    rider_url: riderUrl.value || undefined,
}));

const toggleInputField = (fieldValue: string) => {
    const index = selectedInputFields.value.indexOf(fieldValue);
    if (index > -1) {
        selectedInputFields.value.splice(index, 1);
    } else {
        selectedInputFields.value.push(fieldValue);
    }
};

// Live JSON preview
const jsonPreview = computed(() => {
    const data = {
        cash: {
            amount: amount.value,
            currency: 'PHP',
            validation: {
                secret: validationSecret.value || null,
                mobile: validationMobile.value || null,
                country: 'PH',
                location: null,
                radius: null,
            },
            settlement_rail: settlementRail.value || null,
            fee_strategy: feeStrategy.value || 'absorb',
        },
        inputs: {
            fields: selectedInputFields.value,
        },
        feedback: {
            email: feedbackEmail.value || null,
            mobile: feedbackMobile.value || null,
            webhook: feedbackWebhook.value || null,
        },
        rider: {
            message: riderMessage.value || null,
            url: riderUrl.value || null,
        },
        validation: {
            location: locationValidation.value || null,
            time: timeValidation.value || null,
        },
        count: count.value,
        prefix: prefix.value || null,
        mask: mask.value || null,
        ttl: ttlDays.value ? `P${ttlDays.value}D` : null,
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

const showJsonPreview = ref(false);

// Form submission
const handleSubmit = async () => {
    if (insufficientFunds.value) return;

    validationErrors.value = {};

    const result = await generateVouchers({
        amount: amount.value,
        count: count.value,
        prefix: prefix.value || undefined,
        mask: mask.value || undefined,
        ttl_days: ttlDays.value || undefined,
        input_fields: selectedInputFields.value.length > 0 ? selectedInputFields.value : undefined,
        validation_secret: validationSecret.value || undefined,
        validation_mobile: validationMobile.value || undefined,
        settlement_rail: settlementRail.value || undefined,
        fee_strategy: feeStrategy.value || undefined,
        feedback_email: feedbackEmail.value || undefined,
        feedback_mobile: feedbackMobile.value || undefined,
        feedback_webhook: feedbackWebhook.value || undefined,
        rider_message: riderMessage.value || undefined,
        rider_url: riderUrl.value || undefined,
        campaign_id: selectedCampaignId.value || undefined,
        // Validation instructions
        validation_location: locationValidation.value || undefined,
        validation_time: timeValidation.value || undefined,
    });

    if (result) {
        // Success - redirect to success page
        router.visit(`/vouchers/generate/success/${result.count}`);
    } else if (error.value) {
        // Handle validation errors
        // The error.value contains the error message
        console.error('Generation failed:', error.value);
    }
};
</script>

<template>
    <Head :title="config.page.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                :title="config.page.title"
                :description="config.page.description"
            />

            <form
                @submit.prevent="handleSubmit"
                class="grid gap-6 lg:grid-cols-3"
            >
                <!-- Main Form -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Basic Settings -->
                    <Card v-if="config.basic_settings.show_card">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <Settings class="h-5 w-5" />
                                <CardTitle>Basic Settings</CardTitle>
                            </div>
                            <CardDescription>
                                Configure voucher amount, quantity, and expiry
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Campaign Template Selector -->
                            <div v-if="config.basic_settings.show_campaign_selector" class="space-y-2 pb-4 border-b">
                                <Label for="campaign">{{ config.basic_settings.campaign_selector.label }}</Label>
                                <select
                                    id="campaign"
                                    v-model="selectedCampaignId"
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">None (manual entry)</option>
                                    <option
                                        v-for="campaign in campaigns"
                                        :key="campaign.id"
                                        :value="campaign.id.toString()"
                                    >
                                        {{ campaign.name }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    {{ config.basic_settings.campaign_selector.help_text }}
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-if="config.basic_settings.show_amount" class="space-y-2">
                                    <Label for="amount">{{ config.basic_settings.amount.label }}</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        name="amount"
                                        v-model.number="amount"
                                        :min="config.basic_settings.amount.min"
                                        :step="config.basic_settings.amount.step"
                                        required
                                    />
                                    <InputError :message="validationErrors.amount" />
                                </div>

                                <div v-if="config.basic_settings.show_quantity" class="space-y-2">
                                    <Label for="count">{{ config.basic_settings.quantity.label }}</Label>
                                    <Input
                                        id="count"
                                        type="number"
                                        name="count"
                                        v-model.number="count"
                                        :min="config.basic_settings.quantity.min"
                                        required
                                    />
                                    <InputError :message="validationErrors.count" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-if="config.basic_settings.show_prefix" class="space-y-2">
                                    <Label for="prefix">{{ config.basic_settings.prefix.label }}</Label>
                                    <Input
                                        id="prefix"
                                        name="prefix"
                                        v-model="prefix"
                                        :placeholder="config.basic_settings.prefix.placeholder"
                                    />
                                    <InputError :message="validationErrors.prefix" />
                                </div>

                                <div v-if="config.basic_settings.show_mask" class="space-y-2">
                                    <Label for="mask">{{ config.basic_settings.mask.label }}</Label>
                                    <Input
                                        id="mask"
                                        name="mask"
                                        v-model="mask"
                                        :placeholder="config.basic_settings.mask.placeholder"
                                    />
                                    <InputError :message="validationErrors.mask" />
                                    <p class="text-xs text-muted-foreground">
                                        {{ config.basic_settings.mask.help_text }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="config.basic_settings.show_ttl" class="space-y-2">
                                <Label for="ttl_days">{{ config.basic_settings.ttl.label }}</Label>
                                <Input
                                    id="ttl_days"
                                    type="number"
                                    name="ttl_days"
                                    v-model.number="ttlDays"
                                    :min="config.basic_settings.ttl.min"
                                    :placeholder="config.basic_settings.ttl.placeholder"
                                />
                                <InputError :message="validationErrors.ttl_days" />
                                <p class="text-xs text-muted-foreground">
                                    {{ config.basic_settings.ttl.help_text }}
                                </p>
                            </div>

                            <!-- Settlement Rail & Fee Strategy -->
                            <div class="pt-4 border-t space-y-4">
                                <h4 class="text-sm font-medium">Disbursement Settings</h4>
                                
                                <!-- Settlement Rail -->
                                <div class="space-y-2">
                                    <Label for="settlement_rail">Settlement Rail</Label>
                                    <Select v-model="settlementRail">
                                        <SelectTrigger id="settlement_rail">
                                            <SelectValue placeholder="Auto (based on amount)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">Auto (based on amount)</SelectItem>
                                            <SelectItem value="INSTAPAY">INSTAPAY (Real-time, ₱50k max, ₱10 fee)</SelectItem>
                                            <SelectItem value="PESONET">PESONET (Next day, ₱1M max, ₱25 fee)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-muted-foreground">
                                        Auto mode selects INSTAPAY for amounts &lt; ₱50k, PESONET otherwise
                                    </p>
                                    
                                    <!-- Rail validation warning with smooth transition -->
                                    <transition
                                        enter-active-class="transition-all duration-200 ease-out"
                                        leave-active-class="transition-all duration-150 ease-in"
                                        enter-from-class="opacity-0 -translate-y-2"
                                        enter-to-class="opacity-100 translate-y-0"
                                        leave-from-class="opacity-100 translate-y-0"
                                        leave-to-class="opacity-0 -translate-y-2"
                                    >
                                        <Alert v-if="railValidation" :variant="railValidation.type === 'warning' ? 'destructive' : 'default'" class="mt-2">
                                            <AlertTriangle v-if="railValidation.type === 'warning'" class="h-4 w-4" />
                                            <Info v-else class="h-4 w-4" />
                                            <AlertDescription class="text-sm">
                                                {{ railValidation.message }}
                                            </AlertDescription>
                                        </Alert>
                                    </transition>
                                </div>

                                <!-- Fee Strategy -->
                                <div class="space-y-2">
                                    <Label>Fee Strategy</Label>
                                    <RadioGroup v-model="feeStrategy" class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="absorb" id="absorb" />
                                            <Label for="absorb" class="font-normal cursor-pointer">
                                                <div>
                                                    <div class="font-medium">Absorb (Default)</div>
                                                    <div class="text-xs text-muted-foreground">You pay the fee, redeemer gets full amount</div>
                                                </div>
                                            </Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="include" id="include" />
                                            <Label for="include" class="font-normal cursor-pointer">
                                                <div>
                                                    <div class="font-medium">Include</div>
                                                    <div class="text-xs text-muted-foreground">Fee deducted from voucher (redeemer gets ₱{{ adjustedAmount }})</div>
                                                </div>
                                            </Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="add" id="add" />
                                            <Label for="add" class="font-normal cursor-pointer">
                                                <div>
                                                    <div class="font-medium">Add to Amount</div>
                                                    <div class="text-xs text-muted-foreground">Redeemer receives ₱{{ totalCost }} (voucher + fee)</div>
                                                </div>
                                            </Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                <!-- Fee Preview -->
                                <div class="p-3 bg-muted rounded-md space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Selected Rail:</span>
                                        <span class="font-medium">{{ selectedRailValue }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Estimated Fee:</span>
                                        <span class="font-medium">₱{{ estimatedFee }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Input Fields -->
                    <Card v-if="config.input_fields.show_card">
                        <CardHeader v-if="config.input_fields.show_header">
                            <div class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                <CardTitle v-if="config.input_fields.show_title">{{ config.input_fields.title }}</CardTitle>
                            </div>
                            <CardDescription v-if="config.input_fields.show_description">
                                {{ config.input_fields.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="option in input_field_options"
                                    :key="option.value"
                                    class="flex items-center space-x-2 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :id="option.value"
                                        :value="option.value"
                                        v-model="selectedInputFields"
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm">
                                        {{ option.label }}
                                    </span>
                                </label>
                            </div>
                            <input
                                v-for="(field, index) in selectedInputFields"
                                :key="index"
                                type="hidden"
                                :name="`input_fields[${index}]`"
                                :value="field"
                            />
                            <InputError
                                :message="validationErrors.input_fields"
                                class="mt-2"
                            />
                        </CardContent>
                    </Card>

                    <!-- Validation Rules -->
                    <Card v-if="config.validation_rules.show_card">
                        <CardHeader v-if="config.validation_rules.show_header">
                            <div class="flex items-center gap-2">
                                <AlertCircle class="h-5 w-5" />
                                <CardTitle v-if="config.validation_rules.show_title">{{ config.validation_rules.title }}</CardTitle>
                            </div>
                            <CardDescription v-if="config.validation_rules.show_description">
                                {{ config.validation_rules.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="config.validation_rules.show_secret" class="space-y-2">
                                <Label for="validation_secret">{{ config.validation_rules.secret.label }}</Label>
                                <Input
                                    id="validation_secret"
                                    name="validation_secret"
                                    v-model="validationSecret"
                                    :placeholder="config.validation_rules.secret.placeholder"
                                />
                                <InputError :message="validationErrors.validation_secret" />
                            </div>

                            <div v-if="config.validation_rules.show_mobile" class="space-y-2">
                                <Label for="validation_mobile"
                                    >{{ config.validation_rules.mobile.label }}</Label
                                >
                                <Input
                                    id="validation_mobile"
                                    name="validation_mobile"
                                    v-model="validationMobile"
                                    :placeholder="config.validation_rules.mobile.placeholder"
                                />
                                <InputError :message="validationErrors.validation_mobile" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Feedback Channels -->
                    <Card v-if="config.feedback_channels.show_card">
                        <CardHeader v-if="config.feedback_channels.show_header">
                            <div class="flex items-center gap-2">
                                <Send class="h-5 w-5" />
                                <CardTitle v-if="config.feedback_channels.show_title">{{ config.feedback_channels.title }}</CardTitle>
                            </div>
                            <CardDescription v-if="config.feedback_channels.show_description">
                                {{ config.feedback_channels.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="config.feedback_channels.show_email" class="space-y-2">
                                <Label for="feedback_email">{{ config.feedback_channels.email.label }}</Label>
                                <Input
                                    id="feedback_email"
                                    name="feedback_email"
                                    type="email"
                                    v-model="feedbackEmail"
                                    :placeholder="config.feedback_channels.email.placeholder"
                                />
                                <InputError :message="validationErrors.feedback_email" />
                            </div>

                            <div v-if="config.feedback_channels.show_mobile" class="space-y-2">
                                <Label for="feedback_mobile">{{ config.feedback_channels.mobile.label }}</Label>
                                <Input
                                    id="feedback_mobile"
                                    name="feedback_mobile"
                                    v-model="feedbackMobile"
                                    :placeholder="config.feedback_channels.mobile.placeholder"
                                />
                                <InputError :message="validationErrors.feedback_mobile" />
                            </div>

                            <div v-if="config.feedback_channels.show_webhook" class="space-y-2">
                                <Label for="feedback_webhook">{{ config.feedback_channels.webhook.label }}</Label>
                                <Input
                                    id="feedback_webhook"
                                    name="feedback_webhook"
                                    type="url"
                                    v-model="feedbackWebhook"
                                    :placeholder="config.feedback_channels.webhook.placeholder"
                                />
                                <InputError :message="validationErrors.feedback_webhook" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Rider -->
                    <Card v-if="config.rider.show_card">
                        <CardHeader v-if="config.rider.show_header">
                            <div class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                <CardTitle v-if="config.rider.show_title">{{ config.rider.title }}</CardTitle>
                            </div>
                            <CardDescription v-if="config.rider.show_description">
                                {{ config.rider.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="config.rider.show_message" class="space-y-2">
                                <Label for="rider_message">{{ config.rider.message.label }}</Label>
                                <Input
                                    id="rider_message"
                                    name="rider_message"
                                    v-model="riderMessage"
                                    :placeholder="config.rider.message.placeholder"
                                />
                                <InputError :message="validationErrors.rider_message" />
                            </div>

                            <div v-if="config.rider.show_url" class="space-y-2">
                                <Label for="rider_url">{{ config.rider.url.label }}</Label>
                                <Input
                                    id="rider_url"
                                    name="rider_url"
                                    type="url"
                                    v-model="riderUrl"
                                    :placeholder="config.rider.url.placeholder"
                                />
                                <InputError :message="validationErrors.rider_url" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Location Validation -->
                    <LocationValidationForm
                        v-if="config.location_validation.show_card"
                        v-model="locationValidation"
                        :validation-errors="validationErrors"
                        :config="config.location_validation"
                    />

                    <!-- Time Validation -->
                    <TimeValidationForm
                        v-if="config.time_validation.show_card"
                        v-model="timeValidation"
                        :validation-errors="validationErrors"
                        :config="config.time_validation"
                    />

                    <!-- JSON Preview -->
                    <Collapsible v-if="config.json_preview.show_card" v-model:open="showJsonPreview">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Code class="h-5 w-5" />
                                            <CardTitle v-if="config.json_preview.show_title">{{ config.json_preview.title }}</CardTitle>
                                        </div>
                                        <span class="text-sm text-muted-foreground">
                                            {{ showJsonPreview ? '▼' : '▶' }}
                                        </span>
                                    </div>
                                    <CardDescription v-if="config.json_preview.show_description">
                                        {{ config.json_preview.description }}
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

                <!-- Cost Preview Sidebar -->
                <div v-if="config.cost_breakdown.show_sidebar" class="lg:col-span-1">
                    <Card class="sticky top-6">
                        <CardHeader v-if="config.cost_breakdown.show_header">
                            <div class="flex items-center gap-2">
                                <Banknote class="h-5 w-5" />
                                <CardTitle v-if="config.cost_breakdown.show_title">{{ config.cost_breakdown.title }}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Loading state -->
                            <div v-if="pricingLoading" class="text-sm text-muted-foreground text-center py-4">
                                {{ config.cost_breakdown.calculating_message }}
                            </div>
                            
                            <!-- Error state -->
                            <div v-else-if="pricingError" class="text-sm text-destructive text-center py-4">
                                {{ config.cost_breakdown.error_message }}
                            </div>
                            
                            <!-- Breakdown from API -->
                            <div v-else class="space-y-2 text-sm">
                                <div
                                    v-for="item in costBreakdown.breakdown"
                                    :key="item.index"
                                    class="flex justify-between"
                                >
                                    <span class="text-muted-foreground">{{ item.label }}</span>
                                    <span class="font-medium">{{ item.price_formatted }}</span>
                                </div>
                                
                                <!-- Fallback message if no breakdown items -->
                                <div v-if="costBreakdown.breakdown.length === 0" class="flex justify-between">
                                    <span class="text-muted-foreground">Base Charge</span>
                                    <span class="font-medium">₱{{ costBreakdown.total.toLocaleString() }}</span>
                                </div>
                            </div>

                            <Separator />

                            <div class="flex justify-between text-base font-semibold">
                                <span>Total Cost</span>
                                <span>{{ costBreakdown.total_formatted || `₱${costBreakdown.total.toLocaleString()}` }}</span>
                            </div>

                            <Separator />

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >{{ config.cost_breakdown.wallet_balance_label }}</span
                                    >
                                    <span
                                        :class="
                                            insufficientFunds
                                                ? 'text-destructive'
                                                : 'text-green-600 dark:text-green-400'
                                        "
                                        >{{ formattedBalance }}</span
                                    >
                                </div>
                                <div v-if="realtimeNote" class="text-xs text-muted-foreground italic">
                                    {{ realtimeNote }}
                                </div>
                                <div class="flex justify-between font-medium">
                                    <span>{{ config.cost_breakdown.after_generation_label }}</span>
                                    <span
                                        :class="
                                            insufficientFunds
                                                ? 'text-destructive'
                                                : ''
                                        "
                                        >₱{{
                                            walletBalance !== null
                                                ? (walletBalance - costBreakdown.total).toLocaleString()
                                                : '0.00'
                                        }}</span
                                    >
                                </div>
                            </div>

                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="loading || insufficientFunds"
                            >
                                {{
                                    insufficientFunds
                                        ? config.cost_breakdown.submit_button.insufficient_funds_text
                                        : loading
                                          ? config.cost_breakdown.submit_button.processing_text
                                          : config.cost_breakdown.submit_button.text
                                }}
                            </Button>

                            <p
                                v-if="insufficientFunds"
                                class="text-center text-xs text-destructive"
                            >
                                {{ config.cost_breakdown.insufficient_funds_message }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
