<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import { useVoucherTiming } from '@/composables/useVoucherTiming';
import type { ValidateVoucherResponse } from '@/composables/useRedemptionApi';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle } from 'lucide-vue-next';
import BankSelect from '@/components/BankSelect.vue';
import AppLogo from '@/components/AppLogo.vue';

interface VoucherData {
    code: string;
    amount: number;
    currency: string;
    created_at: string | null;
    expires_at: string | null;
    owner: {
        name: string;
        email: string;
    } | null;
    count: number | null;
}

interface Bank {
    code: string;
    name: string;
}

interface Props {
    voucher_code: string;
    voucher: VoucherData;
    banks: Bank[];
    config: any;
}

const props = defineProps<Props>();

const { loading, error, validateVoucher, redeemVoucher } = useRedemptionApi();
const { trackRedemptionStart } = useVoucherTiming();

const voucherInfo = ref<ValidateVoucherResponse | null>(null);
const validationErrors = ref<Record<string, string>>({});

const mobileInputWrapper = ref<HTMLElement | null>(null);
const accountNumberInputWrapper = ref<HTMLElement | null>(null);
let manualAccountOverride = false;

// Auto-sync configuration
const autoSyncEnabled = computed(() => props.config?.contact_payment?.auto_sync_account_number ?? true);
const autoSyncBankCodes = computed(() => props.config?.contact_payment?.auto_sync_bank_codes || ['GXCHPHM2XXX']);
const autoSyncDelay = computed(() => props.config?.contact_payment?.auto_sync_delay || 1500);

const form = ref({
    mobile: props.config?.contact_payment?.mobile_default || '',
    country: props.config?.contact_payment?.country_default || 'PH',
    bank_code: props.config?.contact_payment?.bank_default || '',
    account_number: props.config?.contact_payment?.account_number_default || '',
    secret: '',
    inputs: {} as Record<string, any>,
});

const formattedAmount = computed(() => {
    if (!voucherInfo.value && !props.voucher) return '';
    const currency = voucherInfo.value?.voucher.currency || props.voucher?.currency || 'PHP';
    const amount = voucherInfo.value?.voucher.amount || props.voucher?.amount || 0;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
    }).format(amount);
});

