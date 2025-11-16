<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import type { BreadcrumbItem } from '@/types';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, Clock, XCircle, Loader2, ArrowLeft } from 'lucide-vue-next';

interface TopUpData {
    reference_no: string;
    amount: number;
    status: string;
    gateway: string;
    institution_code?: string;
    created_at: string;
}

interface Props {
    topUp: TopUpData;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet/load' },
    { title: 'Top-Up', href: '/topup' },
    { title: 'Confirmation', href: '#' },
];

const status = ref(props.topUp.status);
const pollInterval = ref<number | null>(null);

const formatAmount = (amt: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amt);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusConfig = (st: string) => {
    switch (st.toUpperCase()) {
        case 'PAID':
            return {
                icon: CheckCircle2,
                variant: 'default' as const,
                title: 'Payment Successful!',
                description: 'Your wallet has been topped up successfully.',
                alertVariant: 'default' as const,
            };
        case 'PENDING':
            return {
                icon: Clock,
                variant: 'secondary' as const,
                title: 'Payment Pending',
                description: 'Waiting for payment confirmation. This usually takes a few moments.',
                alertVariant: 'default' as const,
            };
        case 'FAILED':
        case 'EXPIRED':
            return {
                icon: XCircle,
                variant: 'destructive' as const,
                title: 'Payment Failed',
                description: 'Your payment could not be processed. Please try again.',
                alertVariant: 'destructive' as const,
            };
        default:
            return {
                icon: Loader2,
                variant: 'secondary' as const,
                title: 'Processing...',
                description: 'Please wait while we process your payment.',
                alertVariant: 'default' as const,
            };
    }
};

const checkStatus = async () => {
    try {
        const response = await fetch(`/topup/status/${props.topUp.reference_no}`);
        const data = await response.json();
        
        if (data.status && data.status !== status.value) {
            status.value = data.status;
            
            // Stop polling if status is final
            if (['PAID', 'FAILED', 'EXPIRED'].includes(data.status.toUpperCase())) {
                stopPolling();
            }
        }
    } catch (e) {
        console.error('Failed to check status:', e);
    }
};

const startPolling = () => {
    // Poll every 3 seconds
    pollInterval.value = window.setInterval(checkStatus, 3000);
};

const stopPolling = () => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
};

const goBackToTopUp = () => {
    router.visit('/topup');
};

onMounted(() => {
    // Start polling if status is pending
    if (status.value.toUpperCase() === 'PENDING') {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

const config = getStatusConfig(status.value);
</script>

<template>
    <Head title="Payment Confirmation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-6 p-6">
            <!-- Page Header -->
            <Heading title="Payment Confirmation" description="Top-up status" />

            <div class="space-y-6">
                <!-- Status Alert -->
                <Alert :variant="config.alertVariant">
                    <component :is="config.icon" class="h-5 w-5" :class="{ 'animate-spin': status.toUpperCase() === 'PENDING' }" />
                    <AlertTitle>{{ config.title }}</AlertTitle>
                    <AlertDescription>{{ config.description }}</AlertDescription>
                </Alert>

                <!-- Transaction Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Details</CardTitle>
                        <CardDescription>
                            Reference: {{ topUp.reference_no }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Amount -->
                        <div class="flex items-center justify-between border-b pb-3">
                            <span class="text-sm font-medium">Amount</span>
                            <span class="text-2xl font-bold">{{ formatAmount(topUp.amount) }}</span>
                        </div>

                        <!-- Status -->
                        <div class="flex items-center justify-between border-b pb-3">
                            <span class="text-sm font-medium">Status</span>
                            <Badge :variant="config.variant">
                                {{ status }}
                            </Badge>
                        </div>

                        <!-- Gateway -->
                        <div class="flex items-center justify-between border-b pb-3">
                            <span class="text-sm font-medium">Payment Gateway</span>
                            <span class="text-sm">{{ topUp.gateway.toUpperCase() }}</span>
                        </div>

                        <!-- Institution -->
                        <div v-if="topUp.institution_code" class="flex items-center justify-between border-b pb-3">
                            <span class="text-sm font-medium">Payment Method</span>
                            <span class="text-sm">{{ topUp.institution_code }}</span>
                        </div>

                        <!-- Date -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Initiated</span>
                            <span class="text-sm text-muted-foreground">{{ formatDate(topUp.created_at) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex gap-4">
                    <Button variant="outline" class="flex-1" @click="goBackToTopUp">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Top-Up
                    </Button>
                    <Button v-if="status.toUpperCase() === 'PAID'" class="flex-1" @click="router.visit('/dashboard')">
                        Go to Dashboard
                    </Button>
                </div>

                <!-- Polling Indicator -->
                <div v-if="status.toUpperCase() === 'PENDING'" class="text-center text-sm text-muted-foreground">
                    <Loader2 class="inline-block h-4 w-4 animate-spin mr-2" />
                    Checking payment status...
                </div>
            </div>
        </div>
    </AppLayout>
</template>
