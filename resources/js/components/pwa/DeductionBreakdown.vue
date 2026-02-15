<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface DeductionData {
  face_value: {
    label: string;
    amount: number;
    amount_formatted: string;
    quantity: number;
    total: number;
    total_formatted: string;
    currency: string;
    description: string;
  };
  charges: {
    items: Array<{
      index: string;
      label: string;
      category: string;
      unit_price: number;
      unit_price_formatted: string;
      quantity: number;
      total: number;
      total_formatted: string;
      currency: string;
    }>;
    total: number;
    total_formatted: string;
    description: string;
  };
  summary: {
    face_value_total: number;
    face_value_total_formatted: string;
    charges_total: number;
    charges_total_formatted: string;
    grand_total: number;
    grand_total_formatted: string;
    currency: string;
    note: string;
  };
}

interface Props {
  deductionData: DeductionData | null;
}

const props = defineProps<Props>();

// Category labels for display
const categoryLabels: Record<string, string> = {
  'system': 'System Fees',
  'escrow': 'Escrow',
  'gateway': 'Gateway Fees',
  'other': 'Other Charges',
};

// Group charges by category
const chargesByCategory = computed(() => {
  if (!props.deductionData?.charges?.items) return {};
  
  const categorized: Record<string, typeof props.deductionData.charges.items> = {};
  
  props.deductionData.charges.items.forEach((charge) => {
    const category = charge.category || 'other';
    if (!categorized[category]) {
      categorized[category] = [];
    }
    categorized[category].push(charge);
  });
  
  return categorized;
});
</script>

<template>
  <div v-if="deductionData" class="space-y-6">
    <!-- Face Value Card -->
    <Card>
      <CardHeader>
        <CardTitle>{{ deductionData.face_value.label }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Amount per Voucher</div>
          <div class="text-base">{{ deductionData.face_value.amount_formatted }}</div>
        </div>
        
        <div v-if="deductionData.face_value.quantity > 1" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Quantity</div>
          <div class="text-base">{{ deductionData.face_value.quantity }} vouchers</div>
        </div>
        
        <div class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Subtotal</div>
          <div class="text-base font-semibold">{{ deductionData.face_value.total_formatted }}</div>
        </div>
        
        <div class="text-xs text-muted-foreground pt-2 border-t">
          {{ deductionData.face_value.description }}
        </div>
      </CardContent>
    </Card>

    <!-- Charges Card -->
    <Card v-if="Object.keys(chargesByCategory).length > 0">
      <CardHeader>
        <CardTitle>Processing Charges</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <!-- No charges message -->
        <div v-if="Object.keys(chargesByCategory).length === 0" class="text-sm text-muted-foreground text-center py-4">
          No additional charges
        </div>
        
        <!-- Charges by category -->
        <div
          v-for="(items, category) in chargesByCategory"
          :key="category"
          class="space-y-2"
        >
          <!-- Category header -->
          <div class="text-xs font-semibold text-foreground/70 uppercase tracking-wide pt-2 first:pt-0">
            {{ categoryLabels[category] || category }}
          </div>
          
          <!-- Items in this category -->
          <div class="space-y-2 pl-2">
            <div
              v-for="item in items"
              :key="item.index"
              class="flex justify-between text-sm"
            >
              <span class="text-muted-foreground">{{ item.label }}</span>
              <span class="font-medium">{{ item.total_formatted }}</span>
            </div>
          </div>
        </div>
        
        <!-- Charges total -->
        <div class="flex justify-between pt-3 border-t text-sm font-semibold">
          <span>Total Charges</span>
          <span>{{ deductionData.charges.total_formatted }}</span>
        </div>
        
        <div class="text-xs text-muted-foreground">
          {{ deductionData.charges.description }}
        </div>
      </CardContent>
    </Card>

    <!-- Summary Card -->
    <Card class="border-2 border-primary/20">
      <CardHeader>
        <CardTitle>Total Wallet Deduction</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="flex justify-between text-sm">
          <span class="text-muted-foreground">Face Value Total</span>
          <span class="font-medium">{{ deductionData.summary.face_value_total_formatted }}</span>
        </div>
        
        <div class="flex justify-between text-sm">
          <span class="text-muted-foreground">Charges Total</span>
          <span class="font-medium">{{ deductionData.summary.charges_total_formatted }}</span>
        </div>
        
        <div class="flex justify-between pt-3 border-t text-lg font-bold">
          <span>Grand Total</span>
          <span class="text-primary">{{ deductionData.summary.grand_total_formatted }}</span>
        </div>
        
        <div class="text-xs text-muted-foreground pt-2 border-t">
          {{ deductionData.summary.note }}
        </div>
      </CardContent>
    </Card>
  </div>
  
  <div v-else class="text-sm text-muted-foreground text-center py-8">
    No deduction data available
  </div>
</template>
