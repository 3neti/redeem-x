<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useRedemptionApi } from '@/composables/useRedemptionApi';
import type { ValidateVoucherResponse } from '@/composables/useRedemptionApi';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, AlertCircle } from 'lucide-vue-next';

interface Props {
    voucher_code: string;
}

const props = defineProps<Props>();

const { loading, error, validateVoucher, redeemVoucher } = useRedemptionApi();

const voucherInfo = ref<ValidateVoucherResponse | null>(null);
const validationErrors = ref<Record<string, string>>({});

const form = ref({
    mobile: '',
    country: 'PH',
    bank_code: '',
    account_number: '',
    secret: '',
    inputs: {} as Record<string, any>,
});

const banks = [
    { code: 'BDO', name: 'BDO Unibank' },
    { code: 'BPI', name: 'Bank of the Philippine Islands' },
    { code: 'MBTC', name: 'Metrobank' },
    { code: 'UBP', name: 'UnionBank' },
    { code: 'SECB', name: 'Security Bank' },
    { code: 'RCBC', name: 'RCBC' },
    { code: 'PNB', name: 'Philippine National Bank' },
    { code: 'LBP', name: 'Land Bank of the Philippines' },
    { code: 'DBP', name: 'Development Bank of the Philippines' },
    { code: 'CBC', name: 'Chinabank' },
];

const formattedAmount = computed(() => {
    if (!voucherInfo.value) return '';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: voucherInfo.value.voucher.currency || 'PHP',
    }).format(voucherInfo.value.voucher.amount || 0);
});

const hasSecret = computed(() => {
    return voucherInfo.value?.required_validation?.secret || false;
});

