<script setup lang="ts">
import { ref, computed } from 'vue';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Banknote, ChevronDown, Code, Lock, Send, Ban, RotateCcw, Loader2 } from 'lucide-vue-next';
import RedemptionSummary from './RedemptionSummary.vue';
import DeductionBreakdown from './DeductionBreakdown.vue';
import PaymentTimeline from './PaymentTimeline.vue';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import VoucherInstructionsForm from '@/components/voucher/forms/VoucherInstructionsForm.vue';
import { 
  EnvelopeStatusCard, 
  EnvelopeChecklistCard, 
  EnvelopeAttachmentsCard,
  EnvelopeSignalsCard,
  EnvelopePayloadCard,
  EnvelopeAuditLog,
} from '@/components/envelope';

interface Props {
  open: boolean;
  voucherData: any;
  inputFieldOptions?: any[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'paymentConfirmed'): void;
}>();

// Active tab state
const activeTab = ref('instructions');

// Collapsible state for JSON Preview (open by default)
const jsonPreviewOpen = ref(true);
const deductionPreviewOpen = ref(false);
const redemptionPreviewOpen = ref(false);

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

// Transform instructions for VoucherInstructionsForm (same logic as Show.vue)
const instructionsFormData = computed(() => {
  const inst = props.voucherData.instructions;
  if (!inst) {
    return {
      amount: 0,
      count: 1,
      prefix: '',
      mask: '',
      ttlDays: null,
      selectedInputFields: [],
      validationSecret: '',
      validationMobile: '',
      feedbackEmail: '',
      feedbackMobile: '',
      feedbackWebhook: '',
      riderMessage: '',
      riderUrl: '',
      riderRedirectTimeout: null,
      riderSplash: '',
      riderSplashTimeout: null,
      locationValidation: null,
      timeValidation: null,
    };
  }

  // Parse TTL from ISO 8601 duration (e.g., P30D)
  let ttlDays = null;
  if (inst.ttl) {
    const match = inst.ttl.match(/P(\\d+)D/);
    ttlDays = match ? parseInt(match[1]) : null;
  }

  return {
    amount: inst.cash?.amount || 0,
    count: inst.count || 1,
    prefix: inst.prefix || '',
    mask: inst.mask || '',
    ttlDays,
    selectedInputFields: inst.inputs?.fields || [],
    validationSecret: inst.cash?.validation?.secret || '',
    validationMobile: inst.cash?.validation?.mobile || '',
    feedbackEmail: inst.feedback?.email || '',
    feedbackMobile: inst.feedback?.mobile || '',
    feedbackWebhook: inst.feedback?.webhook || '',
    riderMessage: inst.rider?.message || '',
    riderUrl: inst.rider?.url || '',
    riderRedirectTimeout: inst.rider?.redirect_timeout ?? null,
    riderSplash: inst.rider?.splash || '',
    riderSplashTimeout: inst.rider?.splash_timeout ?? null,
    locationValidation: inst.validation?.location || null,
    timeValidation: inst.validation?.time || null,
  };
});

const handleOpenChange = (value: boolean) => {
  emit('update:open', value);
  console.log('Sheet open changed to:', value);
};

// Envelope computed properties
const hasEnvelope = computed(() => !!props.voucherData.envelope);
const envelope = computed(() => props.voucherData.envelope);
const canUpload = computed(() => envelope.value?.status_helpers?.can_edit ?? false);

// Envelope actions
const envelopeLoading = ref(false);

const handleLock = async () => {
  if (!confirm('Lock this envelope? This will freeze all changes.')) return;
  
  envelopeLoading.value = true;
  try {
    await window.location.assign(`/vouchers/${props.voucherData.code}#lock`);
  } finally {
    envelopeLoading.value = false;
  }
};

const handleSettle = async () => {
  if (!confirm('Settle this envelope? This action is final and cannot be undone.')) return;
  
  envelopeLoading.value = true;
  try {
    await window.location.assign(`/vouchers/${props.voucherData.code}#settle`);
  } finally {
    envelopeLoading.value = false;
  }
};

