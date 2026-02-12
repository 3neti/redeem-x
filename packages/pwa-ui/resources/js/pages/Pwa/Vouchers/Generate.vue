<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet';
import { ArrowLeft, Wallet, Plus, Settings as SettingsIcon, Loader2 } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';

interface Campaign {
  id: number;
  name: string;
  slug: string;
  instructions: any;
}

interface InputFieldOption {
  value: string;
  label: string;
  icon?: string;
}

interface Props {
  campaigns?: Campaign[];
  inputFieldOptions?: InputFieldOption[];
  walletBalance?: number;
  formattedBalance?: string;
}

const props = withDefaults(defineProps<Props>(), {
  campaigns: () => [],
  inputFieldOptions: () => [],
  walletBalance: 0,
  formattedBalance: '₱0.00',
});

const { toast } = useToast();

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

// Core voucher state
const amount = ref<number | null>(null);
const count = ref<number>(1);
const voucherType = ref<'redeemable' | 'payable' | 'settlement'>('redeemable');
const selectedInputFields = ref<string[]>([]);

// Settlement/Payable specific
const targetAmount = ref<number | null>(null);
const interestRate = ref<number>(0);
const payee = ref<string>('');

// Advanced configuration
const validationSecret = ref<string>('');
const locationValidation = ref<any>(null);
const timeValidation = ref<any>(null);

// Feedback channels
const feedbackEmail = ref<string>('');
const feedbackMobile = ref<string>('');
const feedbackWebhook = ref<string>('');

// Rider config
const riderMessage = ref<string>('');
const riderUrl = ref<string>('');
const riderRedirectTimeout = ref<number | null>(null);
const riderSplash = ref<string>('');
const riderSplashTimeout = ref<number | null>(null);

// Settlement envelope
const envelopeConfig = ref<any>(null);

// Rail & Fees
const settlementRail = ref<'auto' | 'INSTAPAY' | 'PESONET' | null>(null);
const feeStrategy = ref<'absorb' | 'include' | 'add'>('absorb');

// Campaign state
const selectedCampaignId = ref<string>('');
const selectedCampaign = ref<Campaign | null>(null);

// UI state
const loading = ref(false);
const error = ref<string | null>(null);

// Sheet state management (following plan architecture)
const sheetState = ref({
  campaign: { open: false },
  voucherType: { open: false },
  inputs: { open: false },
  validation: { open: false, activeTab: 'location' as 'location' | 'time' | 'secret' | 'payee' },
  feedback: { open: false },
  rider: { open: false },
  envelope: { open: false },
  railFees: { open: false },
  options: { open: false }, // Menu sheet
});

// ============================================================================
// COMPUTED PROPERTIES
// ============================================================================

// Smart payee detection (from Portal.vue)
const payeeType = computed(() => {
  const normalized = payee.value.trim();
  if (!normalized || normalized.toUpperCase() === 'CASH') return 'anyone';
  if (/^(\+|09|\+63)/.test(normalized)) return 'mobile';
  return 'vendor';
});

const normalizedPayee = computed(() => {
  const normalized = payee.value.trim();
  return normalized.toUpperCase() === 'CASH' ? '' : normalized;
});

// Voucher type display
const voucherTypeDisplay = computed(() => {
  switch (voucherType.value) {
    case 'payable': return 'Payable';
    case 'settlement': return 'Settlement';
    case 'redeemable': return 'Redeemable';
    default: return 'Redeemable';
  }
});

// Input field selection summary
const inputFieldsSummary = computed(() => {
  if (selectedInputFields.value.length === 0) return 'None selected';
  if (selectedInputFields.value.length === 1) return `${selectedInputFields.value.length} input`;
  return `${selectedInputFields.value.length} inputs`;
});

// Validation summary
const validationSummary = computed(() => {
  const parts = [];
  if (locationValidation.value) parts.push('Location');
  if (timeValidation.value) parts.push('Time');
  if (validationSecret.value) parts.push('Secret');
  if (normalizedPayee.value) parts.push(payeeType.value === 'mobile' ? 'Mobile' : 'Vendor');
  return parts.length > 0 ? parts.join(', ') : 'None';
});

// Feedback summary
const feedbackSummary = computed(() => {
  const parts = [];
  if (feedbackEmail.value) parts.push('Email');
  if (feedbackMobile.value) parts.push('SMS');
  if (feedbackWebhook.value) parts.push('Webhook');
  return parts.length > 0 ? parts.join(', ') : 'None';
});

