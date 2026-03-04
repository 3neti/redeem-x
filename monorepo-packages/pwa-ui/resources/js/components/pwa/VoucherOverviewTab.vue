<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/components/ui/toast/use-toast';
import { Calendar, Clock, Wallet, Copy, CheckCircle2, XCircle } from 'lucide-vue-next';

interface Props {
  voucherData: any;
  settlement?: any;
}

const props = defineProps<Props>();
const { toast } = useToast();

// Format currency
const formatCurrency = (amount: number | null | undefined) => {
  if (!amount) return '₱0.00';
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};

// Format date
const formatDate = (dateStr: string | null | undefined) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Status badge variant
const statusVariant = computed(() => {
  switch (props.voucherData?.status) {
    case 'active': return 'default';
    case 'redeemed': return 'success';
    case 'expired': return 'destructive';
    case 'locked': return 'secondary';
    default: return 'outline';
  }
});

// Copy voucher code
const copyCode = async () => {
  try {
    await navigator.clipboard.writeText(props.voucherData.code);
    toast({
      title: 'Copied!',
      description: 'Voucher code copied to clipboard',
    });
  } catch (error) {
    toast({
      title: 'Error',
      description: 'Failed to copy code',
      variant: 'destructive',
    });
  }
};
</script>

<template>
  <div class="space-y-4">
    <!-- Main Stats -->
    <Card>
      <CardHeader>
        <CardTitle class="text-lg">Basic Information</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <!-- Amount -->
        <div>
          <div class="text-sm text-muted-foreground mb-1">Amount</div>
          <div class="text-2xl font-bold">{{ formatCurrency(voucherData.amount) }}</div>
        </div>

        <!-- Voucher Code -->
        <div>
          <div class="text-sm text-muted-foreground mb-1">Code</div>
          <div class="flex items-center gap-2">
            <code class="flex-1 text-lg font-mono bg-muted px-3 py-2 rounded">{{ voucherData.code }}</code>
            <Button variant="ghost" size="icon" @click="copyCode">
              <Copy class="h-4 w-4" />
            </Button>
          </div>
        </div>

        <!-- Status -->
        <div>
          <div class="text-sm text-muted-foreground mb-1">Status</div>
          <Badge :variant="statusVariant">{{ voucherData.status }}</Badge>
        </div>

        <!-- Type -->
        <div v-if="voucherData.voucher_type">
          <div class="text-sm text-muted-foreground mb-1">Type</div>
          <Badge variant="outline" class="capitalize">{{ voucherData.voucher_type }}</Badge>
        </div>
      </CardContent>
    </Card>

    <!-- Dates -->
    <Card>
      <CardHeader>
        <CardTitle class="text-lg">Timeline</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="flex items-start gap-3">
          <Calendar class="h-4 w-4 text-muted-foreground mt-0.5" />
          <div class="flex-1">
            <div class="text-sm text-muted-foreground">Created</div>
            <div class="text-sm font-medium">{{ formatDate(voucherData.created_at) }}</div>
          </div>
        </div>

        <div v-if="voucherData.expires_at" class="flex items-start gap-3">
          <Clock class="h-4 w-4 text-muted-foreground mt-0.5" />
          <div class="flex-1">
            <div class="text-sm text-muted-foreground">Expires</div>
            <div class="text-sm font-medium">{{ formatDate(voucherData.expires_at) }}</div>
          </div>
        </div>

        <div v-if="voucherData.redeemed_at" class="flex items-start gap-3">
          <CheckCircle2 class="h-4 w-4 text-green-600 mt-0.5" />
          <div class="flex-1">
            <div class="text-sm text-muted-foreground">Redeemed</div>
            <div class="text-sm font-medium">{{ formatDate(voucherData.redeemed_at) }}</div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Settlement Summary (conditional) -->
    <Card v-if="settlement">
      <CardHeader>
        <CardTitle class="text-lg">Settlement</CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="settlement.target_amount">
          <div class="text-sm text-muted-foreground">Target Amount</div>
          <div class="text-lg font-semibold">{{ formatCurrency(settlement.target_amount) }}</div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <div class="text-sm text-muted-foreground">Collected</div>
            <div class="text-sm font-medium">{{ formatCurrency(settlement.paid_total) }}</div>
          </div>
          <div>
            <div class="text-sm text-muted-foreground">Available</div>
            <div class="text-sm font-medium">{{ formatCurrency(settlement.available_balance) }}</div>
          </div>
        </div>

        <div v-if="settlement.remaining" class="pt-2 border-t">
          <div class="text-sm text-muted-foreground">Remaining</div>
          <div class="text-lg font-semibold text-orange-600">{{ formatCurrency(settlement.remaining) }}</div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
