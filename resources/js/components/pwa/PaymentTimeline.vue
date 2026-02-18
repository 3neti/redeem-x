<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowDownLeft, ArrowUpRight, Wallet, Loader2, Clock } from 'lucide-vue-next';
import PaymentConfirmSheet from './PaymentConfirmSheet.vue';

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

interface PendingPaymentRequest {
  id: number;
  reference_id: string;
  amount: number;
  currency: string;
  payer_info: Record<string, any> | null;
  status: string;
  created_at: string;
}

interface Props {
  transactions: WalletTransaction[];
  voucherCode: string;
  isOwner?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isOwner: false,
});

const emit = defineEmits<{
  paymentConfirmed: [];
}>();

// Pending payments state
const pendingPayments = ref<PendingPaymentRequest[]>([]);
const loadingPending = ref(false);
const showConfirmSheet = ref(false);
const selectedPayment = ref<PendingPaymentRequest | null>(null);

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

// Format date with diff for humans
const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);
  
  if (diffSecs < 60) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays}d ago`;
  if (diffDays < 30) return `${Math.floor(diffDays / 7)}w ago`;
  if (diffDays < 365) return `${Math.floor(diffDays / 30)}mo ago`;
  return `${Math.floor(diffDays / 365)}y ago`;
};

// Shorten UUID to last segment (like Git)
const shortenUuid = (uuid: string): string => {
  const parts = uuid.split('-');
  return parts[parts.length - 1]; // Last segment
};

// Get transaction actor (who)
const getTransactionActor = (tx: WalletTransaction): string | null => {
  // For payments: sender name or identifier
  if (tx.type === 'deposit' && tx.meta?.sender_name) {
    return tx.meta.sender_name;
  }
  if (tx.type === 'deposit' && tx.meta?.sender_identifier) {
    return tx.meta.sender_identifier;
  }
  
  // For redemptions: contact mobile (redeemer) or recipient identifier
  if (tx.type === 'withdraw') {
    if (tx.meta?.contact_mobile) {
      return tx.meta.contact_mobile;
    }
    if (tx.meta?.recipient_identifier) {
      return tx.meta.recipient_identifier;
    }
  }
  
  return null;
};

// Get bank/account info
const getBankInfo = (tx: WalletTransaction): string | null => {
  // For payments: payment method or bank
  if (tx.type === 'deposit' && tx.meta?.payment_method) {
    return tx.meta.payment_method.replace('_', ' ');
  }
  if (tx.type === 'deposit' && tx.meta?.bank_code) {
    return getBankName(tx.meta.bank_code);
  }
  
  // For withdrawals (redemptions): bank name with full account number
  if (tx.type === 'withdraw') {
    const bankName = tx.meta?.bank_name || (tx.meta?.bank_code ? getBankName(tx.meta.bank_code) : null);
    const account = tx.meta?.recipient_identifier;
    const rail = tx.meta?.settlement_rail;
    
    if (bankName && account) {
      // Format: "GCash: 09171234567" or "GCash: 09171234567 (INSTAPAY)"
      const railSuffix = rail ? ` (${rail})` : '';
      return `${bankName}: ${account}${railSuffix}`;
    }
    if (bankName) return bankName;
    if (account) return account;
  }
  
  return null;
};

// Get bank name from code
const getBankName = (code: string): string => {
  const banks: Record<string, string> = {
    'GXCHPHM2XXX': 'GCash',
    'PYMAPHM2XXX': 'PayMaya',
    'BDOPHMMM': 'BDO',
    'BOPIPHMM': 'BPI',
    'MBTCPHM2XXX': 'Metrobank',
  };
  return banks[code] || code;
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

// Fetch pending payments
const fetchPendingPayments = async () => {
  if (!props.isOwner) return;
  
  loadingPending.value = true;
  try {
    const { data } = await axios.get(`/api/v1/vouchers/${props.voucherCode}/pending-payments`);
    if (data.success && Array.isArray(data.data)) {
      pendingPayments.value = data.data;
    }
  } catch (err: any) {
    console.error('Failed to fetch pending payments:', err);
  } finally {
    loadingPending.value = false;
  }
};

// Open confirm sheet
const handleConfirmPayment = (payment: PendingPaymentRequest) => {
  selectedPayment.value = payment;
  showConfirmSheet.value = true;
};

// Handle payment confirmed
const handlePaymentConfirmed = () => {
  // Refresh pending payments (removes confirmed payment from list)
  fetchPendingPayments();
  // Emit event to parent to refresh transaction list
  emit('paymentConfirmed');
};

// Load pending payments on mount
onMounted(() => {
  if (props.isOwner) {
    fetchPendingPayments();
  }
});
</script>

<template>
  <div class="space-y-6">
    <!-- Pending Payment Requests (Owner Only) -->
    <Card v-if="isOwner && (loadingPending || pendingPayments.length > 0)">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Clock class="h-5 w-5" />
          Pending Payment Requests
        </CardTitle>
        <CardDescription>
          Awaiting confirmation
        </CardDescription>
      </CardHeader>
      <CardContent>
        <!-- Loading State -->
        <div v-if="loadingPending" class="text-center py-4">
          <Loader2 class="h-6 w-6 mx-auto mb-2 animate-spin text-muted-foreground" />
          <p class="text-sm text-muted-foreground">Loading pending payments...</p>
        </div>
        
        <!-- Pending Requests List -->
        <div v-else-if="pendingPayments.length > 0" class="space-y-3">
          <div 
            v-for="request in pendingPayments" 
            :key="request.id"
            class="p-4 rounded-lg border bg-amber-50 dark:bg-amber-950/20 space-y-3"
          >
            <div class="flex items-start justify-between">
              <div class="space-y-1 flex-1">
                <p class="text-lg font-semibold">{{ formatCurrency(request.amount) }}</p>
                <p class="text-xs text-muted-foreground font-mono">{{ request.reference_id }}</p>
                <p class="text-xs text-muted-foreground">{{ formatDate(request.created_at) }}</p>
                <div v-if="request.payer_info" class="flex flex-wrap gap-2 text-xs mt-2">
                  <span v-if="request.payer_info.name" class="text-muted-foreground">
                    From: {{ request.payer_info.name }}
                  </span>
                  <span v-if="request.payer_info.mobile" class="text-muted-foreground">
                    {{ request.payer_info.mobile }}
                  </span>
                </div>
              </div>
              <Badge variant="secondary" class="text-xs">
                Awaiting Confirmation
              </Badge>
            </div>
            
            <Button 
              @click="handleConfirmPayment(request)"
              class="w-full"
              size="sm"
            >
              ✓ Confirm Payment
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
    
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
                  
                  <!-- Actor (Who) -->
                  <p v-if="getTransactionActor(tx)" class="text-sm font-medium">
                    {{ getTransactionActor(tx) }}
                  </p>
                  
                  <!-- Bank/Account Info -->
                  <p v-if="getBankInfo(tx)" class="text-xs text-muted-foreground capitalize">
                    {{ getBankInfo(tx) }}
                  </p>
                  
                  <!-- Shortened UUID -->
                  <p class="text-xs text-muted-foreground mt-1 font-mono">
                    {{ shortenUuid(tx.uuid) }}
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
    
    <!-- Payment Confirmation Sheet -->
    <PaymentConfirmSheet
      v-if="selectedPayment"
      v-model:open="showConfirmSheet"
      :voucher-code="voucherCode"
      :amount="selectedPayment.amount"
      :payment-request-id="selectedPayment.id"
      @confirmed="handlePaymentConfirmed"
    />
  </div>
</template>