// Campaign display
const campaignDisplay = computed(() => {
  return selectedCampaign.value?.name || 'No campaign';
});

// Estimated cost (placeholder - will integrate useChargeBreakdown)
const estimatedCost = computed(() => {
  if (!amount.value) return 0;
  const baseAmount = amount.value * count.value;
  const inputCost = selectedInputFields.value.length * 0.50; // Placeholder
  return baseAmount + inputCost;
});

// Generate button state
const canGenerate = computed(() => {
  if (loading.value) return false;
  if (voucherType.value === 'redeemable') {
    return amount.value !== null && amount.value > 0;
  }
  if (voucherType.value === 'payable') {
    return targetAmount.value !== null && targetAmount.value > 0;
  }
  if (voucherType.value === 'settlement') {
    return amount.value !== null && amount.value > 0 && targetAmount.value !== null && targetAmount.value > 0;
  }
  return false;
});

// ============================================================================
// METHODS
// ============================================================================

// Open sheets
const openSheet = (sheet: keyof typeof sheetState.value) => {
  sheetState.value[sheet].open = true;
};

// Generate voucher
const handleGenerate = async () => {
  if (!canGenerate.value) return;
  
  loading.value = true;
  error.value = null;
  
  try {
    // TODO: Implement actual API call (will copy from Portal.vue)
    toast({
      title: 'Generating voucher...',
      description: 'This is a placeholder. API integration coming next.',
    });
    
    // Placeholder success
    setTimeout(() => {
      toast({
        title: 'Voucher generated!',
        description: 'Redirecting to voucher details...',
      });
      loading.value = false;
    }, 1000);
  } catch (err: any) {
    error.value = err.message || 'Generation failed';
    toast({
      title: 'Error',
      description: error.value,
      variant: 'destructive',
    });
    loading.value = false;
  }
};

// Reset state
const resetState = () => {
  amount.value = null;
  count.value = 1;
  voucherType.value = 'redeemable';
  selectedInputFields.value = [];
  targetAmount.value = null;
  interestRate.value = 0;
  payee.value = '';
  validationSecret.value = '';
  locationValidation.value = null;
  timeValidation.value = null;
  feedbackEmail.value = '';
  feedbackMobile.value = '';
  feedbackWebhook.value = '';
  selectedCampaignId.value = '';
  selectedCampaign.value = null;
};

// Watch for settlement type changes (from Portal.vue)
watch(voucherType, (newType) => {
  if (newType === 'settlement' && amount.value && interestRate.value >= 0) {
    targetAmount.value = parseFloat((amount.value * (1 + interestRate.value / 100)).toFixed(2));
  }
  if (newType === 'payable') {
    amount.value = 0;
  }
});

watch([amount, interestRate], ([newAmount, newRate]) => {
  if (voucherType.value === 'settlement' && newAmount && newRate >= 0) {
    targetAmount.value = parseFloat((newAmount * (1 + newRate / 100)).toFixed(2));
  }
});
</script>

