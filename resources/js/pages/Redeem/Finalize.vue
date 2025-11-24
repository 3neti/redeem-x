<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, Loader2, AlertCircle, Copy, CheckCircle2, ShieldCheck, Clock } from 'lucide-vue-next';

interface Props {
    voucher_code: string;
    config: any;
    kyc?: {
        required: boolean;
        completed: boolean;
        status: string | null;
    };
    voucher_processing?: boolean;
}

const props = defineProps<Props>();
const isDev = import.meta.env.DEV;

const finalizationData = ref<any>(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);
const retryCountdown = ref(0);

const formattedAmount = computed(() => {
    if (!finalizationData.value?.voucher) return '';
    const { amount, currency } = finalizationData.value.voucher;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount || 0);
});

// Process inputs to handle special data types
const processedInputs = computed(() => {
    const inputs = finalizationData.value?.inputs || {};
    const processed: Record<string, any> = {};
    
    for (const [key, value] of Object.entries(inputs)) {
        if (key === 'location' && typeof value === 'string') {
            // Parse location JSON string
            try {
                const locationData = JSON.parse(value as string);
                processed[key] = locationData.address?.formatted || 'Location captured';
            } catch (e) {
                processed[key] = 'Location captured';
            }
        } else if (key === 'selfie' || key === 'signature') {
            // Skip image fields - they're displayed separately
            continue;
        } else {
            processed[key] = value;
        }
    }
    
    return processed;
});

// Captured items for display
const capturedItems = computed(() => {
    const items = [];
    const inputs = finalizationData.value?.inputs || {};
    
    if (inputs.location) {
        items.push({ type: 'location', label: 'Location' });
    }
    if (inputs.selfie) {
        items.push({ type: 'selfie', label: 'Selfie' });
    }
    if (inputs.signature || finalizationData.value?.has_signature) {
        items.push({ type: 'signature', label: 'Signature' });
    }
    
    return items;
});

// Helper for showing captured items row
const capturedItemsText = computed(() => {
    return capturedItems.value.length > 0;
});

