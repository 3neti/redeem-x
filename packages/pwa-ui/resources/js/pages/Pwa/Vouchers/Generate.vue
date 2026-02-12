<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
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
    
    <!-- More placeholder sheets... -->
  </PwaLayout>
</template>
