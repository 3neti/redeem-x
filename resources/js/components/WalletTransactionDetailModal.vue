<script setup lang="ts">
import { computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { ArrowDownLeft, ArrowUpRight, Calendar, Hash, Wallet, CreditCard, Building2, Zap, CheckCircle2, XCircle } from 'lucide-vue-next';
import type { WalletTransactionData } from '@/composables/useWalletTransactionApi';

interface Props {
    transaction: WalletTransactionData | null;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const getRailVariant = (rail?: string) => {
    switch (rail) {
        case 'INSTAPAY':
            return 'default';
        case 'PESONET':
            return 'secondary';
        default:
            return 'outline';
    }
};

const getStatusVariant = (status?: string) => {
    switch (status?.toLowerCase()) {
        case 'pending':
            return 'secondary';
        case 'completed':
        case 'success':
            return 'default';
        case 'failed':
        case 'error':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getMaskedAccount = (account?: string) => {
    if (!account || account.length <= 4) return account || 'N/A';
    return '***' + account.slice(-4);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader v-if="transaction">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg" :class="transaction.type === 'deposit' ? 'bg-green-100 dark:bg-green-900/20' : 'bg-red-100 dark:bg-red-900/20'">
                        <ArrowDownLeft v-if="transaction.type === 'deposit'" class="h-5 w-5 text-green-600 dark:text-green-400" />
                        <ArrowUpRight v-else class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="flex-1">
                        <DialogTitle class="text-2xl">
                            {{ transaction.type === 'deposit' ? 'Deposit' : 'Withdrawal' }}
                        </DialogTitle>
                        <DialogDescription>
                            Transaction ID: {{ transaction.uuid }}
                        </DialogDescription>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold" :class="transaction.type === 'deposit' ? 'text-green-600' : 'text-red-600'">
                            {{ transaction.type === 'deposit' ? '+' : '-' }}{{ formatAmount(transaction.amount, transaction.currency) }}
                        </div>
                        <Badge v-if="transaction.confirmed" variant="outline" class="mt-1">
                            <CheckCircle2 class="h-3 w-3 mr-1" />
                            Confirmed
                        </Badge>
                        <Badge v-else variant="secondary" class="mt-1">
                            <XCircle class="h-3 w-3 mr-1" />
                            Pending
                        </Badge>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="transaction" class="space-y-6 pt-4">
                <!-- Basic Information -->
                <div class="space-y-3">
                    <h3 class="text-sm font-medium flex items-center gap-2">
                        <Hash class="h-4 w-4" />
                        Transaction Details
                    </h3>
                    <Separator />
                    <div class="grid gap-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Transaction ID</span>
                            <span class="text-sm font-mono">{{ transaction.uuid }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Wallet ID</span>
                            <span class="text-sm font-mono">{{ transaction.wallet_id }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Date</span>
                            <span class="text-sm">{{ formatDate(transaction.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Deposit Details -->
                <div v-if="transaction.type === 'deposit'" class="space-y-3">
                    <h3 class="text-sm font-medium flex items-center gap-2">
                        <Wallet class="h-4 w-4" />
                        Deposit Information
                    </h3>
                    <Separator />
                    <div class="grid gap-3">
                        <div v-if="transaction.sender_name" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">From</span>
                            <span class="text-sm font-medium">{{ transaction.sender_name }}</span>
                        </div>
                        <div v-if="transaction.sender_identifier" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Reference</span>
                            <span class="text-sm font-mono">{{ transaction.sender_identifier }}</span>
                        </div>
                        <div v-if="transaction.payment_method" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Payment Method</span>
                            <Badge variant="outline" class="text-xs capitalize">
                                {{ transaction.payment_method.replace('_', ' ') }}
                            </Badge>
                        </div>
                        <div v-if="transaction.deposit_type" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Type</span>
                            <Badge variant="secondary" class="text-xs capitalize">
                                {{ transaction.deposit_type.replace('_', ' ') }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal Details -->
                <div v-if="transaction.type === 'withdraw'" class="space-y-3">
                    <h3 class="text-sm font-medium flex items-center gap-2">
                        <CreditCard class="h-4 w-4" />
                        Withdrawal Information
                    </h3>
                    <Separator />
                    <div class="grid gap-3">
                        <div v-if="transaction.voucher_code" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Voucher Code</span>
                            <span class="text-sm font-mono font-semibold">{{ transaction.voucher_code }}</span>
                        </div>
                        <div v-else class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Purpose</span>
                            <span class="text-sm">Voucher Generation</span>
                        </div>
                    </div>
                </div>

                <!-- Disbursement Details (for withdrawals with disbursement info) -->
                <div v-if="transaction.type === 'withdraw' && transaction.disbursement" class="space-y-3">
                    <h3 class="text-sm font-medium flex items-center gap-2">
                        <Building2 class="h-4 w-4" />
                        Disbursement Details
                    </h3>
                    <Separator />
                    <div class="grid gap-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Gateway</span>
                            <Badge variant="outline" class="text-xs uppercase">
                                {{ transaction.disbursement.gateway }}
                            </Badge>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Recipient</span>
                            <span class="text-sm font-medium">{{ transaction.disbursement.recipient_name }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Account</span>
                            <span class="text-sm font-mono">{{ getMaskedAccount(transaction.disbursement.recipient_identifier) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Settlement Rail</span>
                            <Badge :variant="getRailVariant(transaction.disbursement.rail)" class="text-xs">
                                <Zap class="h-3 w-3 mr-1" />
                                {{ transaction.disbursement.rail }}
                            </Badge>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Status</span>
                            <Badge :variant="getStatusVariant(transaction.disbursement.status)" class="text-xs">
                                {{ transaction.disbursement.status }}
                            </Badge>
                        </div>
                        <div v-if="transaction.disbursement.transaction_id" class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Transaction ID</span>
                            <span class="text-sm font-mono">{{ transaction.disbursement.transaction_id }}</span>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="space-y-3">
                    <h3 class="text-sm font-medium flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Timeline
                    </h3>
                    <Separator />
                    <div class="grid gap-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Created</span>
                            <span class="text-sm">{{ formatDate(transaction.created_at) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Last Updated</span>
                            <span class="text-sm">{{ formatDate(transaction.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="py-8 text-center text-muted-foreground">
                No transaction selected
            </div>
        </DialogContent>
    </Dialog>
</template>