const formatFieldName = (field: string): string => {
    return field
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const handleConfirm = async () => {
    try {
        submitting.value = true;
        error.value = null;

        // Prepare confirmation data
        const data = {
            voucher_code: props.voucher_code,
            mobile: finalizationData.value.mobile,
            country: finalizationData.value.country,
            bank_code: finalizationData.value.bank_code,
            account_number: finalizationData.value.account_number,
            inputs: finalizationData.value.inputs,
        };

        if (isDev) {
            console.log('[Finalize] handleConfirm - Starting confirmation');
            console.log('[Finalize] handleConfirm - Request data:', JSON.stringify(data, null, 2));
            console.log('[Finalize] handleConfirm - Voucher code:', props.voucher_code);
            console.log('[Finalize] handleConfirm - Mobile:', finalizationData.value.mobile);
            console.log('[Finalize] handleConfirm - Bank code:', finalizationData.value.bank_code);
            console.log('[Finalize] handleConfirm - Account number:', finalizationData.value.account_number);
            console.log('[Finalize] handleConfirm - Inputs:', finalizationData.value.inputs);
        }

        // POST to confirm endpoint
        const response = await fetch('/api/v1/redeem/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (isDev) {
            console.log('[Finalize] handleConfirm - Response status:', response.status);
            console.log('[Finalize] handleConfirm - Response ok:', response.ok);
            console.log('[Finalize] handleConfirm - Response headers:', Object.fromEntries(response.headers.entries()));
        }

        if (!response.ok) {
            if (isDev) console.error('[Finalize] handleConfirm - Response not ok');
            let errorData;
            try {
                errorData = await response.json();
                if (isDev) console.error('[Finalize] handleConfirm - Error data:', errorData);
            } catch (parseError) {
                if (isDev) {
                    console.error('[Finalize] handleConfirm - Failed to parse error response:', parseError);
                }
                const responseText = await response.text();
                if (isDev) console.error('[Finalize] handleConfirm - Raw error response:', responseText);
                throw new Error(`Server returned ${response.status}: ${responseText}`);
            }
            throw new Error(errorData.message || 'Failed to confirm redemption');
        }

        const result = await response.json();
        if (isDev) console.log('[Finalize] handleConfirm - Success result:', result);

        // Clear session data
        sessionStorage.removeItem(`redeem_${props.voucher_code}`);

        // Navigate to success
        router.visit(`/redeem/${props.voucher_code}/success`, {
            method: 'get',
            data: {
                amount: result.data?.voucher?.amount,
                currency: result.data?.voucher?.currency,
                mobile: finalizationData.value.mobile,
                message: result.data?.message,
                rider: result.data?.rider,
            },
        });
    } catch (err: any) {
        submitting.value = false;
        error.value = err.message || 'Failed to confirm redemption. Please try again.';
        if (isDev) {
            console.error('[Finalize] handleConfirm - Confirmation failed');
            console.error('[Finalize] handleConfirm - Error object:', err);
            console.error('[Finalize] handleConfirm - Error message:', err.message);
            console.error('[Finalize] handleConfirm - Error stack:', err.stack);
        }
    }
};

const fetchFinalizationData = async () => {
    try {
        loading.value = true;
        error.value = null;

        if (isDev) {
            console.log('[Finalize] fetchFinalizationData - Starting');
            console.log('[Finalize] fetchFinalizationData - Voucher code:', props.voucher_code);
        }

        // Get data from sessionStorage (populated by wallet, inputs, location, selfie, signature pages)
        const storedData = sessionStorage.getItem(`redeem_${props.voucher_code}`);
        if (isDev) console.log('[Finalize] fetchFinalizationData - storedData:', storedData);
        
        if (!storedData) {
            if (isDev) console.error('[Finalize] fetchFinalizationData - No stored data found');
            throw new Error('No redemption data found. Please start the process again.');
        }

        const sessionData = JSON.parse(storedData);
        if (isDev) console.log('[Finalize] fetchFinalizationData - Parsed sessionData:', sessionData);

        // Verify voucher exists and is valid via API
        if (isDev) console.log('[Finalize] fetchFinalizationData - Calling finalize API');
        const response = await fetch(`/api/v1/redeem/finalize?voucher_code=${props.voucher_code}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (isDev) {
            console.log('[Finalize] fetchFinalizationData - API response status:', response.status);
            console.log('[Finalize] fetchFinalizationData - API response ok:', response.ok);
        }

        if (!response.ok) {
            const errorData = await response.json();
            if (isDev) console.error('[Finalize] fetchFinalizationData - API error:', errorData);
            throw new Error(errorData.message || 'Failed to load finalization data');
        }

        const result = await response.json();
        if (isDev) console.log('[Finalize] fetchFinalizationData - API result:', result);
        
        if (!result.success) {
            if (isDev) console.error('[Finalize] fetchFinalizationData - API returned success=false');
            throw new Error(result.message || 'Failed to load finalization data');
        }

        // Combine API voucher data with sessionStorage data collected from all steps
        finalizationData.value = {
            voucher: result.data.voucher,
            mobile: sessionData.mobile,
            country: sessionData.country,
            bank_code: sessionData.bank_code,
            account_number: sessionData.account_number,
            bank_account: sessionData.bank_account || formatBankAccount(sessionData.bank_code, sessionData.account_number),
            inputs: sessionData.inputs || {},
            has_signature: (sessionData.inputs?.signature) ? true : false,
        };
        
        // Debug logging
        if (isDev) {
            console.log('[Finalize] Complete finalizationData:', finalizationData.value);
            console.log('[Finalize] sessionData from storage:', sessionData);
            console.log('[Finalize] API result:', result.data);
        }
    } catch (err: any) {
        error.value = err.message || 'Failed to load finalization data. Please try again.';
        if (isDev) console.error('Finalization fetch failed:', err);
    } finally {
        loading.value = false;
    }
};

const formatBankAccount = (bankCode: string | undefined, accountNumber: string | undefined): string | null => {
    if (!bankCode || !accountNumber) return null;
    // Just return formatted string - can be enhanced with bank registry later
    return `${bankCode} (${accountNumber})`;
};

const copiedCode = ref(false);
const copiedMobile = ref(false);

const copyCode = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedCode.value = true;
        setTimeout(() => {
            copiedCode.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const copyMobile = async () => {
    try {
        await navigator.clipboard.writeText(finalizationData.value?.mobile || '');
        copiedMobile.value = true;
        setTimeout(() => {
            copiedMobile.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const handleBack = () => {
    router.visit(`/redeem/${props.voucher_code}/wallet`);
};

const startKYC = () => {
    router.visit(`/redeem/${props.voucher_code}/kyc/initiate`);
};

onMounted(() => {
    // Fetch finalization data
    fetchFinalizationData();
    
    // If voucher is processing, show countdown
    if (props.voucher_processing) {
        error.value = 'This voucher is still being prepared. Please wait a moment and try again.';
        startRetryCountdown();
    }
});

const startRetryCountdown = () => {
    retryCountdown.value = 3;
    const interval = setInterval(() => {
        retryCountdown.value--;
        if (retryCountdown.value <= 0) {
            clearInterval(interval);
        }
    }, 1000);
};
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Review Redemption" />
        <div class="w-full max-w-md space-y-6">
            <!-- Loading State -->
            <div v-if="loading" class="flex min-h-[50vh] items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <!-- Error Alert -->
            <Alert v-if="error" :variant="voucher_processing ? 'default' : 'destructive'" :class="{ 'border-blue-200 bg-blue-50': voucher_processing }">
                <Clock v-if="voucher_processing" class="h-4 w-4 text-blue-600" />
                <AlertCircle v-else class="h-4 w-4" />
                <AlertDescription :class="{ 'text-blue-800': voucher_processing }">
                    {{ error }}
                    <div v-if="voucher_processing && retryCountdown > 0" class="mt-2 font-semibold">
                        Retry in {{ retryCountdown }} second{{ retryCountdown !== 1 ? 's' : '' }}...
                    </div>
                    <Button 
                        v-if="voucher_processing && retryCountdown === 0" 
                        @click="handleConfirm" 
                        size="sm" 
                        class="mt-3"
                        :disabled="submitting"
                    >
                        <Loader2 v-if="submitting" class="h-4 w-4 mr-2 animate-spin" />
                        {{ submitting ? 'Retrying...' : 'Retry Now' }}
                    </Button>
                </AlertDescription>
            </Alert>

            <template v-if="!loading && finalizationData">
                <!-- Header -->
                <div v-if="config?.show_header" class="space-y-2">
                    <h1 v-if="config?.header?.show_title" class="text-3xl font-bold">
                        {{ config?.header?.title || 'Review Your Redemption' }}
                    </h1>
                    <p v-if="config?.header?.show_description" class="text-muted-foreground">
                        {{ config?.header?.description || 'Please verify all details before confirming' }}
                    </p>
                </div>

                <!-- Summary Table -->
                <Card v-if="config?.show_summary_table">
                    <CardHeader v-if="config?.summary_table?.show_header">
                        <CardTitle v-if="config?.summary_table?.show_title" class="text-lg">
                            {{ config?.summary_table?.title || 'Redemption Summary' }}
                        </CardTitle>
                        <CardDescription v-if="config?.summary_table?.show_description">
                            {{ config?.summary_table?.description || 'Review the details below' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-6">
                        <table class="w-full">
                            <tbody class="divide-y">
                                <!-- Voucher Code -->
                                <tr v-if="config?.summary_table?.show_voucher_code">
                                    <td class="py-3 text-sm font-medium text-muted-foreground">
                                        {{ config?.summary_table?.voucher_code_label || 'Voucher Code' }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <code class="font-mono font-semibold">{{ finalizationData.voucher.code }}</code>
                                            <Button v-if="config?.summary_table?.show_copy_buttons" variant="ghost" size="sm" @click="copyCode(finalizationData.voucher.code)">
                                                <CheckCircle2 v-if="copiedCode" class="h-4 w-4 text-green-500" />
                                                <Copy v-else class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Amount -->
                                <tr v-if="config?.summary_table?.show_amount">
                                    <td class="py-3 text-sm font-medium text-muted-foreground">
                                        {{ config?.summary_table?.amount_label || 'Amount' }}
                                    </td>
                                    <td class="py-3 text-right text-lg font-bold">
                                        {{ formattedAmount }}
                                    </td>
                                </tr>

                                <!-- Mobile Number -->
                                <tr v-if="config?.summary_table?.show_mobile">
                                    <td class="py-3 text-sm font-medium text-muted-foreground">
                                        {{ config?.summary_table?.mobile_label || 'Mobile Number' }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="font-medium">{{ finalizationData.mobile }}</span>
                                            <Button v-if="config?.summary_table?.show_copy_buttons" variant="ghost" size="sm" @click="copyMobile">
                                                <CheckCircle2 v-if="copiedMobile" class="h-4 w-4 text-green-500" />
                                                <Copy v-else class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Bank Account -->
                                <tr v-if="config?.summary_table?.show_bank_account && finalizationData.bank_account">
                                    <td class="py-3 text-sm font-medium text-muted-foreground">
                                        {{ config?.summary_table?.bank_account_label || 'Bank Account' }}
                                    </td>
                                    <td class="py-3 text-right font-medium">{{ finalizationData.bank_account }}</td>
                                </tr>

                                <!-- Collected Data -->
                                <tr v-if="config?.summary_table?.show_collected_inputs" v-for="(value, key) in processedInputs" :key="key">
                                    <td class="py-3 text-sm font-medium text-muted-foreground">
                                        {{ formatFieldName(String(key)) }}
                                    </td>
                                    <td class="py-3 text-right text-sm">{{ value }}</td>
                                </tr>

                                <!-- Captured Items -->
                                <tr v-if="config?.summary_table?.show_captured_items && capturedItemsText">
                                    <td class="py-3 text-sm font-medium text-muted-foreground">
                                        {{ config?.summary_table?.captured_items_label || 'Captured Items' }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex flex-wrap gap-2 justify-end">
                                            <Badge 
                                                v-for="item in capturedItems" 
                                                :key="item.type" 
                                                variant="secondary" 
                                                class="bg-green-100 text-green-800"
                                            >
                                                <CheckCircle :size="14" class="mr-1" />
                                                {{ item.label }}
                                            </Badge>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <!-- KYC Section (if required) -->
                <Card v-if="kyc?.required" class="border-blue-200">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ShieldCheck class="h-5 w-5" />
                            Identity Verification
                        </CardTitle>
                        <CardDescription>KYC verification is required for this voucher</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!kyc.completed" class="space-y-4">
                            <Alert class="border-blue-200 bg-blue-50">
                                <AlertDescription class="text-blue-800">
                                    You must verify your identity before completing redemption.
                                    This process takes 1-2 minutes.
                                </AlertDescription>
                            </Alert>
                            <Button @click="startKYC" size="lg" class="w-full">
                                Start Identity Verification
                            </Button>
                        </div>
                        <div v-else class="flex items-center gap-2 text-green-600 p-3 rounded-lg bg-green-50">
                            <CheckCircle class="w-5 h-5" />
                            <span class="font-medium">Identity Verified</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Confirmation Notice -->
                <Alert v-if="config?.show_confirmation_notice" class="border-amber-200 bg-amber-50">
                    <AlertCircle class="h-4 w-4 text-amber-600" />
                    <AlertDescription class="text-amber-800">
                        <p v-if="config?.confirmation_notice?.show_title" class="font-semibold">
                            {{ config?.confirmation_notice?.title || 'Important:' }}
                        </p>
                        <p v-if="config?.confirmation_notice?.show_message" class="mt-1">
                            {{ config?.confirmation_notice?.message || 'By confirming, you agree that the information provided is accurate. The cash will be transferred to the provided account or mobile number.' }}
                        </p>
                    </AlertDescription>
                </Alert>

                <!-- Action Buttons -->
                <div v-if="config?.show_action_buttons" class="flex gap-3">
                    <Button
                        v-if="config?.action_buttons?.show_back_button"
                        variant="outline"
                        class="flex-1"
                        @click="handleBack"
                        :disabled="submitting"
                    >
                        {{ config?.action_buttons?.back_button_text || 'Back' }}
                    </Button>
                    <Button
                        v-if="config?.action_buttons?.show_confirm_button"
                        class="flex-1"
                        @click="handleConfirm"
                        :disabled="submitting || (kyc?.required && !kyc?.completed)"
                    >
                        <Loader2 v-if="submitting" class="h-4 w-4 mr-2 animate-spin" />
                        {{ submitting ? (config?.action_buttons?.confirm_button_processing_text || 'Processing...') : (kyc?.required && !kyc?.completed ? 'Complete KYC to Continue' : (config?.action_buttons?.confirm_button_text || 'Confirm Redemption')) }}
                    </Button>
                </div>
            </template>
        </div>
    </div>
</template>