<template>
  <PwaLayout title="Generate Voucher">
    <!-- Header -->
    <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
          <Button variant="ghost" size="icon" @click="router.visit('/pwa/portal')">
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <div>
            <h1 class="text-lg font-semibold">Generate</h1>
            <p class="text-xs text-muted-foreground">{{ voucherTypeDisplay }}</p>
          </div>
        </div>
        <Button variant="ghost" size="icon" @click="openSheet('options')">
          <SettingsIcon class="h-5 w-5" />
        </Button>
      </div>
    </header>

    <!-- Main Content -->
    <div class="flex flex-col h-[calc(100vh-64px-56px)]">
      <!-- Scrollable Config Summary -->
      <div class="flex-1 overflow-y-auto p-4 space-y-3">
        <!-- Campaign Indicator -->
        <Card v-if="selectedCampaign" class="p-3 bg-primary/5 border-primary/20">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <p class="text-xs text-muted-foreground">Campaign</p>
              <p class="text-sm font-medium">{{ campaignDisplay }}</p>
            </div>
            <Button variant="ghost" size="sm" @click="openSheet('campaign')">
              Change
            </Button>
          </div>
        </Card>

        <!-- Config Summary Chips -->
        <div class="space-y-2">
          <!-- Input Fields -->
          <div class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 cursor-pointer" @click="openSheet('inputs')">
            <div>
              <p class="text-xs text-muted-foreground">Input Fields</p>
              <p class="text-sm font-medium">{{ inputFieldsSummary }}</p>
            </div>
            <Plus class="h-4 w-4 text-muted-foreground" />
          </div>

          <!-- Validation -->
          <div class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 cursor-pointer" @click="openSheet('validation')">
            <div>
              <p class="text-xs text-muted-foreground">Validation</p>
              <p class="text-sm font-medium">{{ validationSummary }}</p>
            </div>
            <Plus class="h-4 w-4 text-muted-foreground" />
          </div>

          <!-- Feedback -->
          <div class="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 cursor-pointer" @click="openSheet('feedback')">
            <div>
              <p class="text-xs text-muted-foreground">Notifications</p>
              <p class="text-sm font-medium">{{ feedbackSummary }}</p>
            </div>
            <Plus class="h-4 w-4 text-muted-foreground" />
          </div>
        </div>

        <!-- Amount Display (Large) -->
        <div class="text-center py-8">
          <p class="text-sm text-muted-foreground mb-2">Amount</p>
          <p class="text-5xl font-bold tabular-nums">
            {{ amount ? `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '₱0.00' }}
          </p>
          <p v-if="count > 1" class="text-sm text-muted-foreground mt-2">
            × {{ count }} vouchers
          </p>
        </div>

        <!-- Quick Amount Grid -->
        <div class="grid grid-cols-3 gap-2">
          <Button variant="outline" @click="amount = 100">₱100</Button>
          <Button variant="outline" @click="amount = 500">₱500</Button>
          <Button variant="outline" @click="amount = 1000">₱1K</Button>
          <Button variant="outline" @click="amount = 2000">₱2K</Button>
          <Button variant="outline" @click="amount = 5000">₱5K</Button>
          <Button variant="outline" @click="amount = 10000">₱10K</Button>
        </div>
      </div>

      <!-- Fixed Bottom Section -->
      <div class="border-t bg-background p-4 space-y-3">
        <!-- Wallet Balance & Cost -->
        <div class="flex items-center justify-between text-sm">
          <div class="flex items-center gap-2 text-muted-foreground">
            <Wallet class="h-4 w-4" />
            <span>{{ formattedBalance }}</span>
          </div>
          <div v-if="amount" class="text-muted-foreground">
            Cost: ₱{{ estimatedCost.toFixed(2) }}
          </div>
        </div>

        <!-- Generate Button -->
        <Button
          size="lg"
          class="w-full"
          :disabled="!canGenerate"
          @click="handleGenerate"
        >
          <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
          Generate {{ count > 1 ? `${count} Vouchers` : 'Voucher' }}
        </Button>
      </div>
    </div>

    <!-- ========================================================================
         BOTTOM SHEETS (Placeholders - will implement in subsequent phases)
         ======================================================================== -->
    
    <!-- Options Menu Sheet -->
    <Sheet v-model:open="sheetState.options.open">
      <SheetContent side="bottom" class="h-auto max-h-[80vh]">
        <SheetHeader>
          <SheetTitle>Options</SheetTitle>
          <SheetDescription>Configure your voucher</SheetDescription>
        </SheetHeader>
        <div class="space-y-2 py-4">
          <Button variant="ghost" class="w-full justify-start" @click="openSheet('campaign')">
            Campaign Template
          </Button>
          <Button variant="ghost" class="w-full justify-start" @click="openSheet('voucherType')">
            Voucher Type
          </Button>
          <Button variant="ghost" class="w-full justify-start" @click="openSheet('rider')">
            Rider Config
          </Button>
          <Button variant="ghost" class="w-full justify-start" @click="openSheet('envelope')">
            Settlement Envelope
          </Button>
          <Button variant="ghost" class="w-full justify-start" @click="openSheet('railFees')">
            Rail & Fees
          </Button>
        </div>
        <SheetFooter>
          <Button variant="outline" class="w-full" @click="sheetState.options.open = false">
            Close
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <!-- Placeholder sheets (will implement in phases 3-10) -->
    <Sheet v-model:open="sheetState.campaign.open">
      <SheetContent side="bottom">
        <SheetHeader>
          <SheetTitle>Campaign</SheetTitle>
          <SheetDescription>Select a campaign template</SheetDescription>
        </SheetHeader>
        <div class="py-4">
          <p class="text-sm text-muted-foreground">Coming in Phase 3</p>
        </div>
      </SheetContent>
    </Sheet>

    <!-- More placeholder sheets... -->
  </PwaLayout>
</template>
