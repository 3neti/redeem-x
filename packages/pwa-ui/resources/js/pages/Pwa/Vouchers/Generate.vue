<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
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
const page = usePage();

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
const locationValidation = ref<any>({ latitude: null, longitude: null, radius: null });
const timeValidation = ref<any>({ start_time: '', end_time: '', timezone: 'Asia/Manila' });

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

// Payee label (from Portal.vue)
const payeeLabel = computed(() => {
  switch (voucherType.value) {
    case 'payable': return 'Collect from';
    case 'settlement': return 'Lend to';
    case 'redeemable':
    default: return 'Pay to';
  }
});

// Payee context help (from Portal.vue)
const payeeContextHelp = computed(() => {
  switch (payeeType.value) {
    case 'anyone': return 'Anyone can redeem';
    case 'mobile': return `Restricted to: ${normalizedPayee.value}`;
    case 'vendor': return `Merchant: ${normalizedPayee.value}`;
    default: return 'Anyone can redeem';
  }
});

// Payee type display
const payeeTypeDisplay = computed(() => {
  switch (payeeType.value) {
    case 'anyone': return 'Anyone';
    case 'mobile': return 'Mobile Number';
    case 'vendor': return 'Vendor Alias';
    default: return 'Unknown';
  }
});

// Payee type description
const payeeTypeDescription = computed(() => {
  switch (payeeType.value) {
    case 'anyone': return 'No restriction - anyone with the code can redeem';
    case 'mobile': return 'Restricted to specific mobile number (OTP required)';
    case 'vendor': return 'Restricted to vendor with this alias';
    default: return '';
  }
});

// Campaign display
const campaignDisplay = computed(() => {
  return selectedCampaign.value?.name || 'No campaign';
});

// Instructions for pricing (Phase 11)
const instructionsForPricing = computed(() => ({
  cash: {
    amount: amount.value || 0,
    currency: 'PHP',
  },
  inputs: {
    fields: selectedInputFields.value,
  },
  count: count.value,
}));

// Real-time pricing using composable (Phase 11)
const { breakdown, loading: pricingLoading, totalDeduction } = useChargeBreakdown(
  instructionsForPricing,
  { debounce: 500, autoCalculate: true }
);

const estimatedCost = computed(() => totalDeduction.value);

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