const handleCancel = async () => {
  const reason = prompt('Enter cancellation reason:');
  if (!reason) return;
  
  envelopeLoading.value = true;
  try {
    await window.location.assign(`/vouchers/${props.voucherData.code}#cancel`);
  } finally {
    envelopeLoading.value = false;
  }
};

const handleReopen = async () => {
  const reason = prompt('Enter reason for reopening:');
  if (!reason) return;
  
  envelopeLoading.value = true;
  try {
    await window.location.assign(`/vouchers/${props.voucherData.code}#reopen`);
  } finally {
    envelopeLoading.value = false;
  }
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
      <!-- Hidden accessibility header -->
      <SheetHeader class="sr-only">
        <SheetTitle>Voucher {{ voucherData.code }} Details</SheetTitle>
        <SheetDescription>
          View voucher instructions, deductions, redemption data, payments, and envelope information
        </SheetDescription>
      </SheetHeader>
      
      <!-- Header with drag handle (30% viewport) -->
      <div class="flex-shrink-0 h-[30%] flex flex-col items-center justify-center">
        <div class="w-12 h-1 bg-muted rounded-full mx-auto mb-8" />
        <div class="text-6xl font-bold tracking-wider text-primary">
          {{ voucherData.code }}
        </div>
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
              <!-- VoucherInstructionsForm (same as desktop /vouchers/{code} view) -->
              <VoucherInstructionsForm
                v-if="voucherData.instructions"
                :model-value="instructionsFormData"
                :input-field-options="inputFieldOptions || []"
                :readonly="true"
                :show-count-field="false"
                :show-json-preview="true"
              />
              <div v-else class="text-sm text-muted-foreground text-center py-8">
                No instructions available for this voucher.
              </div>
            </TabsContent>

            <TabsContent value="deductions" class="mt-0">
              <div v-if="pricingLoading" class="text-sm text-muted-foreground text-center py-8">
                Calculating charges...
              </div>
              <div v-else class="space-y-6">
                <!-- Formatted Deduction Breakdown -->
                <DeductionBreakdown 
                  :deduction-data="deductionJson" 
                  :voucher-status="voucherData.status"
                />
                
                <!-- Raw JSON Data (Collapsible) -->
                <Collapsible v-model:open="deductionPreviewOpen">
                  <Card>
                    <CollapsibleTrigger class="w-full">
                      <CardHeader class="cursor-pointer hover:bg-muted/50">
                        <div class="flex items-center justify-between">
                          <div class="flex items-center gap-2">
                            <Code class="h-5 w-5" />
                            <CardTitle>Raw Deduction Data (JSON)</CardTitle>
                          </div>
                          <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': deductionPreviewOpen }" />
                        </div>
                        <CardDescription>
                          Technical data from pricing API
                        </CardDescription>
                      </CardHeader>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <CardContent>
                        <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(deductionJson, null, 2) }}</code></pre>
                      </CardContent>
                    </CollapsibleContent>
                  </Card>
                </Collapsible>
              </div>
            </TabsContent>

            <TabsContent value="redemption" class="mt-0">
              <div v-if="!voucherData.redemption_summary" class="text-sm text-muted-foreground text-center py-8">
                No redemption data available. This voucher has not been redeemed yet.
              </div>
              <div v-else class="space-y-6">
                <!-- Formatted Redemption Summary -->
                <RedemptionSummary :redemption-data="voucherData.redemption_summary" />
                
                <!-- Raw JSON Data (Collapsible) -->
                <Collapsible v-model:open="redemptionPreviewOpen">
                  <Card>
                    <CollapsibleTrigger class="w-full">
                      <CardHeader class="cursor-pointer hover:bg-muted/50">
                        <div class="flex items-center justify-between">
                          <div class="flex items-center gap-2">
                            <Code class="h-5 w-5" />
                            <CardTitle>Raw Redemption Data (JSON)</CardTitle>
                          </div>
                          <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': redemptionPreviewOpen }" />
                        </div>
                        <CardDescription>
                          Technical data collected during redemption
                        </CardDescription>
                      </CardHeader>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <CardContent>
                        <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs"><code>{{ JSON.stringify(voucherData.redemption_summary, null, 2) }}</code></pre>
                      </CardContent>
                    </CollapsibleContent>
                  </Card>
                </Collapsible>
              </div>
            </TabsContent>

            <TabsContent value="payments" class="mt-0">
              <PaymentTimeline 
                :transactions="voucherData.wallet_transactions || []" 
                :voucher-code="voucherData.code"
                :is-owner="true"
                :target-amount="voucherData.target_amount"
                :voucher-type="voucherData.voucher_type"
                @payment-confirmed="emit('paymentConfirmed')"
              />
            </TabsContent>

            <TabsContent value="envelope" class="mt-0">
              <div v-if="!hasEnvelope" class="text-center py-8">
                <p class="text-sm text-muted-foreground">No settlement envelope attached</p>
              </div>
              
              <div v-else class="space-y-4">
                <!-- Status Card with action buttons -->
                <EnvelopeStatusCard :envelope="envelope" :show-actions="true">
                  <template #actions="{ canLock, canSettle, canCancel, canReopen, isTerminal }">
                    <div v-if="!isTerminal" class="flex flex-wrap gap-2 pt-3 border-t">
                      <!-- Lock Button -->
                      <Button 
                        v-if="canLock" 
                        variant="default" 
                        size="sm"
                        @click="handleLock"
                        :disabled="envelopeLoading"
                        class="flex-1"
                      >
                        <Loader2 v-if="envelopeLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Lock v-else class="mr-2 h-4 w-4" />
                        Lock
                      </Button>
                      
                      <!-- Settle Button -->
                      <Button 
                        v-if="canSettle" 
                        variant="default" 
                        size="sm"
                        @click="handleSettle"
                        :disabled="envelopeLoading"
                        class="flex-1"
                      >
                        <Loader2 v-if="envelopeLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Settle
                      </Button>
                      
                      <!-- Cancel Button -->
                      <Button 
                        v-if="canCancel" 
                        variant="outline" 
                        size="sm"
                        @click="handleCancel"
                        :disabled="envelopeLoading"
                        class="flex-1"
                      >
                        <Ban class="mr-2 h-4 w-4" />
                        Cancel
                      </Button>
                      
                      <!-- Reopen Button -->
                      <Button 
                        v-if="canReopen" 
                        variant="outline" 
                        size="sm"
                        @click="handleReopen"
                        :disabled="envelopeLoading"
                        class="flex-1"
                      >
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Reopen
                      </Button>
                    </div>
                  </template>
                </EnvelopeStatusCard>
                
                <!-- Checklist (full width on mobile) -->
                <EnvelopeChecklistCard 
                  v-if="envelope.checklist_items?.length" 
                  :items="envelope.checklist_items" 
                />
                
                <!-- Signals (full width on mobile, read-only) -->
                <EnvelopeSignalsCard 
                  v-if="envelope.signals?.length" 
                  :signals="envelope.signals"
                  :blocking-signals="envelope.computed_flags?.blocking_signals ?? []"
                  :readonly="true"
                />
                
                <!-- Attachments (read-only, no upload button) -->
                <EnvelopeAttachmentsCard 
                  :attachments="envelope.attachments ?? []"
                  :readonly="true"
                />
                
                <!-- Payload (read-only) -->
                <EnvelopePayloadCard 
                  :payload="envelope.payload || {}" 
                  :version="envelope.payload_version"
                  :context="envelope.context"
                  :voucher-code="voucherData.code"
                  :readonly="true"
                />
                
                <!-- Audit Log -->
                <EnvelopeAuditLog 
                  v-if="envelope.audit_logs?.length" 
                  :entries="envelope.audit_logs" 
                />
              </div>
            </TabsContent>
          </div>
        </div>
      </Tabs>
    </SheetContent>
  </Sheet>
</template>

<style scoped>
/* Hide placeholders in readonly form inputs without modifying shared component */
:deep(input::placeholder),
:deep(textarea::placeholder) {
  opacity: 0;
}
</style>