const formattedGeneratedAt = computed(() => {
    if (!props.voucher?.created_at) return '';
    return new Date(props.voucher.created_at).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const formattedExpiresAt = computed(() => {
    if (!voucherInfo.value?.voucher.expires_at && !props.voucher?.expires_at) return '';
    const expiresAt = voucherInfo.value?.voucher.expires_at || props.voucher?.expires_at;
    if (!expiresAt) return '';
    return new Date(expiresAt).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
});

const submitButtonText = computed(() => {
    if (loading.value) {
        return props.config?.contact_payment?.submit_button_processing_text || 'Processing...';
    }
    if (props.config?.contact_payment?.show_code_in_submit_button) {
        const action = props.config?.contact_payment?.submit_button_action || 'Redeem';
        return `${action} ${props.voucher_code}`;
    }
    return props.config?.contact_payment?.submit_button_text || 'Redeem Voucher';
});

// Debounce helper
function debounce<T extends (...args: any[]) => void>(fn: T, delay: number): T {
    let timer: ReturnType<typeof setTimeout>;
    return function (this: any, ...args: any[]) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    } as T;
}

// Sync account_number to mobile only if it wasn't manually changed
const updateAccountNumber = debounce((mobile: string) => {
    if (!manualAccountOverride && autoSyncEnabled.value && autoSyncBankCodes.value.includes(form.value.bank_code)) {
        form.value.account_number = mobile;
    }
}, autoSyncDelay.value);

// Watch mobile number changes
watch(() => form.value.mobile, (mobile) => {
    updateAccountNumber(mobile);
});

// Track manual account number changes
watch(() => form.value.account_number, () => {
    manualAccountOverride = true;
});

// When bank changes, clear account number and reset override flag
watch(() => form.value.bank_code, async (newVal, oldVal) => {
    if (newVal !== oldVal) {
        form.value.account_number = '';
        manualAccountOverride = false;
        
        // Wait for DOM update, then focus
        await nextTick();
        const input = accountNumberInputWrapper.value?.querySelector('input');
        input?.focus();
    }
});

const hasSecret = computed(() => {
    return voucherInfo.value?.required_validation?.secret || false;
});


const requiresLocation = computed(() => {
    return (voucherInfo.value?.required_inputs || []).includes('location');
});

const requiresSelfie = computed(() => {
    return (voucherInfo.value?.required_inputs || []).includes('selfie');
});

const requiresSignature = computed(() => {
    return (voucherInfo.value?.required_inputs || []).includes('signature');
});


const handleSubmit = async () => {
    validationErrors.value = {};
    
    // Prepare stored data for multi-step flow
    const storedData = {
        mobile: form.value.mobile,
        country: form.value.country,
        secret: form.value.secret || undefined,
        bank_code: form.value.bank_code || undefined,
        account_number: form.value.account_number || undefined,
        inputs: {},
        required_inputs: voucherInfo.value?.required_inputs || [],
    };
    
    // Check if there are text-based inputs (not location, selfie, signature)
    const textBasedInputs = (voucherInfo.value?.required_inputs || [])
        .filter((input: string) => !['location', 'selfie', 'signature'].includes(input));
    
    // If there are text-based inputs, go to Inputs page
    if (textBasedInputs.length > 0) {
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(storedData));
        router.visit(`/redeem/${props.voucher_code}/inputs`);
        return;
    }
    
    // If location is required, save data and navigate to location page
    if (requiresLocation.value) {
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(storedData));
        router.visit(`/redeem/${props.voucher_code}/location`);
        return;
    }
    
    // If selfie is required (but not location), save data and navigate to selfie page
    if (requiresSelfie.value) {
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(storedData));
        router.visit(`/redeem/${props.voucher_code}/selfie`);
        return;
    }
    
    // If signature is required (but not location/selfie), save data and navigate to signature page
    if (requiresSignature.value) {
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(storedData));
        router.visit(`/redeem/${props.voucher_code}/signature`);
        return;
    }
    
    // No additional fields needed, proceed to finalize for confirmation
    try {
        sessionStorage.setItem(`redeem_${props.voucher_code}`, JSON.stringify(storedData));
        router.visit(`/redeem/${props.voucher_code}/finalize`);
    } catch (err: any) {
        console.error('Navigation failed:', err);
    }
};

