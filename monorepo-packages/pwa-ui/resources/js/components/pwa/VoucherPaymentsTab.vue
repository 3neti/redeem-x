<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TrendingUp, TrendingDown, Wallet, DollarSign } from 'lucide-vue-next';

interface Props {
  settlement?: any;
}

const props = defineProps<Props>();

// Format currency
const formatCurrency = (amount: number | null | undefined) => {
  if (!amount) return '₱0.00';
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};
</script>

<template>
  <div class="space-y-4">
    <div v-if="!settlement" class="text-center text-muted-foreground py-8">
      No payment information available
    </div>

    <template v-else>
      <!-- Summary Card -->
      <Card>
        <CardHeader>
          <CardTitle class="text-lg">Payment Summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <!-- Target Amount -->
          <div v-if="settlement.target_amount" class="flex items-center justify-between p-3 bg-muted rounded-lg">
            <div class="flex items-center gap-2">
              <DollarSign class="h-5 w-5 text-muted-foreground" />
              <span class="text-sm font-medium">Target Amount</span>
            </div>
            <span class="font-semibold">{{ formatCurrency(settlement.target_amount) }}</span>
          </div>

          <!-- Collected -->
          <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200">
            <div class="flex items-center gap-2">
              <TrendingUp class="h-5 w-5 text-green-600" />
              <span class="text-sm font-medium text-green-900">Collected</span>
            </div>
            <span class="font-semibold text-green-900">{{ formatCurrency(settlement.paid_total) }}</span>
          </div>

          <!-- Redeemed -->
          <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-200">
            <div class="flex items-center gap-2">
              <TrendingDown class="h-5 w-5 text-orange-600" />
              <span class="text-sm font-medium text-orange-900">Redeemed</span>
            </div>
            <span class="font-semibold text-orange-900">{{ formatCurrency(settlement.redeemed_total) }}</span>
          </div>

          <!-- Available Balance -->
          <div class="flex items-center justify-between p-3 bg-primary/10 rounded-lg border border-primary/20">
            <div class="flex items-center gap-2">
              <Wallet class="h-5 w-5 text-primary" />
              <span class="text-sm font-medium text-primary">Available</span>
            </div>
            <span class="font-bold text-primary">{{ formatCurrency(settlement.available_balance) }}</span>
          </div>

          <!-- Remaining (if applicable) -->
          <div v-if="settlement.remaining" class="pt-2 border-t">
            <div class="flex items-center justify-between">
              <span class="text-sm text-muted-foreground">Remaining to Collect</span>
              <span class="font-semibold text-orange-600">{{ formatCurrency(settlement.remaining) }}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Status Flags -->
      <Card>
        <CardHeader>
          <CardTitle class="text-lg">Status</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">Can Accept Payment</span>
            <Badge :variant="settlement.can_accept_payment ? 'success' : 'secondary'">
              {{ settlement.can_accept_payment ? 'Yes' : 'No' }}
            </Badge>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">Can Redeem</span>
            <Badge :variant="settlement.can_redeem ? 'success' : 'secondary'">
              {{ settlement.can_redeem ? 'Yes' : 'No' }}
            </Badge>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">Locked</span>
            <Badge :variant="settlement.is_locked ? 'destructive' : 'secondary'">
              {{ settlement.is_locked ? 'Yes' : 'No' }}
            </Badge>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">Closed</span>
            <Badge :variant="settlement.is_closed ? 'destructive' : 'secondary'">
              {{ settlement.is_closed ? 'Yes' : 'No' }}
            </Badge>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">Expired</span>
            <Badge :variant="settlement.is_expired ? 'destructive' : 'secondary'">
              {{ settlement.is_expired ? 'Yes' : 'No' }}
            </Badge>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
