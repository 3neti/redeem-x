<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, Loader2, AlertCircle } from 'lucide-vue-next';

interface Props {
    voucher_code: string;
}

const props = defineProps<Props>();

const finalizationData = ref<any>(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const formattedAmount = computed(() => {
    if (!finalizationData.value?.voucher) return '';
    const { amount, currency } = finalizationData.value.voucher;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount || 0);
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

        // POST to confirm endpoint
        const response = await fetch('/api/v1/redeem/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to confirm redemption');
        }

        const result = await response.json();

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
        console.error('Confirmation failed:', err);
    }
};

const fetchFinalizationData = async () => {
    try {
        loading.value = true;
        error.value = null;

        // Get data from sessionStorage (populated by wallet, inputs, location, selfie, signature pages)
        const storedData = sessionStorage.getItem(`redeem_${props.voucher_code}`);
        if (!storedData) {
            throw new Error('No redemption data found. Please start the process again.');
        }

        const sessionData = JSON.parse(storedData);

        // Verify voucher exists and is valid via API
        const response = await fetch(`/api/v1/redeem/finalize?voucher_code=${props.voucher_code}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to load finalization data');
        }

        const result = await response.json();
        
        if (!result.success) {
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
    } catch (err: any) {
        error.value = err.message || 'Failed to load finalization data. Please try again.';
        console.error('Finalization fetch failed:', err);
    } finally {
        loading.value = false;
    }
};

const formatBankAccount = (bankCode: string | undefined, accountNumber: string | undefined): string | null => {
    if (!bankCode || !accountNumber) return null;
    // Just return formatted string - can be enhanced with bank registry later
    return `${bankCode} (${accountNumber})`;
};

const handleBack = () => {
    router.visit(`/redeem/${props.voucher_code}/wallet`);
};

onMounted(() => {
    // Fetch finalization data
    fetchFinalizationData();
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <!-- Loading State -->
            <div v-if="loading" class="flex min-h-[50vh] items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <!-- Error Alert -->
            <Alert v-if="error" variant="destructive" class="mb-6">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ error }}
                </AlertDescription>
            </Alert>

            <!-- Finalization Review Card -->
            <Card v-if="!loading && finalizationData">
                <CardHeader>
                    <CardTitle>Review & Confirm</CardTitle>
                    <CardDescription>
                        Please review your information before confirming redemption
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Voucher Amount -->
                    <div class="rounded-lg bg-green-50 p-4 text-center">
                        <div class="text-sm text-muted-foreground">You will receive</div>
                        <div class="text-3xl font-bold text-green-600">
                            {{ formattedAmount }}
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-3">
                        <h3 class="font-semibold">Contact Information</h3>
                        <div class="rounded-md border p-4">
                            <div class="flex justify-between py-2">
                                <span class="text-muted-foreground">Mobile Number:</span>
                                <span class="font-medium">{{ finalizationData.mobile }}</span>
                            </div>
                            <div v-if="finalizationData.bank_account" class="flex justify-between border-t py-2">
                                <span class="text-muted-foreground">Bank Account:</span>
                                <span class="font-medium">{{ finalizationData.bank_account }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div v-if="Object.keys(finalizationData.inputs || {}).length > 0" class="space-y-3">
                        <h3 class="font-semibold">Additional Information</h3>
                        <div class="rounded-md border p-4">
                            <div
                                v-for="(value, key) in finalizationData.inputs"
                                :key="key"
                                class="flex justify-between border-b py-2 last:border-b-0"
                            >
                                <span class="text-muted-foreground">
                                    {{ formatFieldName(String(key)) }}:
                                </span>
                                <span class="max-w-[60%] truncate font-medium" :title="String(value)">
                                    {{ typeof value === 'string' && value.startsWith('data:') ? '(image)' : value }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Status -->
                    <div v-if="finalizationData.has_signature" class="flex items-center gap-2 text-green-600">
                        <CheckCircle :size="20" />
                        <span class="font-medium">Signature captured</span>
                    </div>

                    <!-- Confirmation Notice -->
                    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                        <p class="font-semibold">Important:</p>
                        <p class="mt-1">
                            By confirming, you agree that the information provided is accurate. The
                            cash will be transferred to the provided account or mobile number.
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            @click="handleBack"
                            :disabled="submitting"
                        >
                            Back
                        </Button>
                        <Button
                            type="button"
                            class="flex-1"
                            @click="handleConfirm"
                            :disabled="submitting"
                        >
                            <Loader2 v-if="submitting" class="h-4 w-4 mr-2 animate-spin" />
                            {{ submitting ? 'Processing...' : 'Confirm Redemption' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
