<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head, router } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowUpCircle, QrCode, Receipt, TrendingUp, TrendingDown, Activity } from 'lucide-vue-next';
import { useWalletBalance } from '@/composables/useWalletBalance';

interface Transaction {
    id: number;
    type: string;
    amount: number;
    confirmed: boolean;
    created_at: string;
    meta: Record<string, any>;
}

interface Props {
    balance: number;
    recentTransactions: Transaction[];
    stats: {
        total_loaded: number;
        total_spent: number;
        transaction_count: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },
];

const { formattedBalance, realtimeNote, realtimeTime } = useWalletBalance();

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatRelativeTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    
    return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
};

const getTransactionLabel = (tx: Transaction) => {
    if (tx.type === 'deposit') return 'Top Up';
    if (tx.type === 'withdraw') return 'Voucher Generation';
    return tx.type.charAt(0).toUpperCase() + tx.type.slice(1);
};

const getTransactionColor = (amount: number) => {
    return amount >= 0 ? 'text-green-600' : 'text-red-600';
};
</script>

<template>
    <Head title="Wallet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <Heading title="Wallet" description="Manage your balance and transactions" />
            </div>

            <!-- Balance Card & Quick Actions -->
            <Card class="overflow-hidden">
                <CardHeader class="bg-gradient-to-br from-primary/10 to-primary/5">
                    <CardDescription>Current Balance</CardDescription>
                    <CardTitle class="text-5xl font-bold tracking-tight">
                        {{ formattedBalance }}
                    </CardTitle>
                    <p v-if="realtimeNote" class="text-sm text-muted-foreground mt-2">
                        {{ realtimeNote }} • {{ realtimeTime }}
                    </p>
                </CardHeader>
                <CardContent class="pt-6">
                    <!-- Quick Actions -->
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Button
                            variant="outline"
                            class="h-auto flex-col gap-2 py-4"
                            @click="router.visit('/topup')"
                        >
                            <ArrowUpCircle class="h-6 w-6" />
                            <span class="font-medium">Top Up</span>
                        </Button>
                        <Button
                            variant="outline"
                            class="h-auto flex-col gap-2 py-4"
                            @click="router.visit('/wallet/qr')"
                        >
                            <QrCode class="h-6 w-6" />
                            <span class="font-medium">Generate QR</span>
                        </Button>
                        <Button
                            variant="outline"
                            class="h-auto flex-col gap-2 py-4"
                            @click="router.visit('/transactions')"
                        >
                            <Receipt class="h-6 w-6" />
                            <span class="font-medium">All Transactions</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Stats Grid -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Loaded</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(stats.total_loaded) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            All-time top-ups
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Spent</CardTitle>
                        <TrendingDown class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(stats.total_spent) }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Voucher generation costs
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Transactions</CardTitle>
                        <Activity class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.transaction_count }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Total activity count
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Transactions -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Recent Transactions</CardTitle>
                            <CardDescription>Your latest wallet activity</CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" @click="router.visit('/transactions')">
                            View all
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="recentTransactions.length > 0" class="space-y-4">
                        <div
                            v-for="tx in recentTransactions"
                            :key="tx.id"
                            class="flex items-center justify-between border-b last:border-0 pb-4 last:pb-0"
                        >
                            <div class="space-y-1">
                                <p class="font-medium">{{ getTransactionLabel(tx) }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatRelativeTime(tx.created_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p :class="['font-semibold', getTransactionColor(tx.amount)]">
                                    {{ tx.amount >= 0 ? '+' : '' }}{{ formatCurrency(tx.amount) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ tx.confirmed ? 'Confirmed' : 'Pending' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground">
                        <Activity class="h-12 w-12 mx-auto mb-3 opacity-20" />
                        <p>No transactions yet</p>
                        <p class="text-sm mt-1">Start by topping up your wallet</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
