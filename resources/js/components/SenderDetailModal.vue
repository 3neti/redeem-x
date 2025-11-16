<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, User, Phone, Calendar, TrendingUp, CreditCard } from 'lucide-vue-next';
import { useDepositApi } from '@/composables/useDepositApi';
import type { SenderData, DepositTransactionData } from '@/composables/useDepositApi';

interface Props {
    senderId: number | null;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { loading, getSenderDetails } = useDepositApi();
const senderData = ref<SenderData | null>(null);
const transactions = ref<DepositTransactionData[]>([]);

const closeDialog = () => {
    emit('update:open', false);
};

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatMobile = (mobile: string) => {
    // Format: 639173011987 -> +63 917 301 1987
    if (mobile.length === 12 && mobile.startsWith('63')) {
        return `+63 ${mobile.substring(2, 5)} ${mobile.substring(5, 8)} ${mobile.substring(8)}`;
    }
    return mobile;
};

// Load sender details when modal opens
watch(() => props.open, async (isOpen) => {
    if (isOpen && props.senderId) {
        try {
            const response = await getSenderDetails(props.senderId);
            senderData.value = response.sender;
            transactions.value = response.transactions;
        } catch (error) {
            console.error('Failed to fetch sender details:', error);
        }
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Sender Details</DialogTitle>
                <DialogDescription v-if="senderData">
                    {{ senderData.name }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="flex justify-center py-12">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>

            <div v-else-if="senderData" class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription class="flex items-center gap-2">
                                <TrendingUp class="h-4 w-4" />
                                Total Sent
                            </CardDescription>
                            <CardTitle class="text-2xl">
                                {{ formatAmount(senderData.total_sent, 'PHP') }}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription class="flex items-center gap-2">
                                <Calendar class="h-4 w-4" />
                                Transactions
                            </CardDescription>
                            <CardTitle class="text-2xl">
                                {{ senderData.transaction_count }}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription class="flex items-center gap-2">
                                <CreditCard class="h-4 w-4" />
                                Payment Methods
                            </CardDescription>
                            <CardTitle class="text-2xl">
                                {{ senderData.institutions_used.length }}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <!-- Contact Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Contact Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-start gap-3">
                            <User class="h-5 w-5 text-muted-foreground mt-0.5" />
                            <div>
                                <div class="text-sm text-muted-foreground">Name</div>
                                <div class="font-medium">{{ senderData.name }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Phone class="h-5 w-5 text-muted-foreground mt-0.5" />
                            <div>
                                <div class="text-sm text-muted-foreground">Mobile Number</div>
                                <div class="font-medium font-mono">{{ formatMobile(senderData.mobile) }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <CreditCard class="h-5 w-5 text-muted-foreground mt-0.5" />
                            <div>
                                <div class="text-sm text-muted-foreground">Payment Methods Used</div>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    <Badge 
                                        v-for="institution in senderData.institutions_used" 
                                        :key="institution"
                                        variant="outline"
                                    >
                                        {{ institution }}
                                    </Badge>
                                </div>
                                <div v-if="senderData.latest_institution_name" class="text-sm text-muted-foreground mt-2">
                                    Latest: <span class="font-medium">{{ senderData.latest_institution_name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Calendar class="h-5 w-5 text-muted-foreground mt-0.5" />
                            <div>
                                <div class="text-sm text-muted-foreground">Transaction Period</div>
                                <div class="text-sm">
                                    <span v-if="senderData.first_transaction_at">
                                        First: {{ formatDate(senderData.first_transaction_at) }}
                                    </span>
                                    <br v-if="senderData.first_transaction_at && senderData.last_transaction_at" />
                                    <span v-if="senderData.last_transaction_at">
                                        Last: {{ formatDate(senderData.last_transaction_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Transaction History -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction History</CardTitle>
                        <CardDescription>{{ transactions.length }} transactions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="relative overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                        <th class="px-4 py-3 text-left">Payment Method</th>
                                        <th class="px-4 py-3 text-left">Operation ID</th>
                                        <th class="px-4 py-3 text-left">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(tx, index) in transactions"
                                        :key="tx.operation_id || index"
                                        class="border-b hover:bg-muted/50 transition-colors"
                                    >
                                        <td class="px-4 py-3 text-right font-semibold text-green-600">
                                            {{ formatAmount(tx.amount, tx.currency) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge variant="outline" class="text-xs">
                                                {{ tx.institution_name }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-mono text-xs text-muted-foreground">
                                                {{ tx.operation_id || 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ tx.timestamp ? formatDate(tx.timestamp) : 'N/A' }}
                                        </td>
                                    </tr>
                                    <tr v-if="transactions.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                            No transactions found
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="py-12 text-center text-muted-foreground">
                No sender data available
            </div>
        </DialogContent>
    </Dialog>
</template>
