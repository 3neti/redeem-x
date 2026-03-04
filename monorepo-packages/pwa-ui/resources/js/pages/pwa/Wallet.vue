<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Wallet, Plus, Activity, TrendingUp, TrendingDown, Ticket, ChevronDown } from 'lucide-vue-next';

interface Transaction {
    id: number;
    type: string;
    amount: number;
    confirmed: boolean;
    created_at: string;
    meta: Record<string, any>;
}

interface Props {
    balance: number | string;
    formattedBalance: string;
    recentTransactions: Transaction[];
}

const props = defineProps<Props>();

const formatAmount = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
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
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const getTransactionLabel = (tx: Transaction) => {
    if (tx.type === 'deposit') {
        if (tx.meta?.payment_method) {
            return tx.meta.payment_method;
        }
        if (tx.meta?.type === 'voucher_payment' || tx.meta?.deposit_type === 'voucher_payment') {
            return 'Voucher Payment';
        }
        return 'Bank Top-Up';
    }
    
    if (tx.type === 'withdraw') {
        if (tx.meta?.title) {
            return tx.meta.title;
        }
        return 'Voucher Generation';
    }
    
    return tx.type.charAt(0).toUpperCase() + tx.type.slice(1);
};

const getTransactionDescription = (tx: Transaction) => {
    if (tx.type === 'deposit') {
        if (tx.meta?.sender_name && tx.meta?.sender_identifier) {
            return `From: ${tx.meta.sender_name} (${tx.meta.sender_identifier})`;
        }
        if (tx.meta?.sender_name) {
            return `From: ${tx.meta.sender_name}`;
        }
        if (tx.meta?.voucher_code) {
            return `Code: ${tx.meta.voucher_code}`;
        }
    }
    
    if (tx.type === 'withdraw' && tx.meta?.description) {
        return tx.meta.description;
    }
    return null;
};

const getTransactionIcon = (type: string) => {
    return type === 'deposit' ? TrendingUp : TrendingDown;
};

const getTransactionColor = (amount: number) => {
    return amount >= 0 ? 'text-green-600' : 'text-red-600';
};

// Group transactions by type and timestamp (same logic as desktop)
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
    for (const [timestamp, transactions] of txByTimestamp.entries()) {
        const totalAmount = transactions.reduce((sum, tx) => sum + (Number(tx.amount) || 0), 0);
        groups.push({
            key: `group-${timestamp}`,
            timestamp,
            type: 'withdraw',
            isGroup: true,
            transactions,
            totalAmount,
        });
    }
    
    // Sort by timestamp descending
    groups.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());
    
    return groups;
});
</script>

<template>
    <PwaLayout title="Wallet">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    <Wallet class="h-6 w-6 text-primary" />
                    <h1 class="text-lg font-semibold">Wallet</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Balance Card -->
            <Card>
                <CardContent class="pt-6">
                    <div class="text-center space-y-4">
                        <div>
                            <p class="text-sm text-muted-foreground">Available Balance</p>
                            <div class="text-4xl font-bold mt-2">₱{{ formattedBalance }}</div>
                        </div>
                        <Button as-child class="w-full" size="lg">
                            <Link href="/pwa/topup">
                                <Plus class="mr-2 h-5 w-5" />
                                Add Funds
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Transactions -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Activity class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Recent Transactions</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="groupedTransactions.length === 0" class="py-8 text-center">
                        <Activity class="mx-auto h-12 w-12 text-muted-foreground/50" />
                        <h3 class="mt-4 text-sm font-medium">No transactions yet</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Your wallet activity will appear here.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="group in groupedTransactions" :key="group.key">
                            <!-- Grouped voucher generation (expandable) -->
                            <Collapsible v-if="group.isGroup" class="border rounded-lg">
                                <div class="flex items-center justify-between p-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <Ticket class="h-4 w-4 text-muted-foreground" />
                                            <p class="font-medium text-sm">Voucher Generation</p>
                                        </div>
                                        <p class="text-xs text-muted-foreground mt-1">
                                            {{ group.transactions.length }} charges • {{ formatRelativeTime(group.timestamp) }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <p :class="['font-semibold text-sm', getTransactionColor(group.totalAmount)]">
                                                ₱{{ formatAmount(Math.abs(group.totalAmount)) }}
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
                                            <p class="font-mono text-xs">₱{{ formatAmount(Math.abs(tx.amount)) }}</p>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Single transaction (deposits, single charges) -->
                            <div v-else class="flex items-center justify-between p-3 rounded-lg border">
                                <div class="flex items-center gap-3 flex-1">
                                    <component :is="getTransactionIcon(group.type)" class="h-5 w-5 text-muted-foreground" />
                                    <div class="space-y-1">
                                        <p class="font-medium text-sm">{{ getTransactionLabel(group.transactions[0]) }}</p>
                                        <p v-if="getTransactionDescription(group.transactions[0])" class="text-xs text-muted-foreground">
                                            {{ getTransactionDescription(group.transactions[0]) }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatRelativeTime(group.timestamp) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right space-y-1">
                                    <div :class="['font-semibold text-sm', getTransactionColor(group.totalAmount)]">
                                        {{ group.totalAmount >= 0 ? '+' : '' }}₱{{ formatAmount(Math.abs(group.totalAmount)) }}
                                    </div>
                                    <Badge v-if="!group.transactions[0].confirmed" variant="warning" class="text-xs">
                                        Pending
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
