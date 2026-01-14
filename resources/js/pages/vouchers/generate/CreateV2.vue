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
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { AlertCircle, Banknote, ChevronDown, Code, Eye, FileText, Info, Receipt, Send, Settings } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useVoucherApi } from '@/composables/useVoucherApi';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import { useWalletBalance } from '@/composables/useWalletBalance';
import LocationValidationForm from '@/components/voucher/forms/LocationValidationForm.vue';
import TimeValidationForm from '@/components/voucher/forms/TimeValidationForm.vue';
import { useGenerateMode } from '@/composables/useGenerateMode';
import { Switch } from '@/components/ui/switch';
import axios from 'axios';
import { list as vendorAliasesList } from '@/actions/App/Http/Controllers/Settings/VendorAliasController';

// Debug flag - set to false to suppress console logs
const DEBUG = false;

interface Props {
    input_field_options: VoucherInputFieldOption[];
    config: any;
    saved_mode?: string;
    settlement_enabled?: boolean;
}

const props = defineProps<Props>();

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

// Check if user has advanced pricing mode feature flag
const page = usePage();
const hasAdvancedMode = computed(() => {
    return page.props.auth?.feature_flags?.advanced_pricing_mode || false;
});

// Mode management (Simple vs Advanced)
const initialMode = (props.saved_mode ?? 'simple') as GenerateMode;
const { mode, switchMode } = useGenerateMode(initialMode);
const isSimpleMode = computed(() => mode.value === 'simple');

// Explicit computed for Switch component binding
const isSwitchChecked = computed(() => mode.value === 'advanced');

// Collapsible card state management (for Advanced Mode)
const collapsibleCards = ref({
    input_fields: false, // collapsed by default
    validation_rules: false,
    location_validation: false,
    time_validation: false,
    feedback_channels: false,
    rider: false,
    preview_controls: false,
    json_preview: false,
    deduction_json_preview: false,
});

const expandAll = () => {
    Object.keys(collapsibleCards.value).forEach(key => {
        collapsibleCards.value[key as keyof typeof collapsibleCards.value] = true;
    });
};

const collapseAll = () => {
    Object.keys(collapsibleCards.value).forEach(key => {
        collapsibleCards.value[key as keyof typeof collapsibleCards.value] = false;
    });
};

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

// Vendor aliases for payable field
interface VendorAlias {
    id: number;
    alias: string;
    status: string;
}

const vendorAliases = ref<VendorAlias[]>([]);

// Load campaigns and vendor aliases on mount
axios.get('/api/v1/campaigns').then(response => {
    campaigns.value = response.data;
}).catch(err => console.error('Failed to load campaigns:', err));