const requiredInputs = computed(() => {
    return (voucherInfo.value?.required_inputs || [])
        .filter(field => field !== 'signature' && field !== 'location' && field !== 'selfie');
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

// Field configuration for dynamic inputs
const fieldConfig: Record<string, { label: string; type: string; placeholder?: string }> = {
    name: { label: 'Full Name', type: 'text', placeholder: 'Juan Dela Cruz' },
    email: { label: 'Email Address', type: 'email', placeholder: 'juan@example.com' },
    address: { label: 'Full Address', type: 'text', placeholder: '123 Main St, City' },
    birth_date: { label: 'Birth Date', type: 'date' },
    gross_monthly_income: { label: 'Gross Monthly Income', type: 'number', placeholder: '0' },
    location: { label: 'Location', type: 'text', placeholder: 'Current location' },
    reference_code: { label: 'Reference Code', type: 'text', placeholder: 'REF-12345' },
    otp: { label: 'OTP Code', type: 'text', placeholder: '1234' },
};

const handleSubmit = async () => {
    validationErrors.value = {};
    
    // Prepare stored data for multi-step flow
    const storedData = {
        mobile: form.value.mobile,
        country: form.value.country,
        secret: form.value.secret || undefined,
        bank_code: form.value.bank_code || undefined,
        account_number: form.value.account_number || undefined,
        inputs: form.value.inputs,
        required_inputs: voucherInfo.value?.required_inputs || [],
    };
    
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
    
    // Otherwise, proceed with redemption directly
    try {
        const result = await redeemVoucher({
            code: props.voucher_code,
            mobile: form.value.mobile,
            country: form.value.country,
            secret: form.value.secret || undefined,
            bank_code: form.value.bank_code || undefined,
            account_number: form.value.account_number || undefined,
            inputs: Object.keys(form.value.inputs).length > 0 ? form.value.inputs : undefined,
        });

        // Navigate to success page with result
        router.visit(`/redeem/${result.voucher.code}/success`, {
            method: 'get',
            data: {
                amount: result.voucher.amount,
                currency: result.voucher.currency,
                mobile: form.value.mobile,
                message: result.message,
                rider: result.rider,
            },
        });
    } catch (err: any) {
        // Handle validation errors
        if (err.response?.data?.errors) {
            validationErrors.value = err.response.data.errors;
        }
    }
};

onMounted(async () => {
    try {
        const result = await validateVoucher(props.voucher_code);
        voucherInfo.value = result;

        if (!result.can_redeem) {
            router.visit('/redeem');
        }

        // Initialize inputs with empty values for required fields (excluding signature, location, selfie)
        if (result.required_inputs && result.required_inputs.length > 0) {
            result.required_inputs
                .filter(field => field !== 'signature' && field !== 'location' && field !== 'selfie')
                .forEach((field) => {
                    form.value.inputs[field] = '';
                });
        }
    } catch (err) {
        router.visit('/redeem');
    }
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
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
                <Card class="mb-6">
                    <CardHeader>
                        <CardTitle>Voucher Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Code:</span>
                            <span class="font-mono font-semibold">{{ voucherInfo.voucher.code }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Amount:</span>
                            <span class="text-lg font-bold text-green-600">{{ formattedAmount }}</span>
                        </div>
                        <div v-if="voucherInfo.voucher.expires_at" class="flex justify-between">
                            <span class="text-muted-foreground">Expires:</span>
                            <span>{{ new Date(voucherInfo.voucher.expires_at).toLocaleDateString() }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Wallet Form Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Contact & Payment Details</CardTitle>
                        <CardDescription>
                            Provide your mobile number and bank account to receive the cash
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Mobile Number -->
                            <div class="space-y-2">
                                <Label for="mobile">Mobile Number *</Label>
                                <Input
                                    id="mobile"
                                    v-model="form.mobile"
                                    type="tel"
                                    placeholder="+63 917 123 4567"
                                    required
                                    :disabled="loading"
                                />
                                <p v-if="validationErrors.mobile" class="text-sm text-red-600">
                                    {{ validationErrors.mobile }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Philippine mobile number format: +63 9XX XXX XXXX
                                </p>
                            </div>

                            <!-- Secret (if required) -->
                            <div v-if="hasSecret" class="space-y-2">
                                <Label for="secret">Secret Code *</Label>
                                <Input
                                    id="secret"
                                    v-model="form.secret"
                                    type="password"
                                    placeholder="Enter secret code"
                                    :required="hasSecret"
                                    :disabled="loading"
                                />
                                <p v-if="validationErrors.secret" class="text-sm text-red-600">
                                    {{ validationErrors.secret }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    This voucher requires a secret code to redeem
                                </p>
                            </div>

                            <!-- Dynamic Input Fields -->
                            <div
                                v-for="field in requiredInputs"
                                :key="field"
                                class="space-y-2"
                            >
                                <Label :for="field">
                                    {{ fieldConfig[field]?.label || field.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ') }} *
                                </Label>
                                <Input
                                    :id="field"
                                    v-model="form.inputs[field]"
                                    :type="fieldConfig[field]?.type || 'text'"
                                    :placeholder="fieldConfig[field]?.placeholder"
                                    required
                                    :disabled="loading"
                                />
                                <p v-if="validationErrors[`inputs.${field}`]" class="text-sm text-red-600">
                                    {{ validationErrors[`inputs.${field}`] }}
                                </p>
                            </div>

                            <!-- Bank Selection -->
                            <div class="space-y-2">
                                <Label for="bank">Bank (Optional)</Label>
                                <select
                                    id="bank"
                                    v-model="form.bank_code"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="loading"
                                >
                                    <option value="">Select a bank</option>
                                    <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                                        {{ bank.name }}
                                    </option>
                                </select>
                                <p v-if="validationErrors.bank_code" class="text-sm text-red-600">
                                    {{ validationErrors.bank_code }}
                                </p>
                            </div>

                            <!-- Account Number -->
                            <div v-if="form.bank_code" class="space-y-2">
                                <Label for="account_number">Account Number *</Label>
                                <Input
                                    id="account_number"
                                    v-model="form.account_number"
                                    type="text"
                                    placeholder="Enter account number"
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
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    class="flex-1"
                                    :disabled="loading"
                                >
                                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                                    {{ loading ? 'Processing...' : 'Redeem Voucher' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </template>
        </div>
    </PublicLayout>
</template>
