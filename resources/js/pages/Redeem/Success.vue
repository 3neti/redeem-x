<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, ExternalLink } from 'lucide-vue-next';

interface Props {
    // From API flow
    voucher_code?: string;
    amount?: number;
    currency?: string;
    mobile?: string;
    message?: string;
    // From controller flow
    voucher?: {
        code: string;
        amount: number;
        currency: string;
    };
    rider?: {
        message?: string;
        url?: string;
    };
    redirect_timeout?: number;
}

const props = withDefaults(defineProps<Props>(), {
    redirect_timeout: 10,
    currency: 'PHP',
});

// Computed values that work for both flows
const voucherCode = computed(() => props.voucher_code || props.voucher?.code);
const voucherAmount = computed(() => props.amount || props.voucher?.amount || 0);
const voucherCurrency = computed(() => props.currency || props.voucher?.currency || 'PHP');

const countdown = ref(props.redirect_timeout);
const isRedirecting = ref(false);

const formattedAmount = computed(() => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: voucherCurrency.value,
    }).format(voucherAmount.value);
});

const hasRiderUrl = computed(() => {
    return !!props.rider?.url;
});

const displayMessage = computed(() => {
    return props.rider?.message || props.message || 'Thank you for redeeming your voucher! The cash will be transferred shortly.';
});

const handleRedirect = () => {
    if (!props.rider?.url) return;
    isRedirecting.value = true;
    window.location.href = props.rider.url;
};

onMounted(() => {
    // Start countdown if rider URL exists
    if (hasRiderUrl.value && props.rider.url) {
        const interval = setInterval(() => {
            countdown.value--;

            if (countdown.value <= 0) {
                clearInterval(interval);
                handleRedirect();
            }
        }, 1000);

        // Cleanup on unmount
        return () => clearInterval(interval);
    }
});
</script>

<template>
    <PublicLayout>
        <div class="container mx-auto max-w-2xl px-4 py-8">
            <Card>
                <CardHeader class="text-center">
                    <div class="mb-4 flex justify-center">
                        <div class="rounded-full bg-green-100 p-4">
                            <CheckCircle :size="64" class="text-green-600" />
                        </div>
                    </div>
                    <CardTitle class="text-3xl">Redemption Successful!</CardTitle>
                    <CardDescription class="text-base">
                        Your voucher has been redeemed successfully
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Amount Received -->
                    <div class="rounded-lg bg-green-50 p-6 text-center">
                        <div class="text-sm text-muted-foreground">Amount Received</div>
                        <div class="text-4xl font-bold text-green-600">
                            {{ formattedAmount }}
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="space-y-2">
                        <div class="flex justify-between rounded-md border p-4">
                            <span class="text-muted-foreground">Voucher Code:</span>
                            <span class="font-mono font-semibold">{{ voucherCode }}</span>
                        </div>
                        <div v-if="mobile" class="flex justify-between rounded-md border p-4">
                            <span class="text-muted-foreground">Mobile Number:</span>
                            <span class="font-semibold">{{ mobile }}</span>
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="rounded-md border bg-blue-50 p-4">
                        <p class="text-sm font-medium text-blue-900">{{ displayMessage }}</p>
                    </div>

                    <!-- Redirect Notice -->
                    <div v-if="hasRiderUrl && !isRedirecting" class="space-y-4">
                        <div class="rounded-md border bg-amber-50 p-4 text-center">
                            <p class="text-sm text-amber-800">
                                You will be redirected in
                                <span class="font-bold">{{ countdown }}</span> seconds...
                            </p>
                        </div>
                        <Button class="w-full" @click="handleRedirect">
                            Continue Now
                            <ExternalLink :size="16" class="ml-2" />
                        </Button>
                    </div>

                    <!-- Manual Redirect -->
                    <div v-else-if="hasRiderUrl && isRedirecting" class="text-center">
                        <p class="text-sm text-muted-foreground">Redirecting...</p>
                    </div>

                    <!-- No Redirect - Show Redeem Another -->
                    <div v-else class="pt-4">
                        <Button class="w-full" @click="router.visit('/redeem')">
                            Redeem Another Voucher
                        </Button>
                    </div>

                    <!-- Success Note -->
                    <div class="rounded-md bg-gray-50 p-4 text-center text-sm text-gray-600">
                        <p>
                            The cash has been transferred to your account. You should receive a
                            confirmation message shortly.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>
