<script setup lang="ts">
import { ref, computed } from 'vue';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import VoucherOverviewTab from './VoucherOverviewTab.vue';
import VoucherInstructionsTab from './VoucherInstructionsTab.vue';
import VoucherRedemptionTab from './VoucherRedemptionTab.vue';
import VoucherPaymentsTab from './VoucherPaymentsTab.vue';
import VoucherEnvelopeTab from './VoucherEnvelopeTab.vue';

interface Props {
  open: boolean;
  voucherData: any;
  settlement?: any;
  envelope?: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

// Active tab state
const activeTab = ref('overview');

// Determine which tabs to show based on voucher state
const visibleTabs = computed(() => {
  const tabs = [
    { value: 'overview', label: 'Overview', visible: true },
    { value: 'instructions', label: 'Instructions', visible: true },
    { value: 'redemption', label: 'Redemption', visible: !!props.voucherData?.full_data?.redeemed_at },
    { value: 'payments', label: 'Payments', visible: isPayableOrSettlement.value },
    { value: 'envelope', label: 'Envelope', visible: !!props.envelope },
  ];
  
  return tabs.filter(tab => tab.visible);
});

const isPayableOrSettlement = computed(() => {
  const type = props.voucherData?.voucher_type;
  return type === 'payable' || type === 'settlement';
});

const handleOpenChange = (value: boolean) => {
  emit('update:open', value);
  
  // Reset to overview when closing
  if (!value) {
    activeTab.value = 'overview';
  }
};
</script>

<template>
  <Sheet :open="open" @update:open="handleOpenChange">
    <SheetContent 
      side="bottom" 
      class="h-[70vh] p-0 flex flex-col"
    >
      <!-- Header with drag handle -->
      <div class="flex-shrink-0">
        <div class="w-12 h-1 bg-muted rounded-full mx-auto mt-2 mb-4" />
        <SheetHeader class="px-6 pb-4">
          <SheetTitle>Voucher Details</SheetTitle>
          <SheetDescription>
            View comprehensive information about this voucher
          </SheetDescription>
        </SheetHeader>
      </div>

      <!-- Tabs with scrollable content -->
      <Tabs v-model="activeTab" class="flex-1 flex flex-col min-h-0">
        <!-- Horizontal scrollable tab list -->
        <TabsList class="w-full justify-start rounded-none border-b bg-background h-auto p-0 flex-shrink-0 overflow-x-auto">
          <TabsTrigger
            v-for="tab in visibleTabs"
            :key="tab.value"
            :value="tab.value"
            class="data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none px-4 py-3 whitespace-nowrap"
          >
            {{ tab.label }}
          </TabsTrigger>
        </TabsList>

        <!-- Scrollable tab content -->
        <ScrollArea class="flex-1">
          <div class="p-6">
            <TabsContent value="overview" class="mt-0">
              <VoucherOverviewTab 
                :voucher-data="voucherData"
                :settlement="settlement"
              />
            </TabsContent>

            <TabsContent value="instructions" class="mt-0">
              <VoucherInstructionsTab 
                :instructions="voucherData?.full_data?.instructions"
              />
            </TabsContent>

            <TabsContent value="redemption" class="mt-0">
              <VoucherRedemptionTab 
                :voucher-data="voucherData"
              />
            </TabsContent>

            <TabsContent value="payments" class="mt-0">
              <VoucherPaymentsTab 
                :settlement="settlement"
              />
            </TabsContent>

            <TabsContent value="envelope" class="mt-0">
              <VoucherEnvelopeTab 
                :envelope="envelope"
              />
            </TabsContent>
          </div>
        </ScrollArea>
      </Tabs>
    </SheetContent>
  </Sheet>
</template>
