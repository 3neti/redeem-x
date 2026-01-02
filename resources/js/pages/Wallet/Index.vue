<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Head, router } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ArrowUpCircle, QrCode, Receipt, TrendingUp, TrendingDown, Activity, ChevronDown, Ticket } from 'lucide-vue-next';
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
    if (tx.type === 'deposit') {
        // Use new payment_method metadata if available
        if (tx.meta?.payment_method) {
            return tx.meta.payment_method;
        }
        // Fallback to legacy type detection
        if (tx.meta?.type === 'voucher_payment' || tx.meta?.deposit_type === 'voucher_payment') {
            return 'Voucher Payment';
        }
        return 'Bank Top-Up';
    }
    
    if (tx.type === 'withdraw') {
        // Show specific charge category if available in meta
        if (tx.meta?.title) {
            return tx.meta.title;
        }
        return 'Voucher Generation';
    }
    
    return tx.type.charAt(0).toUpperCase() + tx.type.slice(1);
};

const getTransactionDescription = (tx: Transaction) => {
    // Show sender info for deposits
    if (tx.type === 'deposit') {
        if (tx.meta?.sender_name && tx.meta?.sender_identifier) {
            return `From: ${tx.meta.sender_name} (${tx.meta.sender_identifier})`;
        }
        if (tx.meta?.sender_name) {
            return `From: ${tx.meta.sender_name}`;
        }
        // Legacy: Show voucher code for voucher payments
        if (tx.meta?.voucher_code) {
            return `Code: ${tx.meta.voucher_code}`;
        }
    }
    
    if (tx.type === 'withdraw' && tx.meta?.description) {
        return tx.meta.description;
    }
    return null;
};

// Group transactions by type and timestamp
// Voucher generation charges from the same batch will have the same created_at
const groupedTransactions = computed(() => {
    const groups: Array<{
        key: string;
        timestamp: string;
        type: string;
        isGroup: boolean;
        transactions: Transaction[];
        totalAmount: number;
    }> = [];
    
    const txByTimestamp = new Map<string, Transaction[]>();
    
    // Group withdraw transactions by timestamp
    for (const tx of props.recentTransactions) {
        if (tx.type === 'withdraw') {
            const key = tx.created_at;
            if (!txByTimestamp.has(key)) {
                txByTimestamp.set(key, []);
            }
            txByTimestamp.get(key)!.push(tx);
        } else {
            // Non-withdraw transactions are not grouped
            groups.push({
                key: `single-${tx.id}`,
                timestamp: tx.created_at,
                type: tx.type,
                isGroup: false,
                transactions: [tx],
                totalAmount: tx.amount,
            });
        }
    }
    
    // Add grouped withdraw transactions
    // Note: We always group withdraw transactions (voucher generations) for consistency
    // Even single charges are shown as groups for uniform UX
    for (const [timestamp, transactions] of txByTimestamp.entries()) {
        const totalAmount = transactions.reduce((sum, tx) => sum + (Number(tx.amount) || 0), 0);
        groups.push({
            key: `group-${timestamp}`,
            timestamp,
            type: 'withdraw',
            isGroup: true, // Always show as group for voucher generations
            transactions,
            totalAmount,
        });
    }
    
    // Sort by timestamp descending
    groups.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());
    
    return groups;
});

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
                    <div v-if="groupedTransactions.length > 0" class="space-y-3">
                        <div v-for="group in groupedTransactions.slice(0, 10)" :key="group.key">
                            <!-- Grouped voucher generation (expandable) -->
                            <Collapsible v-if="group.isGroup" class="border rounded-lg">
                                <div class="flex items-center justify-between p-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <Ticket class="h-4 w-4 text-muted-foreground" />
                                            <p class="font-medium">Voucher Generation</p>
                                        </div>
                                        <p class="text-xs text-muted-foreground mt-1">
                                            {{ group.transactions.length }} charges • {{ formatRelativeTime(group.timestamp) }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <p :class="['font-semibold', getTransactionColor(group.totalAmount)]">
                                                {{ formatCurrency(group.totalAmount) }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ group.transactions[0].confirmed ? 'Confirmed' : 'Pending' }}
                                            </p>
                                        </div>
                                        <CollapsibleTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <ChevronDown class="h-4 w-4" />
                                            </Button>
                                        </CollapsibleTrigger>
                                    </div>
                                </div>
                                <CollapsibleContent>
                                    <div class="border-t bg-muted/30 p-3 space-y-2">
                                        <p class="text-xs font-medium text-muted-foreground uppercase">Charge Breakdown</p>
                                        <div
                                            v-for="tx in group.transactions"
                                            :key="tx.id"
                                            class="flex items-center justify-between text-sm py-1"
                                        >
                                            <div>
                                                <p class="font-medium">{{ getTransactionLabel(tx) }}</p>
                                                <p v-if="getTransactionDescription(tx)" class="text-xs text-muted-foreground">
                                                    {{ getTransactionDescription(tx) }}
                                                </p>
                                            </div>
                                            <p class="font-mono text-xs">{{ formatCurrency(tx.amount) }}</p>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Single transaction (deposits, single charges) -->
                            <div v-else class="flex items-center justify-between border-b last:border-0 pb-3 last:pb-0">
                                <div class="space-y-1">
                                    <p class="font-medium">{{ getTransactionLabel(group.transactions[0]) }}</p>
                                    <p v-if="getTransactionDescription(group.transactions[0])" class="text-xs text-muted-foreground">
                                        {{ getTransactionDescription(group.transactions[0]) }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ formatRelativeTime(group.timestamp) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p :class="['font-semibold', getTransactionColor(group.totalAmount)]">
                                        {{ group.totalAmount >= 0 ? '+' : '' }}{{ formatCurrency(group.totalAmount) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ group.transactions[0].confirmed ? 'Confirmed' : 'Pending' }}
                                    </p>
                                </div>
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
