<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Copy, Check, ArrowRight, RefreshCw } from 'lucide-vue-next';
import GatewayBadge from '@/components/GatewayBadge.vue';
import type { TransactionData } from '@/composables/useTransactionApi';

interface Props {
    transaction: TransactionData | null;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const copied = ref(false);
const refreshing = ref(false);

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
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const copyOperationId = async () => {
    const transactionId = getTransactionId();
    if (!transactionId) return;
    
    try {
        await navigator.clipboard.writeText(transactionId);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

// Helper to get transaction ID
const getTransactionId = () => {
    return props.transaction?.disbursement?.transaction_id;
};

// Helper to get recipient identifier
const getRecipientIdentifier = () => {
    return props.transaction?.disbursement?.recipient_identifier || 'N/A';
};

// Helper to get bank/recipient name
const getBankName = () => {
    return props.transaction?.disbursement?.recipient_name || 'N/A';
};

// Helper to get rail
const getRail = () => {
    return props.transaction?.disbursement?.metadata?.rail;
};

// Helper to get payment method display
const getPaymentMethod = () => {
    const pm = props.transaction?.disbursement?.payment_method;
    return pm === 'bank_transfer' ? 'Bank Transfer' : 
           pm === 'e_wallet' ? 'E-Wallet' : 
           pm === 'card' ? 'Credit/Debit Card' : 
           pm || 'Unknown';
};

// Helper to check if it's an e-wallet
const isEWallet = () => {
    return props.transaction?.disbursement?.payment_method === 'e_wallet';
};

// Helper to get gateway display name
const getGatewayName = () => {
    const d = props.transaction?.disbursement;
    const gateway = d?.gateway;
    if (!gateway) return null;
    
    return gateway.charAt(0).toUpperCase() + gateway.slice(1);
};

// Helper to get currency
const getCurrency = () => {
    return props.transaction?.disbursement?.currency || props.transaction?.currency || 'PHP';
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

const refreshStatus = async () => {
    if (!props.transaction?.code) return;
    
    refreshing.value = true;
    
    try {
        await fetch(`/api/v1/transactions/${props.transaction.code}/refresh-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        
        // Reload the entire page to get updated transaction data
        router.reload();
    } catch (error) {
        console.error('Failed to refresh status:', error);
    } finally {
        setTimeout(() => {
            refreshing.value = false;
        }, 1000);
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="closeDialog">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Transaction Details</DialogTitle>
                <DialogDescription>
                    Voucher Code: <span class="font-mono font-semibold">{{ transaction?.code }}</span>
                </DialogDescription>
            </DialogHeader>

            <div v-if="transaction" class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription>Amount</CardDescription>
                            <CardTitle class="text-3xl">
                                {{ formatAmount(transaction.amount, transaction.currency) }}
                            </CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardDescription>Status</CardDescription>
                            <CardTitle>
                                <Badge 
                                    v-if="transaction.disbursement" 
                                    :variant="getStatusVariant(transaction.disbursement.status)"
                                    class="text-base"
                                >
                                    {{ transaction.disbursement.status }}
                                </Badge>
                                <Badge v-else variant="outline" class="text-base">
                                    No Disbursement
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <!-- Disbursement Details -->
                <Card v-if="transaction.disbursement">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GatewayBadge 
                                :gateway="transaction.disbursement.gateway"
                                size="md"
                            />
                            Transfer Details
                        </CardTitle>
                        <CardDescription>Disbursement information for this transaction</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Recipient</p>
                                <p class="font-medium">{{ getBankName() }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Account / Identifier</p>
                                <p class="font-mono font-medium">{{ getRecipientIdentifier() }}</p>
                            </div>
                            <div v-if="getRail()" class="space-y-1">
                                <p class="text-sm text-muted-foreground">Settlement Rail</p>
                                <Badge :variant="getRailVariant(getRail())">
                                    {{ getRail() }}
                                </Badge>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Payment Method</p>
                                <Badge variant="outline">
                                    {{ getPaymentMethod() }}
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Transaction ID</p>
                            <div class="flex items-center gap-2">
                                <p class="font-mono text-sm">{{ getTransactionId() }}</p>
                                <Button 
                                    @click="copyOperationId" 
                                    size="sm" 
                                    variant="ghost"
                                    class="h-8 w-8 p-0"
                                >
                                    <Check v-if="copied" class="h-4 w-4 text-green-600" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Transaction UUID</p>
                            <p class="font-mono text-sm text-muted-foreground">
                                {{ transaction.disbursement.transaction_uuid }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Disbursed At</p>
                            <p class="text-sm">{{ formatDate(transaction.disbursement.disbursed_at) }}</p>
                        </div>

                        <!-- Refresh Status Button -->
                        <div class="pt-4 border-t">
                            <Button 
                                @click="refreshStatus" 
                                :disabled="refreshing" 
                                size="sm" 
                                variant="outline" 
                                class="w-full"
                            >
                                <RefreshCw :class="{ 'animate-spin': refreshing }" class="mr-2 h-4 w-4" />
                                {{ refreshing ? 'Refreshing Status...' : 'Refresh Status' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Transaction Timeline -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Timeline</CardTitle>
                        <CardDescription>Complete history of this voucher</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol class="relative border-l border-muted">
                            <!-- Voucher Generated -->
                            <li class="mb-6 ml-6">
                                <div class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary ring-8 ring-background">
                                    <Check class="h-3 w-3 text-primary-foreground" />
                                </div>
                                <div>
                                    <h3 class="font-semibold">Voucher Generated</h3>
                                    <time class="text-sm text-muted-foreground">
                                        {{ formatDate(transaction.created_at) }}
                                    </time>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        Created by {{ transaction.owner?.name || 'System' }}
                                    </p>
                                </div>
                            </li>

                            <!-- Voucher Redeemed -->
                            <li class="mb-6 ml-6">
                                <div class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary ring-8 ring-background">
                                    <Check class="h-3 w-3 text-primary-foreground" />
                                </div>
                                <div>
                                    <h3 class="font-semibold">Voucher Redeemed</h3>
                                    <time class="text-sm text-muted-foreground">
                                        {{ formatDate(transaction.redeemed_at) }}
                                    </time>
                                    <p v-if="transaction.contact" class="text-sm text-muted-foreground mt-1">
                                        By {{ transaction.contact.name || transaction.contact.mobile }}
                                    </p>
                                </div>
                            </li>

                            <!-- Disbursement Processed -->
                            <li v-if="transaction.disbursement" class="ml-6">
                                <div 
                                    class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-8 ring-background"
                                    :class="transaction.disbursement.status === 'Completed' ? 'bg-primary' : 'bg-secondary'"
                                >
                                    <ArrowRight v-if="transaction.disbursement.status === 'Pending'" class="h-3 w-3" />
                                    <Check v-else class="h-3 w-3 text-primary-foreground" />
                                </div>
                                <div>
                                    <h3 class="font-semibold">Disbursement {{ transaction.disbursement.status }}</h3>
                                    <time class="text-sm text-muted-foreground">
                                        {{ formatDate(transaction.disbursement.disbursed_at) }}
                                    </time>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        {{ formatAmount(transaction.disbursement.amount, getCurrency()) }} 
                                        sent to {{ getBankName() }}
                                        <span v-if="getRail()"> via {{ getRail() }}</span>
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <!-- Voucher Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Voucher Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Code</p>
                                <p class="font-mono font-semibold">{{ transaction.code }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Status</p>
                                <Badge>{{ transaction.status }}</Badge>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">Created At</p>
                                <p class="text-sm">{{ formatDate(transaction.created_at) }}</p>
                            </div>
                            <div v-if="transaction.expires_at" class="space-y-1">
                                <p class="text-sm text-muted-foreground">Expires At</p>
                                <p class="text-sm">{{ formatDate(transaction.expires_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </DialogContent>
    </Dialog>
</template>