// Generate voucher (Phase 11 - from Portal.vue)
const handleGenerate = async () => {
  if (!canGenerate.value) return;
  
  // STEP 1: Check authentication
  const isAuthenticated = (page.props as any).auth?.user;
  if (!isAuthenticated) {
    toast({
      title: 'Sign in required',
      description: 'Please sign in to generate vouchers',
    });
    router.visit('/login', {
      data: { redirect: '/pwa/vouchers/generate' },
    });
    return;
  }
  
  // STEP 2: Check balance
  if (estimatedCost.value > props.walletBalance) {
    error.value = `Insufficient balance. You have ${props.formattedBalance}, need ~₱${estimatedCost.value.toFixed(2)}`;
    toast({
      title: 'Insufficient balance',
      description: error.value,
      variant: 'destructive',
    });
    return;
  }
  
  // STEP 3: Build request payload
  loading.value = true;
  error.value = null;
  
  try {
    const requestData: any = {
      amount: voucherType.value === 'payable' ? 0 : amount.value,
      count: count.value,
      input_fields: selectedInputFields.value,
    };
    
    // Add validation
    if (payeeType.value === 'mobile' && normalizedPayee.value) {
      requestData.validation_mobile = normalizedPayee.value;
    }
    if (payeeType.value === 'vendor' && normalizedPayee.value) {
      requestData.validation_payable = normalizedPayee.value;
    }
    if (validationSecret.value) {
      requestData.validation_secret = validationSecret.value;
    }
    if (locationValidation.value?.latitude && locationValidation.value?.longitude) {
      requestData.validation_location = locationValidation.value;
    }
    if (timeValidation.value?.start_time && timeValidation.value?.end_time) {
      requestData.validation_time = timeValidation.value;
    }
    
    // Add voucher type for payable/settlement
    if (payeeType.value === 'anyone' && voucherType.value !== 'redeemable') {
      requestData.voucher_type = voucherType.value;
      if (targetAmount.value) {
        requestData.target_amount = targetAmount.value;
      }
    }
    
    // Add feedback
    if (feedbackEmail.value) requestData.feedback_email = feedbackEmail.value;
    if (feedbackMobile.value) requestData.feedback_mobile = feedbackMobile.value;
    if (feedbackWebhook.value) requestData.feedback_webhook = feedbackWebhook.value;
    
    // Add rider
    if (riderMessage.value || riderUrl.value) {
      requestData.rider = {};
      if (riderMessage.value) requestData.rider.message = riderMessage.value;
      if (riderUrl.value) requestData.rider.url = riderUrl.value;
      if (riderRedirectTimeout.value !== null) requestData.rider.redirect_timeout = riderRedirectTimeout.value;
      if (riderSplash.value) requestData.rider.splash = riderSplash.value;
      if (riderSplashTimeout.value !== null) requestData.rider.splash_timeout = riderSplashTimeout.value;
    }
    
    // Add settlement rail and fee strategy
    if (settlementRail.value && settlementRail.value !== 'auto') {
      requestData.settlement_rail = settlementRail.value;
    }
    if (feeStrategy.value !== 'absorb') {
      requestData.fee_strategy = feeStrategy.value;
    }
    
    const headers: Record<string, string> = {
      'Idempotency-Key': `pwa-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    };
    
    const response = await axios.post('/api/v1/vouchers', requestData, { headers });
    
    // API wraps data in response.data.data structure
    const apiData = response.data.data;
    if (apiData && apiData.vouchers && apiData.vouchers.length > 0) {
      const voucherCount = apiData.vouchers.length;
      const firstVoucher = apiData.vouchers[0];
      
      toast({
        title: `${voucherCount} Voucher${voucherCount > 1 ? 's' : ''} created!`,
        description: voucherCount === 1 ? `Code: ${firstVoucher.code}` : `${voucherCount} codes generated`,
      });
      
      // Redirect to voucher show page for single voucher, vouchers list for multiple
      if (voucherCount === 1) {
        router.visit(`/pwa/vouchers/${firstVoucher.code}`);
      } else {
        router.visit('/pwa/vouchers');
      }
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Generation failed';
    toast({
      title: 'Error',
      description: error.value,
      variant: 'destructive',
    });
  } finally {
    loading.value = false;
  }
};

// Apply campaign (Phase 3)
const applyCampaign = (campaign: Campaign | null) => {
  if (!campaign) {
    // Blank template - reset all
    selectedCampaignId.value = '';
    selectedCampaign.value = null;
    resetState();
    sheetState.value.campaign.open = false;
    toast({
      title: 'Blank template selected',
      description: 'All settings cleared',
    });
    return;
  }
  
  // Apply campaign instructions
  selectedCampaignId.value = campaign.id.toString();
  selectedCampaign.value = campaign;
  
  const instructions = campaign.instructions;
  if (instructions) {
    // Apply cash amount
    if (instructions.cash?.amount) {
      amount.value = instructions.cash.amount;
    }
    
    // Apply input fields
    if (instructions.inputs?.fields) {
      selectedInputFields.value = instructions.inputs.fields;
    }
    
    // Apply validation
    if (instructions.validation) {
      if (instructions.validation.location) locationValidation.value = instructions.validation.location;
      if (instructions.validation.time) timeValidation.value = instructions.validation.time;
      if (instructions.validation.secret) validationSecret.value = instructions.validation.secret;
      if (instructions.validation.mobile) payee.value = instructions.validation.mobile;
      if (instructions.validation.payable) payee.value = instructions.validation.payable;
    }
    
    // Apply feedback
    if (instructions.feedback) {
      if (instructions.feedback.email) feedbackEmail.value = instructions.feedback.email;
      if (instructions.feedback.mobile) feedbackMobile.value = instructions.feedback.mobile;
      if (instructions.feedback.webhook) feedbackWebhook.value = instructions.feedback.webhook;
    }
    
    // Apply rider
    if (instructions.rider) {
      if (instructions.rider.message) riderMessage.value = instructions.rider.message;
      if (instructions.rider.url) riderUrl.value = instructions.rider.url;
      if (instructions.rider.redirect_timeout) riderRedirectTimeout.value = instructions.rider.redirect_timeout;
      if (instructions.rider.splash) riderSplash.value = instructions.rider.splash;
      if (instructions.rider.splash_timeout) riderSplashTimeout.value = instructions.rider.splash_timeout;
    }
    
    // Apply count if present
    if (instructions.count) {
      count.value = instructions.count;
    }
  }
  
  sheetState.value.campaign.open = false;
  toast({
    title: 'Campaign applied',
    description: campaign.name,
  });
};

// Toggle input field (Phase 5)
const toggleInputField = (fieldValue: string) => {
  const index = selectedInputFields.value.indexOf(fieldValue);
  if (index > -1) {
    selectedInputFields.value.splice(index, 1);
  } else {
    selectedInputFields.value.push(fieldValue);
  }
};

// Auto-add tracking for OTP
const autoAddedFields = ref<Set<string>>(new Set());

// Clear rider config (Phase 8)
const clearRider = () => {
  riderMessage.value = '';
  riderUrl.value = '';
  riderRedirectTimeout.value = null;
  riderSplash.value = '';
  riderSplashTimeout.value = null;
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
  locationValidation.value = { latitude: null, longitude: null, radius: null };
  timeValidation.value = { start_time: '', end_time: '', timezone: 'Asia/Manila' };
  feedbackEmail.value = '';
  feedbackMobile.value = '';
  feedbackWebhook.value = '';
  selectedCampaignId.value = '';
  selectedCampaign.value = null;
  autoAddedFields.value.clear();
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

// Watch for mobile payee - auto-add OTP (same logic as Portal.vue)
watch(payeeType, (newType, oldType) => {
  if (newType === 'mobile' && oldType !== 'mobile') {
    // Mobile validation enabled - auto-add OTP if not present
    if (!selectedInputFields.value.includes('otp')) {
      selectedInputFields.value.push('otp');
      autoAddedFields.value.add('otp');
      toast({
        title: 'OTP required',
        description: 'OTP input auto-added for mobile validation',
      });
    }
  } else if (newType !== 'mobile' && oldType === 'mobile') {
    // Mobile validation disabled - remove OTP if auto-added
    if (autoAddedFields.value.has('otp')) {
      const index = selectedInputFields.value.indexOf('otp');
      if (index > -1) {
        selectedInputFields.value.splice(index, 1);
      }
      autoAddedFields.value.delete('otp');
    }
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

    <!-- Campaign Selection Sheet (Phase 3) -->
    <Sheet v-model:open="sheetState.campaign.open">
      <SheetContent side="bottom" class="h-[80vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Select Campaign</SheetTitle>
          <SheetDescription>
            Use a saved template to pre-fill all settings
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-3">
          <!-- Blank Template Option -->
          <Card
            @click="applyCampaign(null)"
            :class="[
              'p-4 cursor-pointer transition-all hover:bg-muted/50',
              !selectedCampaignId && 'ring-2 ring-primary bg-primary/5'
            ]"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold">Blank Template</p>
                <p class="text-sm text-muted-foreground">Start from scratch</p>
              </div>
              <div v-if="!selectedCampaignId" class="h-6 w-6 rounded-full bg-primary flex items-center justify-center">
                <span class="text-primary-foreground text-xs">✓</span>
              </div>
            </div>
          </Card>
          
          <!-- Campaign List -->
          <Card
            v-for="campaign in props.campaigns"
            :key="campaign.id"
            @click="applyCampaign(campaign)"
            :class="[
              'p-4 cursor-pointer transition-all hover:bg-muted/50',
              selectedCampaignId === campaign.id.toString() && 'ring-2 ring-primary bg-primary/5'
            ]"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="font-semibold">{{ campaign.name }}</p>
                <p class="text-sm text-muted-foreground mt-1">
                  {{ campaign.instructions?.cash?.amount ? `₱${campaign.instructions.cash.amount.toLocaleString()}` : 'Variable amount' }}
                </p>
                <p class="text-xs text-muted-foreground mt-1">
                  {{ campaign.instructions?.inputs?.fields?.length || 0 }} inputs • 
                  {{ campaign.instructions?.validation ? 'Validated' : 'No validation' }}
                </p>
              </div>
              <div v-if="selectedCampaignId === campaign.id.toString()" class="h-6 w-6 rounded-full bg-primary flex items-center justify-center">
                <span class="text-primary-foreground text-xs">✓</span>
              </div>
            </div>
          </Card>
          
          <!-- Empty State -->
          <div v-if="props.campaigns.length === 0" class="py-12 text-center">
            <p class="text-sm text-muted-foreground">No campaigns yet</p>
            <Button variant="link" @click="router.visit('/settings/campaigns')" class="mt-2">
              Create your first campaign →
            </Button>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.campaign.open = false" class="flex-1">
            Cancel
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <!-- Voucher Type Sheet (Phase 4) -->
    <Sheet v-model:open="sheetState.voucherType.open">
      <SheetContent side="bottom" class="h-auto max-h-[90vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Voucher Type</SheetTitle>
          <SheetDescription>
            Choose the type of voucher to generate
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-4">
          <RadioGroup v-model="voucherType">
            <!-- Redeemable -->
            <div
              :class="[
                'flex items-start space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-all',
                voucherType === 'redeemable' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'
              ]"
              @click="voucherType = 'redeemable'"
            >
              <RadioGroupItem value="redeemable" id="type-redeemable" class="mt-1" />
              <div class="flex-1">
                <Label for="type-redeemable" class="font-semibold text-base cursor-pointer">
                  Redeemable
                </Label>
                <p class="text-sm text-muted-foreground mt-1">
                  Standard one-time redemption voucher. Redeemer receives the full amount.
                </p>
              </div>
            </div>
            
            <!-- Payable -->
            <div
              :class="[
                'flex items-start space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-all',
                voucherType === 'payable' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'
              ]"
              @click="voucherType = 'payable'"
            >
              <RadioGroupItem value="payable" id="type-payable" class="mt-1" />
              <div class="flex-1">
                <Label for="type-payable" class="font-semibold text-base cursor-pointer">
                  Payable
                </Label>
                <p class="text-sm text-muted-foreground mt-1">
                  Accepts payments until target amount is reached. Anyone can contribute.
                </p>
              </div>
            </div>
            
            <!-- Settlement -->
            <div
              :class="[
                'flex items-start space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-all',
                voucherType === 'settlement' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'
              ]"
              @click="voucherType = 'settlement'"
            >
              <RadioGroupItem value="settlement" id="type-settlement" class="mt-1" />
              <div class="flex-1">
                <Label for="type-settlement" class="font-semibold text-base cursor-pointer">
                  Settlement
                </Label>
                <p class="text-sm text-muted-foreground mt-1">
                  Enterprise settlement instrument. Supports multi-payment with interest calculation.
                </p>
              </div>
            </div>
          </RadioGroup>
          
          <!-- Conditional Fields for Payable -->
          <div v-if="voucherType === 'payable'" class="space-y-3 pt-4 border-t">
            <div class="space-y-2">
              <Label for="target-amount">Target Amount</Label>
              <Input
                id="target-amount"
                v-model.number="targetAmount"
                type="number"
                placeholder="Enter target amount"
                min="1"
                step="0.01"
              />
              <p class="text-xs text-muted-foreground">
                Total amount to collect before voucher closes
              </p>
            </div>
          </div>
          
          <!-- Conditional Fields for Settlement -->
          <div v-if="voucherType === 'settlement'" class="space-y-3 pt-4 border-t">
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label for="loan-amount">Loan Amount</Label>
                <Input
                  id="loan-amount"
                  v-model.number="amount"
                  type="number"
                  placeholder="Enter amount"
                  min="1"
                  step="0.01"
                />
              </div>
              <div class="space-y-2">
                <Label for="interest-rate">Interest Rate</Label>
                <Input
                  id="interest-rate"
                  v-model.number="interestRate"
                  type="number"
                  placeholder="0"
                  min="0"
                  max="100"
                  step="0.01"
                />
              </div>
            </div>
            <p class="text-xs text-muted-foreground">
              Target amount: ₱{{ targetAmount?.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) || '0.00' }} 
              (principal + {{ interestRate.toFixed(2) }}% interest)
            </p>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.voucherType.open = false" class="flex-1">
            Cancel
          </Button>
          <Button @click="sheetState.voucherType.open = false" class="flex-1">
            Apply
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Input Fields Sheet (Phase 5) -->
    <Sheet v-model:open="sheetState.inputs.open">
      <SheetContent side="bottom" class="h-[80vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Input Fields</SheetTitle>
          <SheetDescription>
            Select fields to collect during redemption
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-4">
          <!-- Input Field Checkboxes -->
          <div class="space-y-3">
            <div
              v-for="option in props.inputFieldOptions"
              :key="option.value"
              :class="[
                'flex items-center space-x-3 p-4 rounded-lg border-2 cursor-pointer transition-all',
                selectedInputFields.includes(option.value) ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50',
                option.value === 'otp' && autoAddedFields.has('otp') && 'opacity-60'
              ]"
              @click="option.value === 'otp' && autoAddedFields.has('otp') ? null : toggleInputField(option.value)"
            >
              <Checkbox
                :id="`input-${option.value}`"
                :checked="selectedInputFields.includes(option.value)"
                :disabled="option.value === 'otp' && autoAddedFields.has('otp')"
                @click.stop
              />
              <div class="flex-1">
                <Label
                  :for="`input-${option.value}`"
                  :class="[
                    'font-semibold text-base cursor-pointer flex items-center gap-2',
                    option.value === 'otp' && autoAddedFields.has('otp') && 'cursor-not-allowed'
                  ]"
                >
                  <span v-if="option.icon" class="text-xl">{{ option.icon }}</span>
                  {{ option.label }}
                </Label>
                <p v-if="option.value === 'otp' && autoAddedFields.has('otp')" class="text-xs text-muted-foreground mt-1">
                  Required for mobile validation
                </p>
              </div>
            </div>
          </div>
          
          <!-- Empty State -->
          <div v-if="props.inputFieldOptions.length === 0" class="py-12 text-center">
            <p class="text-sm text-muted-foreground">No input fields configured</p>
          </div>
          
          <!-- Info Box -->
          <div v-if="selectedInputFields.length > 0" class="p-4 bg-muted/50 rounded-lg">
            <p class="text-sm font-medium mb-1">Selected: {{ selectedInputFields.length }} field{{ selectedInputFields.length > 1 ? 's' : '' }}</p>
            <p class="text-xs text-muted-foreground">
              Redeemers will need to provide: {{ selectedInputFields.join(', ') }}
            </p>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.inputs.open = false" class="flex-1">
            Cancel
          </Button>
          <Button @click="sheetState.inputs.open = false" class="flex-1">
            Apply ({{ selectedInputFields.length }})
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Validation Sheet (Phase 6) -->
    <Sheet v-model:open="sheetState.validation.open">
      <SheetContent side="bottom" class="h-[85vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Validation Rules</SheetTitle>
          <SheetDescription>
            Add validation rules to restrict redemption
          </SheetDescription>
        </SheetHeader>
        
        <Tabs v-model="sheetState.validation.activeTab" class="flex-1 flex flex-col mt-4">
          <TabsList class="grid w-full grid-cols-4">
            <TabsTrigger value="location">Location</TabsTrigger>
            <TabsTrigger value="time">Time</TabsTrigger>
            <TabsTrigger value="secret">Secret</TabsTrigger>
            <TabsTrigger value="payee">Payee</TabsTrigger>
          </TabsList>
          
          <!-- Location Tab -->
          <TabsContent value="location" class="flex-1 overflow-y-auto mt-4 space-y-4">
            <div class="space-y-3">
              <div class="space-y-2">
                <Label for="location-lat">Latitude</Label>
                <Input
                  id="location-lat"
                  v-model.number="locationValidation.latitude"
                  type="number"
                  placeholder="14.5995"
                  step="0.0001"
                />
              </div>
              <div class="space-y-2">
                <Label for="location-lng">Longitude</Label>
                <Input
                  id="location-lng"
                  v-model.number="locationValidation.longitude"
                  type="number"
                  placeholder="120.9842"
                  step="0.0001"
                />
              </div>
              <div class="space-y-2">
                <Label for="location-radius">Radius (meters)</Label>
                <Input
                  id="location-radius"
                  v-model.number="locationValidation.radius"
                  type="number"
                  placeholder="100"
                  min="1"
                />
              </div>
              <p class="text-xs text-muted-foreground">
                Redemption allowed within {{ locationValidation?.radius || 100 }}m of specified location
              </p>
              <Button
                variant="outline"
                class="w-full"
                @click="locationValidation = null"
                v-if="locationValidation"
              >
                Clear Location
              </Button>
            </div>
          </TabsContent>
          
          <!-- Time Tab -->
          <TabsContent value="time" class="flex-1 overflow-y-auto mt-4 space-y-4">
            <div class="space-y-3">
              <div class="space-y-2">
                <Label for="time-start">Start Time</Label>
                <Input
                  id="time-start"
                  v-model="timeValidation.start_time"
                  type="time"
                  placeholder="09:00"
                />
              </div>
              <div class="space-y-2">
                <Label for="time-end">End Time</Label>
                <Input
                  id="time-end"
                  v-model="timeValidation.end_time"
                  type="time"
                  placeholder="17:00"
                />
              </div>
              <div class="space-y-2">
                <Label for="time-timezone">Timezone</Label>
                <Input
                  id="time-timezone"
                  v-model="timeValidation.timezone"
                  type="text"
                  placeholder="Asia/Manila"
                />
              </div>
              <p class="text-xs text-muted-foreground">
                Redemption allowed between {{ timeValidation?.start_time || '00:00' }} - {{ timeValidation?.end_time || '23:59' }}
              </p>
              <Button
                variant="outline"
                class="w-full"
                @click="timeValidation = null"
                v-if="timeValidation"
              >
                Clear Time
              </Button>
            </div>
          </TabsContent>
          
          <!-- Secret Tab -->
          <TabsContent value="secret" class="flex-1 overflow-y-auto mt-4 space-y-4">
            <div class="space-y-3">
              <div class="space-y-2">
                <Label for="secret">Secret Code</Label>
                <Input
                  id="secret"
                  v-model="validationSecret"
                  type="text"
                  placeholder="Enter secret code"
                />
                <p class="text-xs text-muted-foreground">
                  Redeemer must enter this secret code to redeem voucher
                </p>
              </div>
              <Button
                variant="outline"
                class="w-full"
                @click="validationSecret = ''"
                v-if="validationSecret"
              >
                Clear Secret
              </Button>
            </div>
          </TabsContent>
          
          <!-- Payee Tab -->
          <TabsContent value="payee" class="flex-1 overflow-y-auto mt-4 space-y-4">
            <div class="space-y-3">
              <div class="space-y-2">
                <Label for="payee">{{ payeeLabel }}</Label>
                <Input
                  id="payee"
                  v-model="payee"
                  type="text"
                  placeholder="CASH, mobile number, or vendor alias"
                />
                <p class="text-xs text-muted-foreground">
                  {{ payeeContextHelp }}
                </p>
              </div>
              
              <!-- Payee Type Info -->
              <div class="p-3 bg-muted/50 rounded-lg">
                <p class="text-sm font-medium mb-1">Detected: {{ payeeTypeDisplay }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ payeeTypeDescription }}
                </p>
              </div>
              
              <!-- Quick Presets -->
              <div class="space-y-2">
                <Label class="text-xs">Quick Presets:</Label>
                <div class="grid grid-cols-2 gap-2">
                  <Button variant="outline" size="sm" @click="payee = ''">
                    Anyone (CASH)
                  </Button>
                  <Button variant="outline" size="sm" @click="payee = '09171234567'">
                    Mobile Sample
                  </Button>
                </div>
              </div>
            </div>
          </TabsContent>
        </Tabs>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.validation.open = false" class="flex-1">
            Cancel
          </Button>
          <Button @click="sheetState.validation.open = false" class="flex-1">
            Apply
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Feedback Sheet (Phase 7) -->
    <Sheet v-model:open="sheetState.feedback.open">
      <SheetContent side="bottom" class="h-auto max-h-[80vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Notifications</SheetTitle>
          <SheetDescription>
            Configure notification channels for redemption events
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-4">
          <!-- Email Notification -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <Label for="feedback-email">Email Notification</Label>
              <Checkbox
                :checked="!!feedbackEmail"
                @update:checked="(checked) => !checked && (feedbackEmail = '')"
              />
            </div>
            <Input
              id="feedback-email"
              v-model="feedbackEmail"
              type="email"
              placeholder="recipient@example.com"
              :disabled="!feedbackEmail && feedbackEmail === ''"
            />
            <p class="text-xs text-muted-foreground">
              Send redemption notification to this email
            </p>
          </div>
          
          <!-- SMS Notification -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <Label for="feedback-mobile">SMS Notification</Label>
              <Checkbox
                :checked="!!feedbackMobile"
                @update:checked="(checked) => !checked && (feedbackMobile = '')"
              />
            </div>
            <Input
              id="feedback-mobile"
              v-model="feedbackMobile"
              type="tel"
              placeholder="09171234567"
              :disabled="!feedbackMobile && feedbackMobile === ''"
            />
            <p class="text-xs text-muted-foreground">
              Send SMS notification to this mobile number
            </p>
          </div>
          
          <!-- Webhook Notification -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <Label for="feedback-webhook">Webhook URL</Label>
              <Checkbox
                :checked="!!feedbackWebhook"
                @update:checked="(checked) => !checked && (feedbackWebhook = '')"
              />
            </div>
            <Input
              id="feedback-webhook"
              v-model="feedbackWebhook"
              type="url"
              placeholder="https://your-server.com/webhook"
              :disabled="!feedbackWebhook && feedbackWebhook === ''"
            />
            <p class="text-xs text-muted-foreground">
              POST redemption data to this endpoint
            </p>
          </div>
          
          <!-- Info Box -->
          <div v-if="feedbackEmail || feedbackMobile || feedbackWebhook" class="p-4 bg-muted/50 rounded-lg mt-6">
            <p class="text-sm font-medium mb-1">Active Channels</p>
            <ul class="text-xs text-muted-foreground space-y-1">
              <li v-if="feedbackEmail" class="flex items-center gap-2">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                Email: {{ feedbackEmail }}
              </li>
              <li v-if="feedbackMobile" class="flex items-center gap-2">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                SMS: {{ feedbackMobile }}
              </li>
              <li v-if="feedbackWebhook" class="flex items-center gap-2">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                Webhook: {{ feedbackWebhook.substring(0, 40) }}{{ feedbackWebhook.length > 40 ? '...' : '' }}
              </li>
            </ul>
          </div>
          
          <!-- Empty State -->
          <div v-else class="py-8 text-center">
            <p class="text-sm text-muted-foreground">No notification channels configured</p>
            <p class="text-xs text-muted-foreground mt-1">Enable at least one channel above</p>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.feedback.open = false" class="flex-1">
            Cancel
          </Button>
          <Button @click="sheetState.feedback.open = false" class="flex-1">
            Apply
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Rider Sheet (Phase 8 - Advanced) -->
    <Sheet v-model:open="sheetState.rider.open">
      <SheetContent side="bottom" class="h-[85vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Rider Configuration</SheetTitle>
          <SheetDescription>
            Advanced settings for redemption experience
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-4">
          <!-- Rider Message -->
          <div class="space-y-2">
            <Label for="rider-message">Custom Message</Label>
            <Textarea
              id="rider-message"
              v-model="riderMessage"
              placeholder="Thank you for redeeming!"
              rows="3"
            />
            <p class="text-xs text-muted-foreground">
              Message displayed to redeemer during redemption
            </p>
          </div>
          
          <!-- Rider URL -->
          <div class="space-y-2">
            <Label for="rider-url">Custom URL</Label>
            <Input
              id="rider-url"
              v-model="riderUrl"
              type="url"
              placeholder="https://your-website.com"
            />
            <p class="text-xs text-muted-foreground">
              Optional URL to redirect after redemption
            </p>
          </div>
          
          <!-- Redirect Timeout -->
          <div class="space-y-2">
            <Label for="rider-redirect-timeout">Redirect Timeout (seconds)</Label>
            <Input
              id="rider-redirect-timeout"
              v-model.number="riderRedirectTimeout"
              type="number"
              placeholder="5"
              min="0"
              max="60"
            />
            <p class="text-xs text-muted-foreground">
              Delay before redirecting to custom URL (0 = immediate)
            </p>
          </div>
          
          <!-- Splash Text -->
          <div class="space-y-2">
            <Label for="rider-splash">Splash Text</Label>
            <Textarea
              id="rider-splash"
              v-model="riderSplash"
              placeholder="Success! Redirecting..."
              rows="2"
            />
            <p class="text-xs text-muted-foreground">
              Text shown during redirect countdown
            </p>
          </div>
          
          <!-- Splash Timeout -->
          <div class="space-y-2">
            <Label for="rider-splash-timeout">Splash Duration (seconds)</Label>
            <Input
              id="rider-splash-timeout"
              v-model.number="riderSplashTimeout"
              type="number"
              placeholder="3"
              min="0"
              max="30"
            />
            <p class="text-xs text-muted-foreground">
              How long to show splash text before redirect
            </p>
          </div>
          
          <!-- Preview -->
          <div v-if="riderMessage || riderUrl || riderSplash" class="p-4 bg-muted/50 rounded-lg">
            <p class="text-sm font-medium mb-2">Preview</p>
            <div class="space-y-2 text-xs text-muted-foreground">
              <p v-if="riderMessage">Message: "{{ riderMessage }}"</p>
              <p v-if="riderUrl">Redirect to: {{ riderUrl }}</p>
              <p v-if="riderRedirectTimeout !== null">Wait {{ riderRedirectTimeout }}s before redirect</p>
              <p v-if="riderSplash">Show splash: "{{ riderSplash }}"</p>
              <p v-if="riderSplashTimeout !== null">Splash duration: {{ riderSplashTimeout }}s</p>
            </div>
          </div>
          
          <!-- Clear All -->
          <Button
            variant="outline"
            class="w-full"
            @click="clearRider"
            v-if="riderMessage || riderUrl || riderSplash"
          >
            Clear All
          </Button>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.rider.open = false" class="flex-1">
            Cancel
          </Button>
          <Button @click="sheetState.rider.open = false" class="flex-1">
            Apply
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Envelope Sheet (Phase 9 - Conditional for payable/settlement) -->
    <Sheet v-model:open="sheetState.envelope.open">
      <SheetContent side="bottom" class="h-auto max-h-[70vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Settlement Envelope</SheetTitle>
          <SheetDescription>
            Configure settlement envelope for tracking and documentation
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-4">
          <div v-if="voucherType === 'payable' || voucherType === 'settlement'">
            <p class="text-sm text-muted-foreground mb-4">
              Settlement envelopes are automatically created for {{ voucherType }} vouchers.
              You can configure additional settings here.
            </p>
            
            <!-- Simple envelope toggle -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label>Enable Settlement Envelope</Label>
                <Checkbox
                  :checked="!!envelopeConfig"
                  @update:checked="(checked) => envelopeConfig = checked ? {} : null"
                />
              </div>
              <p class="text-xs text-muted-foreground">
                Track payments and documents in a settlement envelope
              </p>
            </div>
            
            <div v-if="envelopeConfig" class="mt-4 p-4 bg-primary/5 rounded-lg border border-primary/20">
              <p class="text-sm font-medium mb-1">✓ Envelope Enabled</p>
              <p class="text-xs text-muted-foreground">
                Default envelope driver will be used. Configure advanced options in desktop version.
              </p>
            </div>
          </div>
          
          <div v-else class="py-8 text-center">
            <p class="text-sm text-muted-foreground">Settlement envelopes are only available for payable and settlement vouchers</p>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.envelope.open = false" class="flex-1">
            Close
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Rail & Fees Sheet (Phase 10 - Conditional for settlement) -->
    <Sheet v-model:open="sheetState.railFees.open">
      <SheetContent side="bottom" class="h-auto max-h-[75vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Settlement Rail & Fees</SheetTitle>
          <SheetDescription>
            Configure disbursement method and fee handling
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 space-y-4">
          <div v-if="voucherType === 'settlement' || voucherType === 'redeemable'">
            <!-- Settlement Rail -->
            <div class="space-y-2">
              <Label>Settlement Rail</Label>
              <RadioGroup v-model="settlementRail">
                <div
                  :class="[
                    'flex items-start space-x-3 p-3 rounded-lg border cursor-pointer transition-all',
                    settlementRail === 'auto' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'
                  ]"
                  @click="settlementRail = 'auto'"
                >
                  <RadioGroupItem value="auto" id="rail-auto" class="mt-0.5" />
                  <div class="flex-1">
                    <Label for="rail-auto" class="font-semibold cursor-pointer">Auto</Label>
                    <p class="text-xs text-muted-foreground mt-1">Smart selection based on amount (≤₱50k = INSTAPAY, >₱50k = PESONET)</p>
                  </div>
                </div>
                
                <div
                  :class="[
                    'flex items-start space-x-3 p-3 rounded-lg border cursor-pointer transition-all',
                    settlementRail === 'INSTAPAY' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'
                  ]"
                  @click="settlementRail = 'INSTAPAY'"
                >
                  <RadioGroupItem value="INSTAPAY" id="rail-instapay" class="mt-0.5" />
                  <div class="flex-1">
                    <Label for="rail-instapay" class="font-semibold cursor-pointer">INSTAPAY</Label>
                    <p class="text-xs text-muted-foreground mt-1">Real-time transfer • Max ₱50,000 • ₱10 fee</p>
                  </div>
                </div>
                
                <div
                  :class="[
                    'flex items-start space-x-3 p-3 rounded-lg border cursor-pointer transition-all',
                    settlementRail === 'PESONET' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'
                  ]"
                  @click="settlementRail = 'PESONET'"
                >
                  <RadioGroupItem value="PESONET" id="rail-pesonet" class="mt-0.5" />
                  <div class="flex-1">
                    <Label for="rail-pesonet" class="font-semibold cursor-pointer">PESONET</Label>
                    <p class="text-xs text-muted-foreground mt-1">Next business day • Max ₱1,000,000 • ₱25 fee</p>
                  </div>
                </div>
              </RadioGroup>
            </div>
            
            <!-- Fee Strategy -->
            <div class="space-y-2 pt-4 border-t">
              <Label>Fee Strategy</Label>
              <RadioGroup v-model="feeStrategy">
                <div class="flex items-center space-x-2">
                  <RadioGroupItem value="absorb" id="fee-absorb" />
                  <Label for="fee-absorb" class="font-normal cursor-pointer">
                    Absorb (issuer pays fee)
                  </Label>
                </div>
                <div class="flex items-center space-x-2">
                  <RadioGroupItem value="include" id="fee-include" />
                  <Label for="fee-include" class="font-normal cursor-pointer">
                    Include (deduct from voucher)
                  </Label>
                </div>
                <div class="flex items-center space-x-2">
                  <RadioGroupItem value="add" id="fee-add" />
                  <Label for="fee-add" class="font-normal cursor-pointer">
                    Add (redeemer receives voucher + fee)
                  </Label>
                </div>
              </RadioGroup>
            </div>
            
            <!-- Fee Preview -->
            <div class="p-4 bg-muted/50 rounded-lg">
              <p class="text-sm font-medium mb-2">Fee Preview</p>
              <div class="text-xs text-muted-foreground space-y-1">
                <p>Rail: {{ settlementRail === 'auto' ? 'Auto-select' : settlementRail || 'Not set' }}</p>
                <p>Strategy: {{ feeStrategy === 'absorb' ? 'Issuer pays' : feeStrategy === 'include' ? 'Deduct from amount' : 'Add to disbursement' }}</p>
                <p v-if="amount" class="pt-2 border-t mt-2">
                  Example: ₱{{ amount.toLocaleString() }} voucher → 
                  {{ feeStrategy === 'absorb' ? `₱${amount.toLocaleString()} to redeemer` : 
                     feeStrategy === 'include' ? `₱${(amount - 10).toLocaleString()} to redeemer` :
                     `₱${(amount + 10).toLocaleString()} to redeemer` }}
                </p>
              </div>
            </div>
          </div>
          
          <div v-else class="py-8 text-center">
            <p class="text-sm text-muted-foreground">Rail & fee settings are only available for redeemable and settlement vouchers</p>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button variant="outline" @click="sheetState.railFees.open = false" class="flex-1">
            Cancel
          </Button>
          <Button @click="sheetState.railFees.open = false" class="flex-1">
            Apply
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- More placeholder sheets... -->
  </PwaLayout>
</template>
