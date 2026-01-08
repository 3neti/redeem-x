<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import VoucherPaymentCard from '@/components/VoucherPaymentCard.vue';
import type { BreadcrumbItem } from '@/types';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/toast/use-toast';
import { Wallet, CreditCard, ArrowUpRight, Clock, CheckCircle2, XCircle, ShieldAlert } from 'lucide-vue-next';

interface TopUpData {
    reference_no: string;
    amount: number;
    status: string;
    gateway: string;
    institution_code?: string;
    created_at: string;
}

interface Props {
    balance: number;
    recentTopUps: TopUpData[];
    pendingTopUps: TopUpData[];
    isSuperAdmin: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet/load' },
    { title: 'Top-Up', href: '/topup' },
];

const { toast } = useToast();

// Form state
const amount = ref<number | null>(null);
const gateway = ref('netbank');
const institutionCode = ref<string>('');
const loading = ref(false);
const error = ref<string | null>(null);

// Quick amount buttons
const quickAmounts = [100, 500, 1000, 2500, 5000];

const formattedBalance = computed(() => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(props.balance);
});

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

const getStatusIcon = (status: string | undefined) => {
    if (!status) return Clock;
    switch (status.toUpperCase()) {
        case 'PAID':
            return CheckCircle2;
        case 'PENDING':
            return Clock;
        case 'FAILED':
        case 'EXPIRED':
            return XCircle;
        default:
            return Clock;
    }
};

const getStatusVariant = (status: string | undefined) => {
    if (!status) return 'outline';
    switch (status.toUpperCase()) {
        case 'PAID':
            return 'default';
        case 'PENDING':
            return 'secondary';
        case 'FAILED':
        case 'EXPIRED':
            return 'destructive';
        default:
            return 'outline';
    }
};

const setQuickAmount = (amt: number) => {
    amount.value = amt;
};

const handleSubmit = async () => {
    if (!amount.value || amount.value < 1) {
        error.value = 'Please enter a valid amount';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.post('/topup', {
            amount: amount.value,
            gateway: gateway.value,
            institution_code: institutionCode.value || null,
        });

        if (data.success && data.redirect_url) {
            // Redirect to payment gateway
            window.location.href = data.redirect_url;
        } else {
            error.value = data.message || 'Failed to initiate top-up';
            toast({
                title: 'Error',
                description: error.value,
                variant: 'destructive',
            });
        }
    } catch (e: any) {
        error.value = e.response?.data?.message || e.message || 'An error occurred';
        toast({
            title: 'Error',
            description: error.value,
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Top-Up Wallet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <Heading
                    title="Top-Up Wallet"
                    :description="`Current Balance: ${formattedBalance}`"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Left Column: Voucher Payment (Always Visible) -->
                <div class="space-y-6">
                    <VoucherPaymentCard />
                </div>

                <!-- Right Column: Bank Top-Up (Super-Admin Only) -->
                <div class="space-y-6">
                    <Card v-if="isSuperAdmin">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="flex items-center gap-2">
                                        <CreditCard class="h-5 w-5" />
                                        Add Funds
                                        <Badge variant="secondary" class="ml-2">Admin Only</Badge>
                                    </CardTitle>
                                    <CardDescription>
                                        Top-up your wallet using NetBank Direct Checkout
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Amount Input -->
                            <div class="space-y-2">
                                <Label for="amount">Amount (PHP)</Label>
                                <Input
                                    id="amount"
                                    v-model.number="amount"
                                    type="number"
                                    placeholder="Enter amount"
                                    min="1"
                                    max="50000"
                                    step="1"
                                />
                            </div>

                            <!-- Quick Amount Buttons -->
                            <div class="space-y-2">
                                <Label>Quick Amounts</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="amt in quickAmounts"
                                        :key="amt"
                                        variant="outline"
                                        size="sm"
                                        @click="setQuickAmount(amt)"
                                    >
                                        {{ formatAmount(amt) }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Institution Selection (Optional) -->
                            <div class="space-y-2">
                                <Label for="institution">Preferred Payment Method (Optional)</Label>
                                <select
                                    id="institution"
                                    v-model="institutionCode"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Any</option>
                                    <option value="GCASH">GCash</option>
                                    <option value="MAYA">Maya</option>
                                    <option value="BDO">BDO</option>
                                    <option value="BPI">BPI</option>
                                </select>
                            </div>

                            <!-- Error Alert -->
                            <Alert v-if="error" variant="destructive">
                                <AlertDescription>{{ error }}</AlertDescription>
                            </Alert>

                            <!-- Submit Button -->
                            <Button
                                class="w-full"
                                :disabled="loading || !amount || amount < 1"
                                @click="handleSubmit"
                            >
                                <ArrowUpRight v-if="!loading" class="mr-2 h-4 w-4" />
                                {{ loading ? 'Processing...' : 'Proceed to Payment' }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Restricted Access Message (Non-Super-Admins) -->
                    <Card v-else>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <ShieldAlert class="h-5 w-5 text-muted-foreground" />
                                Bank Top-Up Restricted
                            </CardTitle>
                            <CardDescription>
                                This feature is only available to administrators
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Alert>
                                <AlertDescription>
                                    Bank-based top-ups are restricted to administrators. Please use voucher payments or contact support for assistance.
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Recent & Pending Top-Ups Section -->
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-6">
                    <!-- Pending Top-Ups -->
                    <Card v-if="pendingTopUps.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="h-5 w-5" />
                                Pending Payments
                            </CardTitle>
                            <CardDescription>
                                Awaiting payment confirmation
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="topUp in pendingTopUps"
                                    :key="topUp.reference_no"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div>
                                        <p class="font-medium">{{ formatAmount(topUp.amount) }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ topUp.reference_no }}
                                        </p>
                                    </div>
                                    <Badge :variant="getStatusVariant(topUp.status)">
                                        {{ topUp.status }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Top-Ups -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Wallet class="h-5 w-5" />
                                Recent Top-Ups
                            </CardTitle>
                            <CardDescription>
                                Your recent wallet top-ups
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentTopUps.length === 0" class="text-center py-8 text-muted-foreground">
                                No top-ups yet
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="topUp in recentTopUps"
                                    :key="topUp.reference_no"
                                    class="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <component
                                            :is="getStatusIcon(topUp.status)"
                                            class="h-5 w-5 text-muted-foreground"
                                        />
                                        <div>
                                            <p class="font-medium">{{ formatAmount(topUp.amount) }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ formatDate(topUp.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge :variant="getStatusVariant(topUp.status)">
                                        {{ topUp.status }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