onMounted(async () => {
    // Track redemption start
    trackRedemptionStart(props.voucher_code);
    
    try {
        const result = await validateVoucher(props.voucher_code);
        voucherInfo.value = result;

        if (!result.can_redeem) {
            router.visit('/redeem');
        }

        // Input fields are handled in Inputs.vue, not here
        
        // Focus mobile input after data is loaded
        await nextTick();
        const input = mobileInputWrapper.value?.querySelector('input');
        input?.focus();
    } catch (err) {
        router.visit('/redeem');
    }
});
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Redeem Voucher" />
        <div class="w-full max-w-md">
            <!-- Logo and App Name -->
            <div v-if="config?.widget?.show_logo || config?.widget?.show_app_name" class="flex flex-col items-center gap-2 mb-6">
                <AppLogo v-if="config?.widget?.show_logo" />
                <span v-if="config?.widget?.show_app_name" class="text-lg font-medium">{{ config?.widget?.app_name || 'Redeem' }}</span>
            </div>
            
            <!-- Main Content -->
            <!-- Loading State -->
            <div v-if="!voucherInfo" class="flex min-h-[50vh] items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <template v-else>
                <!-- Error Alert -->
                <Alert v-if="error" variant="destructive" class="mb-6">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{ error }}
                    </AlertDescription>
                </Alert>

                <!-- Voucher Info Card -->
                <Card v-if="config?.show_voucher_details_card" class="mb-3 border-muted/60">
                    <CardHeader v-if="config?.voucher_details?.show_header" class="py-2 px-4 pb-0">
                        <CardTitle v-if="config?.voucher_details?.show_title" class="text-sm font-medium text-muted-foreground">
                            {{ config?.voucher_details?.title || 'Voucher Details' }}
                        </CardTitle>
                        <CardDescription v-if="config?.voucher_details?.show_description && config?.voucher_details?.description" class="text-xs mt-0.5">
                            {{ config?.voucher_details?.description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-1 pt-1 pb-2 px-4">
                        <div v-if="config?.voucher_details?.show_code" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ config?.voucher_details?.code_label || 'Code' }}:</span>
                            <span class="font-mono text-xs">{{ voucherInfo?.voucher.code || voucher.code }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_amount" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ config?.voucher_details?.amount_label || 'Amount' }}:</span>
                            <span class="text-sm font-semibold text-green-600">{{ formattedAmount }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_owner && voucher.owner" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ config?.voucher_details?.owner_label || 'Issued by' }}:</span>
                            <span class="text-xs">{{ voucher.owner.name }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_generated_at && voucher.created_at" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ config?.voucher_details?.generated_at_label || 'Generated' }}:</span>
                            <span class="text-xs">{{ formattedGeneratedAt }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_count && voucher.count" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ config?.voucher_details?.count_label || 'Batch size' }}:</span>
                            <span class="text-xs">{{ voucher.count }} {{ voucher.count === 1 ? 'voucher' : 'vouchers' }}</span>
                        </div>
                        <div v-if="config?.voucher_details?.show_expires_at && formattedExpiresAt" class="flex justify-between items-baseline">
                            <span class="text-xs text-muted-foreground">{{ config?.voucher_details?.expires_at_label || 'Expires' }}:</span>
                            <span class="text-xs">{{ formattedExpiresAt }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Instruction Details Card -->
                <Card v-if="config?.show_instruction_details_card && (hasSecret || requiresLocation || requiresSelfie || requiresSignature)" class="mb-6">
                    <CardHeader v-if="config?.instruction_details?.show_header">
                        <CardTitle v-if="config?.instruction_details?.show_title">
                            {{ config?.instruction_details?.title || 'What You\'ll Need' }}
                        </CardTitle>
                        <CardDescription v-if="config?.instruction_details?.show_description">
                            {{ config?.instruction_details?.description || 'Please prepare the following before proceeding' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Validation Requirements -->
                        <div v-if="config?.instruction_details?.show_validation_requirements && hasSecret">
                            <p class="text-sm font-medium mb-2">{{ config?.instruction_details?.validation_label || 'Validation Required' }}:</p>
                            <ul class="text-sm text-muted-foreground space-y-1 list-disc list-inside">
                                <li>{{ config?.instruction_details?.secret_hint || 'A secret code is required for this voucher' }}</li>
                            </ul>
                        </div>
                        
                        <!-- Capture Requirements -->
                        <div v-if="config?.instruction_details?.show_capture_requirements && (requiresLocation || requiresSelfie || requiresSignature)">
                            <p class="text-sm font-medium mb-2">{{ config?.instruction_details?.capture_label || 'You will need to provide' }}:</p>
                            <ul class="text-sm text-muted-foreground space-y-1 list-disc list-inside">
                                <li v-if="requiresLocation">{{ config?.instruction_details?.location_hint || 'Your current location - please enable location services' }}</li>
                                <li v-if="requiresSelfie">{{ config?.instruction_details?.selfie_hint || 'A clear selfie photo - please ensure good lighting' }}</li>
                                <li v-if="requiresSignature">{{ config?.instruction_details?.signature_hint || 'Your digital signature' }}</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <!-- Wallet Form Card -->
                <Card v-if="config?.show_contact_payment_card">
                    <CardHeader v-if="config?.contact_payment?.show_header">
                        <CardTitle v-if="config?.contact_payment?.show_title">
                            {{ config?.contact_payment?.title || 'Contact & Payment Details' }}
                        </CardTitle>
                        <CardDescription v-if="config?.contact_payment?.show_description">
                            {{ config?.contact_payment?.description || 'Provide your mobile number and bank account to receive the cash' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Mobile Number -->
                            <div ref="mobileInputWrapper" class="space-y-2">
                                <Label for="mobile">{{ config?.contact_payment?.mobile_label || 'Mobile Number' }} *</Label>
                                <Input
                                    id="mobile"
                                    v-model="form.mobile"
                                    type="tel"
                                    :placeholder="config?.contact_payment?.mobile_placeholder || '+63 917 123 4567'"
                                    required
                                    :disabled="loading"
                                />
                                <p v-if="validationErrors.mobile" class="text-sm text-red-600">
                                    {{ Array.isArray(validationErrors.mobile) ? validationErrors.mobile[0] : validationErrors.mobile }}
                                </p>
                                <p v-if="config?.contact_payment?.mobile_hint" class="text-xs text-muted-foreground">
                                    {{ config?.contact_payment?.mobile_hint }}
                                </p>
                            </div>

                            <!-- Secret (if required) -->
                            <div v-if="hasSecret" class="space-y-2">
                                <Label for="secret">{{ config?.contact_payment?.secret_label || 'Secret Code' }} *</Label>
                                <Input
                                    id="secret"
                                    v-model="form.secret"
                                    type="password"
                                    :placeholder="config?.contact_payment?.secret_placeholder || 'Enter secret code'"
                                    :required="hasSecret"
                                    :disabled="loading"
                                />
                                <p v-if="validationErrors.secret" class="text-sm text-red-600">
                                    {{ validationErrors.secret }}
                                </p>
                                <p v-if="config?.contact_payment?.secret_hint" class="text-xs text-muted-foreground">
                                    {{ config?.contact_payment?.secret_hint }}
                                </p>
                            </div>

                            <!-- Input fields removed - they belong in Inputs.vue -->

                            <!-- Bank Selection -->
                            <div v-if="config?.contact_payment?.show_bank_fields" class="space-y-2">
                                <Label for="bank">{{ config?.contact_payment?.bank_label || 'Bank (Optional)' }}</Label>
                                <BankSelect
                                    v-model="form.bank_code"
                                    :banks="banks"
                                    :placeholder="config?.contact_payment?.bank_placeholder || 'Select a bank'"
                                    :disabled="loading"
                                    :config="config?.bank_select"
                                />
                                <p v-if="validationErrors.bank_code" class="text-sm text-red-600">
                                    {{ validationErrors.bank_code }}
                                </p>
                            </div>

                            <!-- Account Number -->
                            <div v-if="config?.contact_payment?.show_bank_fields && form.bank_code" ref="accountNumberInputWrapper" class="space-y-2">
                                <Label for="account_number">{{ config?.contact_payment?.account_number_label || 'Account Number' }} *</Label>
                                <Input
                                    id="account_number"
                                    v-model="form.account_number"
                                    type="text"
                                    :placeholder="config?.contact_payment?.account_number_placeholder || 'Enter account number'"
                                    :required="!!form.bank_code"
                                    :disabled="loading"
                                />
                                <p v-if="validationErrors.account_number" class="text-sm text-red-600">
                                    {{ validationErrors.account_number }}
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex gap-3 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex-1"
                                    @click="router.visit('/redeem')"
                                    :disabled="loading"
                                >
                                    {{ config?.contact_payment?.cancel_button_text || 'Cancel' }}
                                </Button>
                                <Button
                                    type="submit"
                                    class="flex-1"
                                    :disabled="loading"
                                >
                                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                                    {{ submitButtonText }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </template>
        </div>
    </div>
</template>
