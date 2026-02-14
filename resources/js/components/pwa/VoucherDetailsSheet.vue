<script setup lang="ts">
import { ref, computed } from 'vue';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Code, ChevronDown, Banknote } from 'lucide-vue-next';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';

interface Props {
  open: boolean;
  voucherData: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

// Active tab state
const activeTab = ref('instructions');

// Collapsible state for JSON Preview (open by default)
const jsonPreviewOpen = ref(true);
const deductionPreviewOpen = ref(true);
const redemptionPreviewOpen = ref(true);

// Compute charges from voucher instructions
const instructionsForPricing = computed(() => {
  if (!props.voucherData.instructions) return { cash: { amount: 0 }, count: 1 };
  return {
    ...props.voucherData.instructions,
    count: 1, // Single voucher
  };
});

const { deductionJson, loading: pricingLoading } = useChargeBreakdown(instructionsForPricing, {
  debounce: 0,
  autoCalculate: true,
  faceValueLabel: 'Voucher Amount (Escrowed)',
});

const handleOpenChange = (value: boolean) => {
  emit('update:open', value);
  console.log('Sheet open changed to:', value);
};

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
};
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent 
      side="bottom" 
      class="h-[70vh] p-0 flex flex-col"
    >
      <!-- Header with drag handle (30% viewport) -->
      <div class="flex-shrink-0 h-[30%]">
        <div class="w-12 h-1 bg-muted rounded-full mx-auto mt-2 mb-4" />
        <SheetHeader class="px-6 pb-4">
          <SheetTitle class="text-2xl">Pay Code: {{ voucherData.code }}</SheetTitle>
          <SheetDescription class="text-lg space-y-1">
            <div class="font-semibold capitalize">{{ voucherData.voucher_type }}</div>
            <div v-if="voucherData.target_amount" class="text-primary">
              {{ formatCurrency(voucherData.amount) }} → {{ formatCurrency(voucherData.target_amount) }}
            </div>
            <div v-else class="text-primary">
              {{ formatCurrency(voucherData.amount) }}
            </div>
          </SheetDescription>
        </SheetHeader>
      </div>

      <!-- Tabs (70% viewport) -->
      <Tabs :default-value="activeTab" @update:model-value="(val) => activeTab = val" class="flex-1 flex flex-col min-h-0 h-[70%]">
        <!-- Horizontal scrollable tab list -->
        <TabsList class="w-full justify-start rounded-none border-b bg-background h-auto p-0 flex-shrink-0 overflow-x-auto">
          <TabsTrigger value="instructions" class="data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none px-4 py-3">
            Instructions
          </TabsTrigger>
          <TabsTrigger value="deductions" class="data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none px-4 py-3">
            Deductions
          </TabsTrigger>
          <TabsTrigger value="redemption" class="data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none px-4 py-3">
            Redemption
          </TabsTrigger>
          <TabsTrigger value="payments" class="data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none px-4 py-3">
            Payments
          </TabsTrigger>
          <TabsTrigger value="envelope" class="data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none px-4 py-3">
            Envelope
          </TabsTrigger>
        </TabsList>

        <!-- Scrollable tab content -->
        <div class="flex-1 overflow-y-auto">
          <div class="p-6">
            <TabsContent value="instructions" class="mt-0">
              <!-- Live JSON Preview (always open by default) -->
              <Collapsible v-model:open="jsonPreviewOpen">
                <Card>
                  <CollapsibleTrigger class="w-full">
                    <CardHeader class="cursor-pointer hover:bg-muted/50">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <Code class="h-5 w-5" />
                          <CardTitle>Live JSON Preview</CardTitle>
                        </div>
                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': jsonPreviewOpen }" />
                      </div>
                      <CardDescription>
                        Complete voucher instructions data structure
                      </CardDescription>
                    </CardHeader>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <CardContent>
                      <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(voucherData.instructions, null, 2) }}</code></pre>
                    </CardContent>
                  </CollapsibleContent>
                </Card>
              </Collapsible>
            </TabsContent>

            <TabsContent value="deductions" class="mt-0">
              <!-- Wallet Deduction JSON Preview (open by default) -->
              <Collapsible v-model:open="deductionPreviewOpen">
                <Card>
                  <CollapsibleTrigger class="w-full">
                    <CardHeader class="cursor-pointer hover:bg-muted/50">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <Banknote class="h-5 w-5" />
                          <CardTitle>Wallet Deduction JSON</CardTitle>
                        </div>
                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': deductionPreviewOpen }" />
                      </div>
                      <CardDescription>
                        Wallet deduction breakdown (all amounts in pesos)
                      </CardDescription>
                    </CardHeader>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <CardContent>
                      <div v-if="pricingLoading" class="text-sm text-muted-foreground text-center py-4">
                        Calculating charges...
                      </div>
                      <div v-else-if="!deductionJson" class="text-sm text-muted-foreground text-center py-4">
                        No deduction data available
                      </div>
                      <pre v-else class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(deductionJson, null, 2) }}</code></pre>
                    </CardContent>
                  </CollapsibleContent>
                </Card>
              </Collapsible>
            </TabsContent>

            <TabsContent value="redemption" class="mt-0">
              <!-- Collected Form-Flow Data (open by default) -->
              <div v-if="!voucherData.collected_data || voucherData.collected_data.length === 0" class="text-sm text-muted-foreground text-center py-8">
                No redemption data available. This voucher has not been redeemed yet.
              </div>
              <Collapsible v-else v-model:open="redemptionPreviewOpen">
                <Card>
                  <CollapsibleTrigger class="w-full">
                    <CardHeader class="cursor-pointer hover:bg-muted/50">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <Code class="h-5 w-5" />
                          <CardTitle>Collected Form-Flow Data</CardTitle>
                        </div>
                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': redemptionPreviewOpen }" />
                      </div>
                      <CardDescription>
                        Data collected during voucher redemption
                      </CardDescription>
                    </CardHeader>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <CardContent>
                      <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(voucherData.collected_data, null, 2) }}</code></pre>
                    </CardContent>
                  </CollapsibleContent>
                </Card>
              </Collapsible>
            </TabsContent>

            <TabsContent value="payments" class="mt-0">
              <div class="prose prose-sm">
                <h3>Payments</h3>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
                <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident.</p>
              </div>
            </TabsContent>

            <TabsContent value="envelope" class="mt-0">
              <div class="prose prose-sm">
                <h3>Envelope</h3>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
                <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident.</p>
              </div>
            </TabsContent>
          </div>
        </div>
      </Tabs>
    </SheetContent>
  </Sheet>
</template>