// Load vendor aliases
axios.get('/settings/vendor-aliases/list').then(response => {
    vendorAliases.value = response.data.aliases || [];
}).catch(err => console.error('Failed to load vendor aliases:', err));

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
            // Load payee from either mobile or payable field
            if (inst.cash.validation.mobile) {
                payee.value = inst.cash.validation.mobile;
            } else if (inst.cash.validation.payable) {
                payee.value = inst.cash.validation.payable;
            }
        }
        
        if (inst.feedback) {
            feedbackEmail.value = inst.feedback.email || '';
            feedbackMobile.value = inst.feedback.mobile || '';
            feedbackWebhook.value = inst.feedback.webhook || '';
        }
        
        if (inst.rider) {
            riderMessage.value = inst.rider.message || '';
            riderUrl.value = inst.rider.url || props.config.rider.url.default;
            riderRedirectTimeout.value = inst.rider.redirect_timeout ?? null;
            riderSplash.value = inst.rider.splash || '';
            riderSplashTimeout.value = inst.rider.splash_timeout ?? null;
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
        
        // Populate preview controls from campaign metadata
        if (inst.metadata) {
            previewEnabled.value = inst.metadata.preview_enabled ?? true;
            previewScope.value = inst.metadata.preview_scope ?? 'full';
            previewMessage.value = inst.metadata.preview_message ?? '';
        } else {
            previewEnabled.value = true;
            previewScope.value = 'full';
            previewMessage.value = '';
        }
        
        count.value = inst.count || props.config.basic_settings.quantity.default;
        prefix.value = inst.prefix || '';
        mask.value = inst.mask || '';
        
        // Parse TTL if present (format: P30D)
        if (inst.ttl) {
            const match = inst.ttl.match(/P(\d+)D/);
            ttlDays.value = match ? parseInt(match[1]) : props.config.basic_settings.ttl.default;
        }
        
        // Populate settlement voucher fields from campaign (if exists)
        if (inst.voucher_type) {
            voucherType.value = inst.voucher_type;
            targetAmount.value = inst.target_amount || null;
        }
        
        // Populate external metadata from campaign metadata (if exists)
        if (inst.metadata?.external_metadata) {
            externalMetadataJson.value = JSON.stringify(inst.metadata.external_metadata, null, 2);
        } else {
            externalMetadataJson.value = '';
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
const autoAddedFields = ref<Set<string>>(new Set());

const validationSecret = ref('');
const payee = ref<string>(''); // Bank check metaphor: blank/CASH, mobile, or vendor alias

const feedbackEmail = ref('');
const feedbackMobile = ref('');
const feedbackWebhook = ref('');

const riderMessage = ref('');
const riderUrl = ref(props.config.rider.url.default);
const riderRedirectTimeout = ref<number | null>(null);
const riderSplash = ref('');
const riderSplashTimeout = ref<number | null>(null);

// Preview controls
const previewEnabled = ref<boolean>(true);
const previewScope = ref<string>('full');
const previewMessage = ref<string>('');

// Settlement rail and fee strategy
const settlementRail = ref<string | null>(null);
const feeStrategy = ref<string>('absorb');

// Settlement voucher fields (only when feature enabled)
const voucherType = ref<string>('redeemable'); // redeemable, payable, settlement
const targetAmount = ref<number | null>(null);
const settlementRules = ref<any>(null); // Optional JSON rules
const externalMetadataJson = ref<string>(''); // Freeform JSON for payable vouchers

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

// Payee field smart detection (bank check metaphor)
const payeeType = computed(() => {
    const normalized = payee.value.trim();
    
    // Blank or "CASH" (case-insensitive) = anyone
    if (!normalized || normalized.toUpperCase() === 'CASH') return 'anyone';
    
    // Detect mobile: starts with +, 09, etc.
    if (/^(\+|09|\+63)/.test(normalized)) return 'mobile';
    
    // Everything else is vendor alias
    return 'vendor';
});

const normalizedPayee = computed(() => {
    const normalized = payee.value.trim();
    // Normalize "CASH" to empty string
    return normalized.toUpperCase() === 'CASH' ? '' : normalized;
});

const showDisbursementSettings = computed(() => {
    return payeeType.value === 'anyone';  // Only for blank/CASH
});

const isAmountDisabled = computed(() => {
    return voucherType.value === 'payable';
});

const isValidExternalMetadata = computed(() => {
    const json = externalMetadataJson.value.trim();
    if (!json) return true; // Empty is valid (optional)
    
    try {
        JSON.parse(json);
        return true;
    } catch {
        return false;
    }
});

const contextualHelpText = computed(() => {
    switch (payeeType.value) {
        case 'anyone': return 'Anyone can redeem this voucher';
        case 'mobile': return `Restricted to mobile number: ${normalizedPayee.value}`;
        case 'vendor': return `Restricted to merchant: ${normalizedPayee.value}`;
    }
});

// Computed state for location input (auto-add + read-only logic)
const locationInputState = computed(() => {
    const isValidationEnabled = locationValidation.value !== null;
    const isInFields = selectedInputFields.value.includes('location');
    const isAutoAdded = autoAddedFields.value.has('location');
    
    return {
        readOnly: isValidationEnabled,
        checked: isInFields,
        disabled: isValidationEnabled && isAutoAdded,
        label: isValidationEnabled 
            ? 'Location (Required by validation)' 
            : 'Location',
    };
});

// Computed state for OTP input (auto-add when mobile validation enabled)
const otpInputState = computed(() => {
    const isMobileValidation = payeeType.value === 'mobile';
    const isInFields = selectedInputFields.value.includes('otp');
    const isAutoAdded = autoAddedFields.value.has('otp');
    
    return {
        readOnly: isMobileValidation,
        checked: isInFields,
        disabled: isMobileValidation && isAutoAdded,
        label: isMobileValidation 
            ? 'OTP (Required for mobile verification)' 
            : 'OTP',
    };
});

// Validation fields initialized from config

// Build instructions payload for pricing API
const instructionsForPricing = computed(() => {
    const payload: any = {
        cash: {
            amount: amount.value,
            currency: 'PHP',
            settlement_rail: settlementRail.value || null,
            fee_strategy: feeStrategy.value || 'absorb',
            validation: {
                secret: validationSecret.value || null,
                mobile: payeeType.value === 'mobile' ? normalizedPayee.value : null,
                payable: payeeType.value === 'vendor' ? normalizedPayee.value : null,
                country: 'PH',
            },
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
            redirect_timeout: riderRedirectTimeout.value ?? null,
            splash: riderSplash.value || null,
            splash_timeout: riderSplashTimeout.value ?? null,
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
    
    // Add settlement fields if feature enabled and not default type
    if (props.settlement_enabled && voucherType.value !== 'redeemable') {
        payload.voucher_type = voucherType.value;
        payload.target_amount = targetAmount.value;
        if (settlementRules.value) {
            payload.rules = settlementRules.value;
        }
    }
    
    return payload;
});

// Auto-add location input when location validation is enabled
watch(locationValidation, (newVal, oldVal) => {
    const autoAdd = props.config?.location_validation?.auto_add_input ?? true;
    
    if (autoAdd) {
        if (newVal && !oldVal) {
            // Location validation enabled - auto-add location input if not present
            if (!selectedInputFields.value.includes('location')) {
                selectedInputFields.value.push('location');
                autoAddedFields.value.add('location');
            }
        } else if (!newVal && oldVal) {
            // Location validation disabled - remove if auto-added
            if (autoAddedFields.value.has('location')) {
                const index = selectedInputFields.value.indexOf('location');
                if (index > -1) {
                    selectedInputFields.value.splice(index, 1);
                }
                autoAddedFields.value.delete('location');
            }
        }
    }
});

// Auto-add OTP input when mobile validation is enabled
watch(payeeType, (newType, oldType) => {
    const autoAdd = props.config?.mobile_validation?.auto_add_otp ?? true;
    
    if (autoAdd) {
        if (newType === 'mobile' && oldType !== 'mobile') {
            // Mobile validation enabled - auto-add OTP if not present
            if (!selectedInputFields.value.includes('otp')) {
                selectedInputFields.value.push('otp');
                autoAddedFields.value.add('otp');
            }
        } else if (newType !== 'mobile' && oldType === 'mobile') {
            // Mobile validation disabled - remove OTP if auto-added
            if (autoAddedFields.value.has('otp')) {
                const index = selectedInputFields.value.indexOf('otp');
                if (index > -1) {
                    selectedInputFields.value.splice(index, 1);
                }
                autoAddedFields.value.delete('otp');
            }
        }
    }
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

// Always log instructionsForPricing for debugging
watch(instructionsForPricing, (newVal) => {
    console.log('[CreateV2] instructionsForPricing:', newVal);
    console.log('[CreateV2] validationSecret:', validationSecret.value);
    console.log('[CreateV2] payee:', payee.value);
    console.log('[CreateV2] payeeType:', payeeType.value);
}, { deep: true });

// Voucher type-specific amount behavior
const amountInputRef = ref<HTMLInputElement | null>(null);
const targetAmountInputRef = ref<HTMLInputElement | null>(null);

watch(voucherType, (newType, oldType) => {
    if (newType === 'payable') {
        // Payable: Set amount to 0, make read-only, focus on target amount
        amount.value = 0;
        // Focus on target amount field on next tick
        setTimeout(() => {
            const targetInput = document.getElementById('target_amount') as HTMLInputElement;
            if (targetInput) targetInput.focus();
        }, 100);
    } else if (newType === 'settlement') {
        // Settlement: If amount is zero (coming from payable), set to default
        if (amount.value === 0) {
            amount.value = props.config.basic_settings.amount.default;
        }
        // Populate target amount with current amount
        targetAmount.value = amount.value;
        externalMetadataJson.value = ''; // Clear metadata when leaving payable
        // Focus and select amount field
        setTimeout(() => {
            const amountInput = document.getElementById('amount') as HTMLInputElement;
            if (amountInput) {
                amountInput.focus();
                amountInput.select();
            }
        }, 100);
    } else if (newType === 'redeemable') {
        // Redeemable: Restore default amount, enable editing
        amount.value = props.config.basic_settings.amount.default;
        targetAmount.value = null;
        externalMetadataJson.value = ''; // Clear metadata when leaving payable
    }
});

// Settlement: Sync target amount whenever amount changes
watch(amount, (newAmount) => {
    if (voucherType.value === 'settlement') {
        targetAmount.value = newAmount;
    }
});

// Use live pricing API with centralized deduction calculation
const { 
    breakdown: apiBreakdown, 
    loading: pricingLoading, 
    error: pricingError,
    totalDeduction: centralizedTotalDeduction,
    deductionJson,
} = useChargeBreakdown(
    instructionsForPricing,
    { 
        debounce: 500, 
        autoCalculate: true,
        faceValueLabel: props.config.cost_breakdown.face_value_label || 'Voucher Amount (Escrowed)',
    }
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

// Use centralized total deduction calculation from composable
const actualTotalCost = computed(() => centralizedTotalDeduction.value);

// Group charges by category for organized display
const chargesByCategory = computed(() => {
    if (!costBreakdown.value.breakdown) return {};
    
    const groups: Record<string, any[]> = {};
    
    costBreakdown.value.breakdown.forEach(item => {
        const category = item.category || 'other';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(item);
    });
    
    return groups;
});

// Category display names
const categoryLabels: Record<string, string> = {
    base: 'Base Charges',
    input_fields: 'Input Fields',
    feedback: 'Feedback Channels',
    validation: 'Validation Rules',
    rider: 'Rider Configuration',
    other: 'Other Charges',
};

const insufficientFunds = computed(
    () => walletBalance.value !== null && actualTotalCost.value > walletBalance.value,
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
    rider_redirect_timeout: riderRedirectTimeout.value ?? undefined,
    rider_splash: riderSplash.value || undefined,
    rider_splash_timeout: riderSplashTimeout.value ?? undefined,
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
    const data: any = {
        cash: {
            amount: amount.value,
            currency: 'PHP',
            validation: {
                secret: validationSecret.value || null,
                mobile: payeeType.value === 'mobile' ? normalizedPayee.value : null,
                payable: payeeType.value === 'vendor' ? normalizedPayee.value : null,
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
            redirect_timeout: riderRedirectTimeout.value ?? null,
            splash: riderSplash.value || null,
            splash_timeout: riderSplashTimeout.value ?? null,
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
    
    // Add settlement fields if feature enabled and not default type
    if (props.settlement_enabled && voucherType.value !== 'redeemable') {
        data.voucher_type = voucherType.value;
        data.target_amount = targetAmount.value;
        if (settlementRules.value) {
            data.rules = settlementRules.value;
        }
    }
    
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

// Initialize auto-add location input on mount if location validation is enabled
onMounted(() => {
    // If location validation enabled by default or from initial config
    if (locationValidation.value && !selectedInputFields.value.includes('location')) {
        const autoAdd = props.config?.location_validation?.auto_add_input ?? true;
        if (autoAdd) {
            selectedInputFields.value.push('location');
            autoAddedFields.value.add('location');
        }
    }
    
    // If mobile validation enabled on mount, auto-add OTP
    if (payeeType.value === 'mobile' && !selectedInputFields.value.includes('otp')) {
        const autoAdd = props.config?.mobile_validation?.auto_add_otp ?? true;
        if (autoAdd) {
            selectedInputFields.value.push('otp');
            autoAddedFields.value.add('otp');
        }
    }
});

// Form submission
const handleSubmit = async () => {
    if (insufficientFunds.value) return;

    validationErrors.value = {};
    
    // Parse external metadata for payable vouchers
    let parsedExternalMetadata = undefined;
    if (voucherType.value === 'payable' && externalMetadataJson.value.trim()) {
        try {
            parsedExternalMetadata = JSON.parse(externalMetadataJson.value.trim());
        } catch {
            // Invalid JSON - should not happen due to validation, but handle gracefully
            parsedExternalMetadata = undefined;
        }
    }
    
    const payload = {
        amount: amount.value,
        count: count.value,
        prefix: prefix.value || undefined,
        mask: mask.value || undefined,
        ttl_days: ttlDays.value || undefined,
        input_fields: selectedInputFields.value.length > 0 ? selectedInputFields.value : undefined,
        validation_secret: validationSecret.value || undefined,
        validation_mobile: payeeType.value === 'mobile' ? normalizedPayee.value : undefined,
        validation_payable: payeeType.value === 'vendor' ? normalizedPayee.value : undefined,
        settlement_rail: settlementRail.value || undefined,
        fee_strategy: feeStrategy.value || undefined,
        feedback_email: feedbackEmail.value || undefined,
        feedback_mobile: feedbackMobile.value || undefined,
        feedback_webhook: feedbackWebhook.value || undefined,
        rider_message: riderMessage.value || null,
        rider_url: riderUrl.value || null,
        rider_redirect_timeout: riderRedirectTimeout.value ?? null,
        rider_splash: riderSplash.value || null,
        rider_splash_timeout: riderSplashTimeout.value ?? null,
        campaign_id: selectedCampaignId.value || undefined,
        // Validation instructions
        validation_location: locationValidation.value || undefined,
        validation_time: timeValidation.value || undefined,
        // Preview controls
        preview_enabled: previewEnabled.value,
        preview_scope: previewScope.value,
        preview_message: previewMessage.value || undefined,
        // Settlement voucher fields
        voucher_type: (props.settlement_enabled && voucherType.value !== 'redeemable') ? voucherType.value : undefined,
        target_amount: (props.settlement_enabled && voucherType.value !== 'redeemable') ? targetAmount.value : undefined,
        rules: (props.settlement_enabled && settlementRules.value) ? settlementRules.value : undefined,
        // External metadata (only for payable vouchers)
        external_metadata: parsedExternalMetadata,
    };
    
    const result = await generateVouchers(payload);

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
            <div class="flex items-start justify-between">
                <Heading
                    :title="config.page.title"
                    :description="config.page.description"
                />
                
                <div class="flex items-center gap-4">
                    <!-- Expand/Collapse All (Advanced Mode only) -->
                    <div v-if="hasAdvancedMode && !isSimpleMode" class="flex items-center gap-2 text-sm">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="expandAll"
                            class="h-8 px-2"
                        >
                            Expand All
                        </Button>
                        <span class="text-muted-foreground">|</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="collapseAll"
                            class="h-8 px-2"
                        >
                            Collapse All
                        </Button>
                    </div>
                    
                    <!-- Mode Toggle (only if user has advanced mode feature) -->
                    <div v-if="hasAdvancedMode" class="flex items-center gap-2">
                        <Label for="mode-toggle" class="text-sm text-muted-foreground">
                            {{ isSimpleMode ? 'Simple' : 'Advanced' }} Mode
                        </Label>
                        <Switch
                            id="mode-toggle"
                            :checked="isSwitchChecked"
                            @update:checked="(checked) => switchMode(checked ? 'advanced' : 'simple')"
                        />
                    </div>
                </div>
            </div>

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
                                Configure voucher amount and quantity
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Campaign Template Selector (Advanced Mode only) -->
                            <div v-if="!isSimpleMode && config.basic_settings.show_campaign_selector" class="space-y-2 pb-4 border-b">
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
                                        :disabled="isAmountDisabled"
                                        :readonly="isAmountDisabled"
                                        :class="{ 'opacity-60 cursor-not-allowed': isAmountDisabled }"
                                        required
                                    />
                                    <p v-if="voucherType === 'payable'" class="text-xs text-muted-foreground">
                                        Payable vouchers have no initial amount - set target amount instead
                                    </p>
                                    <p v-else-if="voucherType === 'settlement'" class="text-xs text-muted-foreground">
                                        Settlement amount syncs to target amount (loan principal)
                                    </p>
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

                            <!-- Payee field (bank check metaphor) - Visible in both Simple and Advanced modes -->
                            <div v-if="config.basic_settings.show_payee" class="space-y-2">
                                <Label for="payee">
                                    <span class="flex items-center gap-2">
                                        <Receipt class="h-4 w-4" />
                                        {{ config.basic_settings.payee.label }}
                                    </span>
                                </Label>
                                <Input
                                    id="payee"
                                    v-model="payee"
                                    :placeholder="config.basic_settings.payee.placeholder"
                                    list="vendor-aliases-datalist"
                                />
                                <datalist id="vendor-aliases-datalist">
                                    <option v-for="alias in vendorAliases" :key="alias.id" :value="alias.alias" />
                                </datalist>
                                <p class="text-xs text-muted-foreground">
                                    {{ contextualHelpText }}
                                </p>
                            </div>

                            <!-- Settlement Voucher Type (Advanced Mode + Feature Flag) -->
                            <div v-if="!isSimpleMode && settlement_enabled" class="pt-4 border-t space-y-4">
                                <h4 class="text-sm font-medium">Settlement Voucher Settings</h4>
                                
                                <div class="space-y-2">
                                    <Label>Voucher Type</Label>
                                    <RadioGroup v-model="voucherType" class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="redeemable" id="type-redeemable" />
                                            <Label for="type-redeemable" class="font-normal cursor-pointer">
                                                <div>
                                                    <div class="font-medium">Redeemable (Default)</div>
                                                    <div class="text-xs text-muted-foreground">Standard one-time redemption voucher</div>
                                                </div>
                                            </Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="payable" id="type-payable" />
                                            <Label for="type-payable" class="font-normal cursor-pointer">
                                                <div>
                                                    <div class="font-medium">Payable</div>
                                                    <div class="text-xs text-muted-foreground">Accepts payments until target amount reached</div>
                                                </div>
                                            </Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="settlement" id="type-settlement" />
                                            <Label for="type-settlement" class="font-normal cursor-pointer">
                                                <div>
                                                    <div class="font-medium">Settlement</div>
                                                    <div class="text-xs text-muted-foreground">Enterprise settlement instrument (multi-payment)</div>
                                                </div>
                                            </Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                <!-- Target Amount (shown for payable/settlement types) -->
                                <div v-if="voucherType === 'payable' || voucherType === 'settlement'" class="space-y-2">
                                    <Label for="target_amount">Target Amount (₱)</Label>
                                    <Input
                                        id="target_amount"
                                        type="number"
                                        v-model.number="targetAmount"
                                        :min="1"
                                        :step="0.01"
                                        placeholder="Enter target amount"
                                        required
                                    />
                                    <p v-if="voucherType === 'settlement'" class="text-xs text-muted-foreground">
                                        Syncs with loan principal amount above (editable)
                                    </p>
                                    <p v-else class="text-xs text-muted-foreground">
                                        Total amount to collect before voucher closes
                                    </p>
                                    <InputError :message="validationErrors.target_amount" />
                                </div>
                                
                                <!-- External Metadata (payable only) -->
                                <div v-if="voucherType === 'payable'" class="space-y-2">
                                    <Label for="external_metadata">External Metadata (Optional)</Label>
                                    <Textarea
                                        id="external_metadata"
                                        v-model="externalMetadataJson"
                                        placeholder='{"reference":"REF-001", "project_name":"Product X", "invoice_number":"INV-2026-001"}'
                                        rows="4"
                                        class="font-mono text-sm"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Freeform JSON for tracking (e.g., reference code, invoice number, project name)
                                    </p>
                                    <p v-if="!isValidExternalMetadata" class="text-xs text-destructive">
                                        ⚠ Invalid JSON format
                                    </p>
                                </div>
                            </div>

                            <!-- Advanced fields (only in Advanced Mode) -->
                            <template v-if="!isSimpleMode">
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
                            </template>

                            <!-- Settlement Rail & Fee Strategy (Advanced Mode only, and only for disbursement vouchers) -->
                            <div v-if="!isSimpleMode && showDisbursementSettings" class="pt-4 border-t space-y-4">
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
                                    <div class="text-xs text-muted-foreground space-y-1">
                                        <p>Auto mode selects INSTAPAY for amounts &lt; ₱50k, PESONET otherwise</p>
                                        <p class="text-amber-600 dark:text-amber-500">
                                            <strong>Note:</strong> EMIs (GCash, PayMaya, etc.) only support INSTAPAY. Use INSTAPAY or Auto mode for EMI redeemers.
                                        </p>
                                    </div>
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
                    <Collapsible v-if="!isSimpleMode && config.input_fields.show_card" v-model:open="collapsibleCards.input_fields">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader v-if="config.input_fields.show_header" class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <FileText class="h-5 w-5" />
                                            <CardTitle v-if="config.input_fields.show_title">{{ config.input_fields.title }}</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.input_fields }" />
                                    </div>
                                    <CardDescription v-if="config.input_fields.show_description">
                                        {{ config.input_fields.description }}
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="option in input_field_options"
                                    :key="option.value"
                                    :class="[
                                        'flex items-center space-x-2',
                                        (option.value === 'location' && locationInputState.readOnly) ||
                                        (option.value === 'otp' && otpInputState.readOnly)
                                            ? 'opacity-75 cursor-not-allowed'
                                            : 'cursor-pointer'
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        :id="option.value"
                                        :value="option.value"
                                        v-model="selectedInputFields"
                                        :disabled="
                                            (option.value === 'location' && locationInputState.disabled) ||
                                            (option.value === 'otp' && otpInputState.disabled)
                                        "
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm flex items-center gap-2">
                                        {{ 
                                            option.value === 'location' ? locationInputState.label :
                                            option.value === 'otp' ? otpInputState.label :
                                            option.label 
                                        }}
                                        <Info
                                            v-if="
                                                (option.value === 'location' && locationInputState.readOnly) ||
                                                (option.value === 'otp' && otpInputState.readOnly)
                                            "
                                            class="h-3 w-3 text-muted-foreground"
                                            :title="option.value === 'location' ? 'Required by location validation' : 'Required for mobile verification'"
                                        />
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
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Validation Rules -->
                    <Collapsible v-if="!isSimpleMode && config.validation_rules.show_card" v-model:open="collapsibleCards.validation_rules">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader v-if="config.validation_rules.show_header" class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <AlertCircle class="h-5 w-5" />
                                            <CardTitle v-if="config.validation_rules.show_title">{{ config.validation_rules.title }}</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.validation_rules }" />
                                    </div>
                                    <CardDescription v-if="config.validation_rules.show_description">
                                        {{ config.validation_rules.description }}
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent class="space-y-6">
                            <!-- Secret Code -->
                            <div class="space-y-4">
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
                            </div>

                            <!-- Location Validation (nested) -->
                            <div v-if="config.location_validation.show_card" class="space-y-2">
                                <LocationValidationForm
                                    v-model="locationValidation"
                                    :validation-errors="validationErrors"
                                    :config="config.location_validation"
                                    :collapsible="false"
                                />
                            </div>

                            <!-- Time Validation (nested) -->
                            <div v-if="config.time_validation.show_card" class="space-y-2">
                                <TimeValidationForm
                                    v-model="timeValidation"
                                    :validation-errors="validationErrors"
                                    :config="config.time_validation"
                                    :collapsible="false"
                                />
                            </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Feedback Channels -->
                    <Collapsible v-if="!isSimpleMode && config.feedback_channels.show_card" v-model:open="collapsibleCards.feedback_channels">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader v-if="config.feedback_channels.show_header" class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Send class="h-5 w-5" />
                                            <CardTitle v-if="config.feedback_channels.show_title">{{ config.feedback_channels.title }}</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.feedback_channels }" />
                                    </div>
                                    <CardDescription v-if="config.feedback_channels.show_description">
                                        {{ config.feedback_channels.description }}
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
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
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Rider -->
                    <Collapsible v-if="!isSimpleMode && config.rider.show_card" v-model:open="collapsibleCards.rider">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader v-if="config.rider.show_header" class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <FileText class="h-5 w-5" />
                                            <CardTitle v-if="config.rider.show_title">{{ config.rider.title }}</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.rider }" />
                                    </div>
                                    <CardDescription v-if="config.rider.show_description">
                                        {{ config.rider.description }}
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
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

                            <div class="space-y-2">
                                <Label for="rider_redirect_timeout">Redirect Timeout (seconds)</Label>
                                <Input
                                    id="rider_redirect_timeout"
                                    name="rider_redirect_timeout"
                                    type="number"
                                    v-model.number="riderRedirectTimeout"
                                    placeholder="10"
                                    :min="0"
                                    :max="300"
                                />
                                <InputError :message="validationErrors.rider_redirect_timeout" />
                                <p class="text-xs text-muted-foreground">
                                    Time to wait before auto-redirect (0 = manual only, leave empty for default: 10s)
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="rider_splash">Splash Page Content</Label>
                                <Textarea
                                    id="rider_splash"
                                    name="rider_splash"
                                    v-model="riderSplash"
                                    placeholder="Enter splash page content (supports markdown, HTML, or plain text)..."
                                    rows="8"
                                    :maxlength="51200"
                                />
                                <InputError :message="validationErrors.rider_splash" />
                                <p class="text-xs text-muted-foreground">
                                    Shown as first page before redemption flow (supports markdown, HTML, SVG, or URL). Maximum 50KB.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="rider_splash_timeout">Splash Timeout (seconds)</Label>
                                <Input
                                    id="rider_splash_timeout"
                                    name="rider_splash_timeout"
                                    type="number"
                                    v-model.number="riderSplashTimeout"
                                    placeholder="5"
                                    :min="0"
                                    :max="60"
                                />
                                <InputError :message="validationErrors.rider_splash_timeout" />
                                <p class="text-xs text-muted-foreground">
                                    Time to wait before auto-advancing from splash page (0 = manual only, leave empty for default: 5s)
                                </p>
                            </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Preview Controls -->
                    <Collapsible v-if="!isSimpleMode" v-model:open="collapsibleCards.preview_controls">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Eye class="h-5 w-5" />
                                            <CardTitle>Preview Controls</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.preview_controls }" />
                                    </div>
                                    <CardDescription>
                                        Control what information is visible when the voucher code is previewed before redemption
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent class="space-y-4">
                            <!-- Preview Enabled Toggle -->
                            <div class="flex items-start space-x-3">
                                <Checkbox
                                    id="preview_enabled"
                                    v-model:checked="previewEnabled"
                                />
                                <div class="space-y-1 leading-none">
                                    <Label
                                        for="preview_enabled"
                                        class="text-sm font-medium cursor-pointer"
                                    >
                                        Allow Preview
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Allow redeemers to preview voucher details by entering the code on /redeem
                                    </p>
                                </div>
                            </div>

                            <!-- Preview Scope (only shown when preview enabled) -->
                            <div v-if="previewEnabled" class="space-y-2">
                                <Label for="preview_scope">Preview Scope</Label>
                                <Select v-model="previewScope">
                                    <SelectTrigger id="preview_scope">
                                        <SelectValue placeholder="Select preview scope" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="full">Full Details (amount, instructions, metadata)</SelectItem>
                                        <SelectItem value="requirements_only">Requirements Only (input fields, validations)</SelectItem>
                                        <SelectItem value="none">None (completely hidden)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-xs text-muted-foreground">
                                    Choose how much information to reveal when the voucher is previewed
                                </p>
                            </div>

                            <!-- Preview Message (optional issuer note) -->
                            <div v-if="previewEnabled" class="space-y-2">
                                <Label for="preview_message">Preview Message (Optional)</Label>
                                <Textarea
                                    id="preview_message"
                                    v-model="previewMessage"
                                    placeholder="Add a custom message shown during preview (e.g., 'This voucher is for authorized personnel only')"
                                    rows="2"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Optional message displayed to the redeemer during preview
                                </p>
                            </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Instruction JSON Preview -->
                    <Collapsible v-if="!isSimpleMode && config.json_preview.show_card" v-model:open="collapsibleCards.json_preview">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Code class="h-5 w-5" />
                                            <CardTitle v-if="config.json_preview.show_title">{{ config.json_preview.title }}</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.json_preview }" />
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
                    
                    <!-- Wallet Deduction JSON Preview -->
                    <Collapsible v-if="!isSimpleMode" v-model:open="collapsibleCards.deduction_json_preview">
                        <Card>
                            <CollapsibleTrigger class="w-full">
                                <CardHeader class="cursor-pointer hover:bg-muted/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Banknote class="h-5 w-5" />
                                            <CardTitle>Wallet Deduction JSON</CardTitle>
                                        </div>
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': collapsibleCards.deduction_json_preview }" />
                                    </div>
                                    <CardDescription>
                                        Real-time wallet deduction breakdown (all amounts in pesos)
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent>
                                    <div v-if="pricingLoading" class="text-sm text-muted-foreground text-center py-4">
                                        Calculating charges...
                                    </div>
                                    <div v-else-if="!deductionJson" class="text-sm text-muted-foreground text-center py-4">
                                        Enter an amount to see deduction breakdown
                                    </div>
                                    <pre v-else class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(deductionJson, null, 2) }}</code></pre>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Simple Mode Upgrade Notice (only if user has advanced mode feature) -->
                    <Card v-if="hasAdvancedMode && isSimpleMode" class="border-primary/50 bg-primary/5">
                        <CardContent class="pt-6">
                            <div class="flex items-start gap-3">
                                <Info class="h-5 w-5 text-primary mt-0.5 flex-shrink-0" />
                                <div class="space-y-2">
                                    <p class="text-sm font-medium">
                                        Need more customization?
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        Switch to Advanced Mode to access campaign templates, input fields, validation rules, feedback channels, custom expiry, and more.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="switchMode('advanced')"
                                        class="mt-2"
                                    >
                                        Switch to Advanced Mode
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Simple Mode: Wallet Balance & Submit -->
                <div v-if="isSimpleMode" class="lg:col-span-1">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <Banknote class="h-5 w-5" />
                                <CardTitle>Wallet Balance</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Current Balance</span>
                                    <span
                                        :class="
                                            insufficientFunds
                                                ? 'text-destructive'
                                                : 'text-green-600 dark:text-green-400'
                                        "
                                        class="font-medium"
                                    >{{ formattedBalance }}</span>
                                </div>
                                <div v-if="realtimeNote" class="text-xs text-muted-foreground italic">
                                    {{ realtimeNote }}
                                </div>
                            </div>

                            <Separator />

                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="loading || insufficientFunds"
                            >
                                {{
                                    insufficientFunds
                                        ? 'Insufficient Funds'
                                        : loading
                                          ? 'Generating...'
                                          : 'Generate Vouchers'
                                }}
                            </Button>

                            <p
                                v-if="insufficientFunds"
                                class="text-center text-xs text-destructive"
                            >
                                Please top up your wallet to continue
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Cost Preview Sidebar (Advanced Mode only) -->
                <div v-if="!isSimpleMode && config.cost_breakdown.show_sidebar" class="lg:col-span-1">
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
                            <div v-else class="space-y-3 text-sm">
                                <!-- Base voucher amount (face value - escrowed) -->
                                <div class="flex justify-between pb-2 border-b border-border/50">
                                    <span class="text-muted-foreground">{{ config.cost_breakdown.face_value_label || 'Voucher Amount' }} × {{ count }}</span>
                                    <span class="font-medium">₱{{ (amount * count).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                </div>
                                
                                <!-- Charges grouped by category -->
                                <div
                                    v-for="(items, category) in chargesByCategory"
                                    :key="category"
                                    class="space-y-1"
                                >
                                    <!-- Category header -->
                                    <div class="text-xs font-semibold text-foreground/70 uppercase tracking-wide pt-1">
                                        {{ categoryLabels[category] || category }}
                                    </div>
                                    
                                    <!-- Items in this category -->
                                    <div
                                        v-for="item in items"
                                        :key="item.index"
                                        class="flex justify-between pl-2"
                                    >
                                        <span class="text-muted-foreground">{{ item.label }}</span>
                                        <span class="font-medium">{{ item.price_formatted }}</span>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <div class="flex justify-between text-base font-semibold">
                                <span>{{ config.cost_breakdown.total_label || 'Total Deduction' }}</span>
                                <span>₱{{ actualTotalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
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
                                                ? (walletBalance - actualTotalCost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
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
