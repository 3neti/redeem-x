<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowDownLeft, ArrowUpRight, Wallet } from 'lucide-vue-next';

interface WalletTransaction {
  id: number;
  uuid: string;
  type: 'deposit' | 'withdraw';
  amount: number;
  currency: string;
  confirmed: boolean;
  meta: {
    flow?: 'pay' | 'redeem';
    purpose?: string;
    voucher_code?: string;
    [key: string]: any;
  };
  created_at: string;
}

interface Props {
  transactions: WalletTransaction[];
  voucherCode: string;
}

const props = defineProps<Props>();

// Transaction type detection
type TransactionType = 'payment' | 'redemption' | 'charge' | 'topup' | 'unknown';

const getTransactionType = (tx: WalletTransaction): TransactionType => {
  if (tx.type === 'deposit' && tx.meta?.flow === 'pay') return 'payment';
  if (tx.type === 'withdraw' && tx.meta?.flow === 'redeem') return 'redemption';
  if (tx.type === 'withdraw' && tx.meta?.purpose === 'voucher_generation') return 'charge';
  if (tx.type === 'deposit' && !tx.meta?.flow) return 'topup';
  return 'unknown';
};

// Transaction type labels
const getTypeLabel = (tx: WalletTransaction): string => {
  const type = getTransactionType(tx);
  switch (type) {
    case 'payment': return 'Payment';
    case 'redemption': return 'Redemption';
    case 'charge': return 'Wallet Charge';
    case 'topup': return 'Top-Up';
    default: return 'Transaction';
  }
};

// Transaction type badge variant
const getTypeBadgeVariant = (tx: WalletTransaction): 'default' | 'secondary' | 'outline' => {
  const type = getTransactionType(tx);
  switch (type) {
    case 'payment':
    case 'topup':
      return 'default';
    case 'redemption':
    case 'charge':
      return 'secondary';
    default:
      return 'outline';
  }
};

// Transaction icon
const getTransactionIcon = (tx: WalletTransaction) => {
  return tx.type === 'deposit' ? ArrowDownLeft : ArrowUpRight;
};

// Amount color class
const getAmountColorClass = (tx: WalletTransaction): string => {
  return tx.type === 'deposit' 
    ? 'text-green-600 dark:text-green-400' 
    : 'text-red-600 dark:text-red-400';
};

// Format amount with sign
const formatAmount = (tx: WalletTransaction): string => {
  const sign = tx.type === 'deposit' ? '+' : '-';
  const amount = Math.abs(tx.amount);
  return `${sign}${new Intl.NumberFormat('en-PH', { 
    style: 'currency', 
    currency: tx.currency 
  }).format(amount)}`;
};

// Format date
const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Transaction description
const getTransactionDescription = (tx: WalletTransaction): string => {
  const type = getTransactionType(tx);
  switch (type) {
    case 'payment':
      return `Payment to voucher ${props.voucherCode}`;
    case 'redemption':
      return `Voucher ${props.voucherCode} redeemed`;
    case 'charge':
      return `Wallet deduction for voucher generation`;
    case 'topup':
      return `Wallet top-up`;
    default:
      return 'Transaction';
  }
};

// Summary stats
const summary = computed(() => {
  const payments = props.transactions.filter(tx => getTransactionType(tx) === 'payment');
  const redemptions = props.transactions.filter(tx => getTransactionType(tx) === 'redemption');
  
  const totalPayments = payments.reduce((sum, tx) => sum + tx.amount, 0);
  const totalRedemptions = redemptions.reduce((sum, tx) => sum + Math.abs(tx.amount), 0);
  
  return {
    totalPayments,
    totalRedemptions,
    paymentCount: payments.length,
    redemptionCount: redemptions.length,
  };
});

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', { 
    style: 'currency', 
    currency: 'PHP' 
  }).format(amount);
};
</script>

<template>
  <div class="space-y-6">
    <!-- Summary Stats -->
    <div v-if="transactions.length > 0" class="grid grid-cols-2 gap-4">
      <Card>
        <CardHeader class="pb-2">
          <CardDescription>Total Payments</CardDescription>
          <CardTitle class="text-2xl text-green-600 dark:text-green-400">
            {{ formatCurrency(summary.totalPayments) }}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-xs text-muted-foreground">{{ summary.paymentCount }} payment{{ summary.paymentCount !== 1 ? 's' : '' }}</p>
        </CardContent>
      </Card>
      
      <Card>
        <CardHeader class="pb-2">
          <CardDescription>Total Redeemed</CardDescription>
          <CardTitle class="text-2xl text-red-600 dark:text-red-400">
            {{ formatCurrency(summary.totalRedemptions) }}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-xs text-muted-foreground">{{ summary.redemptionCount }} redemption{{ summary.redemptionCount !== 1 ? 's' : '' }}</p>
        </CardContent>
      </Card>
    </div>

    <!-- Transaction Timeline -->
    <Card>
      <CardHeader>
        <CardTitle>Transaction History</CardTitle>
        <CardDescription>
          {{ transactions.length }} transaction{{ transactions.length !== 1 ? 's' : '' }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <!-- Empty State -->
        <div v-if="transactions.length === 0" class="text-center py-8">
          <Wallet class="h-12 w-12 mx-auto text-muted-foreground mb-3" />
          <p class="text-sm text-muted-foreground">No transactions yet</p>
          <p class="text-xs text-muted-foreground mt-1">
            Transactions will appear here when payments are made or voucher is redeemed
          </p>
        </div>

        <!-- Transaction List -->
        <div v-else class="space-y-4">
          <div
            v-for="tx in transactions"
            :key="tx.id"
            class="flex items-start gap-3 pb-4 border-b last:border-b-0 last:pb-0"
          >
            <!-- Icon -->
            <div 
              class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
              :class="tx.type === 'deposit' ? 'bg-green-100 dark:bg-green-900/20' : 'bg-red-100 dark:bg-red-900/20'"
            >
              <component 
                :is="getTransactionIcon(tx)" 
                class="h-5 w-5"
                :class="tx.type === 'deposit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
              />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <Badge :variant="getTypeBadgeVariant(tx)" class="text-xs">
                      {{ getTypeLabel(tx) }}
                    </Badge>
                    <Badge v-if="!tx.confirmed" variant="outline" class="text-xs">
                      Pending
                    </Badge>
                  </div>
                  <p class="text-sm text-muted-foreground">
                    {{ getTransactionDescription(tx) }}
                  </p>
                  <p class="text-xs text-muted-foreground mt-1 font-mono">
                    {{ tx.uuid }}
                  </p>
                </div>
                
                <!-- Amount -->
                <div class="text-right flex-shrink-0">
                  <div class="text-base font-semibold" :class="getAmountColorClass(tx)">
                    {{ formatAmount(tx) }}
                  </div>
                  <div class="text-xs text-muted-foreground mt-1">
                    {{ formatDate(tx.created_at) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
