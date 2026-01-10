<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Loader2, Sparkles, Copy, MessageSquare, Share2, Wallet, ChevronDown, AlertCircle, QrCode, CreditCard, RotateCcw, Receipt } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';

interface PortalConfig {
  branding?: { show_logo?: boolean; show_icon?: boolean };
  labels?: Record<string, string | boolean>;
  quick_amounts?: { enabled?: boolean; amounts?: number[] };
  inputs?: { enabled?: boolean; available?: Record<string, any> };
  success?: Record<string, string | boolean>;
  modals?: Record<string, Record<string, string>>;
}

interface Props {
  is_authenticated: boolean;
  wallet_balance: number;
  vouchers_count: number;
  formatted_balance: string;
  redemption_endpoint?: string;
  page_title?: string;
  page_subtitle?: string;
  config?: PortalConfig;
}

const props = defineProps<Props>();
const page = usePage();
const { toast } = useToast();

// Get redemption endpoint from props or shared state
const redemptionEndpoint = computed(() => 
  props.redemption_endpoint || (page.props as any).redemption_endpoint || '/disburse'
);

// Get configurable title/subtitle
const pageTitle = computed(() => props.page_title || (page.props as any).app_name || 'Portal');
const pageSubtitle = computed(() => props.page_subtitle || 'Generate vouchers instantly');

// Configurable quick amounts
const quickAmounts = computed(() => {
  if (props.config?.quick_amounts?.enabled === false) return [];
  return props.config?.quick_amounts?.amounts || [100, 200, 500, 1000, 2000, 5000];
});

// Configurable available inputs
const availableInputs = computed(() => {
  if (props.config?.inputs?.enabled === false) return [];
  
  const inputs = props.config?.inputs?.available || {
    otp: { enabled: true, label: 'OTP', icon: '🔢' },
    selfie: { enabled: true, label: 'Selfie', icon: '📸' },
    location: { enabled: true, label: 'Location', icon: '📍' },
    signature: { enabled: true, label: 'Signature', icon: '✍️' },
    kyc: { enabled: true, label: 'KYC', icon: '🆔' },
  };
  
  return Object.entries(inputs)
    .filter(([_, config]) => (config as any).enabled !== false)
    .map(([key, config]) => ({
      value: key,
      label: (config as any).label || key.toUpperCase(),
      icon: (config as any).icon || '📋',
    }));
});

// Form state
const amount = ref<number | null>(null);
const count = ref<number>(1);
const instruction = ref('');
const quickInputs = ref<string[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const generatedVoucher = ref<any>(null);
const showTopUpModal = ref(false);
const showConfirmModal = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

/**
 * STATE MACHINE INPUT MASKING
 * 
 * Calculator-style input with amount × count format (e.g., "100 x 1")
 * 
 * Key Concepts:
 * - editMode: 'amount' | 'count' - determines which value is being edited
 * - tempAmount/tempCount: digit accumulators (empty = "select all" behavior)
 * - Global keyboard capture: works without clicking input field first
 * 
 * State Flow:
 * 1. Numeric keys → append to current mode's accumulator
 * 2. Non-numeric keys → toggle mode (character not shown)
 * 3. Backspace → remove last digit from accumulator
 * 4. Mode toggle resets accumulator → next digit replaces (select-all)
 * 
 * Example:
 * Click "100"     → "100 x 1" (tempAmount='', ready for replace)
 * Type: 2         → "2 x 1" (first digit replaces)
 * Type: 0         → "20 x 1" (append)
 * Type: 5         → "205 x 1" (append)
 * Type: x         → "205 x 1" (toggle to count, tempCount='')
 * Type: 3         → "205 x 3" (first digit replaces)
 * Type: space     → "205 x 3" (toggle to amount, tempAmount='')
 * Type: 1         → "1 x 3" (select-all, count sticky!)
 * 
 * See: docs/PORTAL_INPUT_MASKING.md for full documentation
 */
type EditMode = 'amount' | 'count';
const editMode = ref<EditMode>('amount');
const tempAmount = ref<string>(''); // Accumulator for digits
const tempCount = ref<string>('1');

// Quick amounts and available inputs are now computed properties from config (lines 47-72)

// Payee field state (bank check metaphor: blank/CASH, mobile, or vendor alias)
const payee = ref<string>(''); // Default to CASH (anyone)
const showPayeeModal = ref(false);
const autoAddedFields = ref<Set<string>>(new Set()); // Track auto-added fields (for disable logic)

interface VendorAlias {
  id: number;
  alias: string;
  status: string;
}

const vendorAliases = ref<VendorAlias[]>([]);

// Smart payee detection (same logic as CreateV2.vue)
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

const payeeDisplayValue = computed(() => {
  return payee.value.trim() || 'CASH';
});

const payeeContextHelp = computed(() => {
  switch (payeeType.value) {
    case 'anyone': return 'Anyone can redeem';
    case 'mobile': return `Restricted to: ${normalizedPayee.value}`;
    case 'vendor': return `Merchant: ${normalizedPayee.value}`;
    default: return 'Anyone can redeem';
  }
});

// Computed state for OTP checkbox (auto-add + read-only when mobile payee)
const otpInputState = computed(() => {
  const isMobileValidation = payeeType.value === 'mobile';
  const isInFields = quickInputs.value.includes('otp');
  const isAutoAdded = autoAddedFields.value.has('otp');
  
  return {
    checked: isInFields,
    disabled: isMobileValidation && isAutoAdded,
  };
});

// Live pricing using composable (same as vouchers/generate)
const instructionsForPricing = computed(() => ({
  cash: {
    amount: amount.value || 0,
    currency: 'PHP',
  },
  inputs: {
    fields: quickInputs.value,
  },
  count: count.value,
}));

const { breakdown, loading: pricingLoading, totalDeduction } = useChargeBreakdown(
  instructionsForPricing,
  { debounce: 500, autoCalculate: true }
);

const estimatedCost = computed(() => totalDeduction.value);

const canShare = computed(() => {
  return typeof navigator !== 'undefined' && 'share' in navigator;
});

const showQuickAmounts = computed(() => !instruction.value && !amount.value);

const formatAmount = (amt: number): string => {
  if (amt >= 1000) {
    return `${amt / 1000}K`;
  }
  return amt.toString();
};

// Input masking utilities
const formatMaskedInput = (rawInput: string): string => {
  const digits = rawInput.replace(/\D/g, '');
  if (!digits) return '';
  
  // Parse: first 1-6 digits = amount, next 1-3 = count
  const match = /^(\d{1,6})(\d{1,3})?$/.exec(digits);
  if (!match) return rawInput;
  
  const amt = match[1];
  const cnt = match[2] || '1';
  
  return `${amt} x ${cnt}`;
};

const parseMaskedInput = (maskedValue: string): { amount: number; count: number } | null => {
  const match = /^(\d+)\s*x\s*(\d+)$/i.exec(maskedValue);
  if (!match) return null;
  
  return {
    amount: parseInt(match[1]),
    count: parseInt(match[2]),
  };
};

const dynamicPlaceholder = computed(() => {
  // Priority 1: Reflect current UI state
  if (amount.value && quickInputs.value.length > 0) {
    const voucherText = count.value === 1 ? 'voucher' : 'vouchers';
    return `Press Enter for ${count.value} ₱${amount.value.toLocaleString()} ${voucherText} with ${quickInputs.value.length} input(s)`;
  }
  
  if (amount.value) {
    const voucherText = count.value === 1 ? 'voucher' : 'vouchers';
    return `Press Enter to generate ${count.value} ₱${amount.value.toLocaleString()} ${voucherText}`;
  }
  
  if (quickInputs.value.length > 0) {
    return `Enter amount (${quickInputs.value.join(', ')} required)`;
  }
  
  // Priority 2: First-time user
  if (props.vouchers_count === 0) {
    return '200  ← Try typing an amount';
  }
  
  // Priority 3: Low balance warning
  if (props.wallet_balance < 100) {
    return 'Top up balance first (click balance above)';
  }
  
  // Priority 4: Rotating examples
  const examples = ['500', '1000', '10 vouchers for 200 each', '5 for 500 with selfie'];
  const dayOfYear = Math.floor(Date.now() / (1000 * 60 * 60 * 24));
  return examples[dayOfYear % examples.length];
});

const handleQuickAmount = (amt: number) => {
  amount.value = amt;
  count.value = 1;
  tempAmount.value = ''; // Reset accumulator so next digit replaces (select-all behavior)
  tempCount.value = '1';
  editMode.value = 'amount';
  instruction.value = `${amt} x 1`;
  
  // Important: Set display to show current value while accumulator is reset
  // This way the display shows "100 x 1" but tempAmount is "" ready for next digit
};

const toggleInput = (input: string) => {
  // Don't allow toggling OTP if it's disabled (auto-added by mobile payee)
  if (input === 'otp' && otpInputState.value.disabled) {
    return;
  }
  
  const index = quickInputs.value.indexOf(input);
  if (index > -1) {
    quickInputs.value.splice(index, 1);
  } else {
    quickInputs.value.push(input);
  }
};

const handleSubmit = async () => {
  // Use parsed amount from state machine instead of raw instruction
  if (!amount.value) {
    error.value = 'Please enter an amount';
    return;
  }
  
  // STEP 1: Check authentication
  if (!props.is_authenticated) {
    sessionStorage.setItem('intended_voucher', JSON.stringify({
      amount: amount.value,
      inputs: quickInputs.value,
      instruction: instruction.value,
    }));
    
    router.visit('/login', {
      data: { redirect: '/portal' },
    });
    
    toast({
      title: 'Sign in required',
      description: 'Please sign in or create an account to generate vouchers',
    });
    return;
  }
  
  // STEP 2: Check balance
  if (estimatedCost.value > props.wallet_balance) {
    error.value = `Insufficient balance. You have ${props.formatted_balance}, need ~₱${estimatedCost.value.toFixed(2)}`;
    showTopUpModal.value = true;
    return;
  }
  
  // STEP 3: Show confirmation modal
  // TODO: Make confirmation optional via user preference
  // - Add user setting: confirm_portal_generation (boolean)
  // - Check setting before showing modal
  // - Allow power users to skip confirmation
  showConfirmModal.value = true;
};

const confirmGeneration = async () => {
  showConfirmModal.value = false;
  await generateSimple(amount.value);
};

const generateSimple = async (amt: number) => {
  if (amt < 1) {
    error.value = 'Amount must be at least ₱1';
    return;
  }
  
  if (count.value < 1) {
    error.value = 'Count must be at least 1';
    return;
  }
  
  loading.value = true;
  error.value = null;
  
  try {
    const payload = {
      amount: amt,
      count: count.value,
      input_fields: quickInputs.value,
      validation_mobile: payeeType.value === 'mobile' ? normalizedPayee.value : undefined,
      validation_payable: payeeType.value === 'vendor' ? normalizedPayee.value : undefined,
    };
    
    // Generate idempotency key for this request
    const idempotencyKey = `portal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    const response = await axios.post('/api/v1/vouchers', payload, {
      headers: {
        'Idempotency-Key': idempotencyKey,
      },
    });
    
    // API wraps data in response.data.data structure
    const apiData = response.data.data;
    if (apiData && apiData.vouchers && apiData.vouchers.length > 0) {
      // API returns array of vouchers, we take the first one
      generatedVoucher.value = apiData.vouchers[0];
      toast({
        title: 'Voucher created!',
        description: `Code: ${apiData.vouchers[0].code}`,
      });
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

const copyCode = async () => {
  if (!generatedVoucher.value) return;
  
  await navigator.clipboard.writeText(generatedVoucher.value.code);
  toast({
    title: 'Copied!',
    description: 'Voucher code copied to clipboard',
  });
};

const shareViaSMS = () => {
  if (!generatedVoucher.value) return;
  const text = `Your voucher code: ${generatedVoucher.value.code}. Redeem at: ${window.location.origin}/redeem?code=${generatedVoucher.value.code}`;
  window.location.href = `sms:?body=${encodeURIComponent(text)}`;
};

const shareViaWhatsApp = () => {
  if (!generatedVoucher.value) return;
  const text = `Your voucher code: ${generatedVoucher.value.code}. Redeem at: ${window.location.origin}/redeem?code=${generatedVoucher.value.code}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
};

const handleShare = async () => {
  if (!generatedVoucher.value) return;
  
  const shareData = {
    title: 'Voucher Code',
    text: `Your voucher code: ${generatedVoucher.value.code}`,
    url: `${window.location.origin}${redemptionEndpoint.value}?code=${generatedVoucher.value.code}`,
  };
  
  try {
    await navigator.share(shareData);
    toast({
      title: 'Shared!',
      description: 'Voucher shared successfully',
    });
  } catch (err: any) {
    if (err.name !== 'AbortError') {
      console.error('Share failed:', err);
    }
  }
};

const resetAndCreateAnother = () => {
  generatedVoucher.value = null;
  amount.value = null;
  count.value = 1;
  tempAmount.value = '';
  tempCount.value = '1';
  editMode.value = 'amount';
  instruction.value = '';
  quickInputs.value = [];
  error.value = null;
};

// Reset input field (preserves checkbox selections)
const resetInput = () => {
  amount.value = null;
  count.value = 1;
  tempAmount.value = '';
  tempCount.value = '1';
  editMode.value = 'amount';
  instruction.value = '';
  error.value = null;
  // Note: Does NOT reset quickInputs (preserves user selections)
};

// State machine: Handle keyboard input with amount/count mode toggle
const handleKeyDown = (event: KeyboardEvent) => {
  const key = event.key;
  
  // Check if numeric (0-9)
  const isNumeric = /^[0-9]$/.test(key);
  
  if (isNumeric) {
    event.preventDefault();
    
    if (editMode.value === 'amount') {
      // If accumulator is empty, this is first digit (replace behavior)
      // Otherwise append
      if (tempAmount.value === '') {
        // First digit after quick amount or mode toggle
        tempAmount.value = key;
      } else {
        // Append to existing
        tempAmount.value += key;
      }
      amount.value = parseInt(tempAmount.value);
    } else {
      // Count mode
      if (tempCount.value === '') {
        tempCount.value = key;
      } else {
        tempCount.value += key;
      }
      count.value = parseInt(tempCount.value);
    }
    
    // Update display (always use temp accumulators)
    instruction.value = `${tempAmount.value} x ${tempCount.value}`;
  } else if (key === 'Backspace') {
    event.preventDefault();
    
    if (editMode.value === 'amount') {
      // If accumulator is empty, populate from display first
      if (tempAmount.value === '' && amount.value) {
        tempAmount.value = amount.value.toString();
      }
      // Remove last digit from amount
      tempAmount.value = tempAmount.value.slice(0, -1);
      amount.value = tempAmount.value ? parseInt(tempAmount.value) : null;
      
      // If amount is now null, clear instruction to show quick amounts
      if (!amount.value) {
        instruction.value = '';
        return;
      }
    } else {
      // If accumulator is empty, populate from display first
      if (tempCount.value === '' && count.value) {
        tempCount.value = count.value.toString();
      }
      // Remove last digit from count
      tempCount.value = tempCount.value.slice(0, -1);
      if (!tempCount.value) tempCount.value = '1';
      count.value = parseInt(tempCount.value);
    }
    
    instruction.value = `${tempAmount.value || amount.value} x ${tempCount.value}`;
  } else if (key === 'Enter') {
    // Let Enter propagate for form submission
    return;
  } else {
    // Any other key (x, space, letters, etc.) = toggle mode
    event.preventDefault();
    
    if (editMode.value === 'amount') {
      // Switch to count mode
      editMode.value = 'count';
      tempCount.value = ''; // Reset count accumulator (next digit replaces)
    } else {
      // Switch back to amount mode
      editMode.value = 'amount';
      tempAmount.value = ''; // Reset amount accumulator (next digit replaces)
    }
  }
};

// Click handler: determine mode based on click position
const handleInputClick = (event: MouseEvent) => {
  const input = event.target as HTMLInputElement;
  const cursorPos = input.selectionStart || 0;
  const xPos = input.value.indexOf(' x ');
  
  if (xPos > -1) {
    if (cursorPos > xPos + 2) {
      // Clicked in count area
      editMode.value = 'count';
    } else {
      // Clicked in amount area
      editMode.value = 'amount';
    }
  }
};

// Global keyboard capture for calculator-like UX
const handleGlobalKeyDown = (event: KeyboardEvent) => {
  // Don't capture if user is typing in another input or modal is open
  const target = event.target as HTMLElement;
  if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || generatedVoucher.value || showTopUpModal.value || showPayeeModal.value) {
    return;
  }
  
  // Forward to our handler
  handleKeyDown(event);
};

// Restore intended action after login
onMounted(() => {
  const intended = sessionStorage.getItem('intended_voucher');
  if (intended && props.is_authenticated) {
    try {
      const data = JSON.parse(intended);
      amount.value = data.amount;
      quickInputs.value = data.inputs || [];
      instruction.value = data.instruction;
      sessionStorage.removeItem('intended_voucher');
      
      toast({
        title: 'Welcome back!',
        description: 'Complete your voucher generation below',
      });
    } catch (e) {
      console.error('Failed to restore intended action:', e);
    }
  }
  
  // Load vendor aliases
  axios.get('/settings/vendor-aliases/list').then(response => {
    vendorAliases.value = response.data.aliases || [];
  }).catch(err => console.error('Failed to load vendor aliases:', err));
  
  // Attach global keyboard listener
  window.addEventListener('keydown', handleGlobalKeyDown);
});

// Cleanup on unmount
onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeyDown);
});

const isSuperAdmin = computed(() => {
  return page.props.auth?.user?.roles?.includes('super-admin') || false;
});

// Sync temp values when instruction changes externally (e.g., quick amounts)
watch(instruction, (val) => {
  const parsed = parseMaskedInput(val);
  if (parsed) {
    amount.value = parsed.amount;
    count.value = parsed.count;
    tempAmount.value = parsed.amount.toString();
    tempCount.value = parsed.count.toString();
  }
});

// Auto-add OTP when mobile payee (same logic as CreateV2.vue)
watch(payeeType, (newType, oldType) => {
  if (newType === 'mobile' && oldType !== 'mobile') {
    // Mobile validation enabled - auto-add OTP if not present
    if (!quickInputs.value.includes('otp')) {
      quickInputs.value.push('otp');
      autoAddedFields.value.add('otp');
    }
  } else if (newType !== 'mobile' && oldType === 'mobile') {
    // Mobile validation disabled - remove OTP if auto-added
    if (autoAddedFields.value.has('otp')) {
      const index = quickInputs.value.indexOf('otp');
      if (index > -1) {
        quickInputs.value.splice(index, 1);
      }
      autoAddedFields.value.delete('otp');
    }
  }
});
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center p-6">
    <!-- Header -->
    <Sparkles class="mb-6 h-16 w-16 text-primary" />
    <h1 class="mb-2 text-4xl font-bold">{{ pageTitle }}</h1>
    <p class="mb-8 text-muted-foreground">
      {{ pageSubtitle }}
    </p>
    
    <!-- Main Form (hide after generation) -->
    <div v-if="!generatedVoucher" class="w-full max-w-2xl space-y-6">
      <!-- Amount Input with Embedded Quick Amounts + Payee Display -->
      <div class="space-y-2">
        <p class="text-sm font-medium">{{ config?.labels?.amount_label || 'Amount' }}</p>
        <div class="flex gap-2">
          <!-- Amount Input (fixed width to prevent shifting) -->
          <div class="relative" style="width: 520px; flex-shrink: 0;">
          <!-- Quick Amount Chips (inside input field, left side) -->
          <div v-if="showQuickAmounts" class="absolute left-2 top-1/2 -translate-y-1/2 flex gap-1 z-10">
            <Button
              v-for="amt in quickAmounts"
              :key="amt"
              variant="ghost"
              size="sm"
              @click="handleQuickAmount(amt)"
              class="h-8 px-2 text-xs font-medium"
            >
              {{ formatAmount(amt) }}
            </Button>
          </div>
          
          <Input
            ref="inputRef"
            v-model="instruction"
            :class="[
              'text-lg h-12 ring-2 ring-primary/50',
              showQuickAmounts ? 'pl-[360px]' : 'pl-3',
              amount ? 'pr-[220px]' : 'pr-[160px]'
            ]"
            @keyup.enter="handleSubmit"
            @keydown="handleKeyDown"
            @click="handleInputClick"
            :disabled="loading"
            readonly
          />
          <Button
            v-if="amount"
            @click="resetInput"
            variant="ghost"
            size="sm"
            class="absolute right-[152px] top-1 h-10 w-10 p-0"
            :title="config?.labels?.reset_button_title || 'Reset input'"
          >
            <RotateCcw class="h-4 w-4" />
          </Button>
          <Button
            @click="handleSubmit"
            :disabled="!instruction || loading || (amount && estimatedCost > wallet_balance)"
            class="absolute right-1 top-1 min-w-[140px] h-10"
            size="sm"
            :variant="amount && estimatedCost > wallet_balance ? 'destructive' : 'default'"
          >
            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
            <template v-else-if="amount">
              {{ config?.labels?.generate_button_text || 'Generate voucher' }}
            </template>
            <template v-else>
              →
            </template>
          </Button>
          </div>
          
          <!-- Payee Display Field (fixed width to prevent shifting) -->
          <div class="relative" style="width: 144px; flex-shrink: 0;">
            <div
              @click="showPayeeModal = true"
              class="flex h-12 w-full cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 py-2 ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              role="button"
              tabindex="0"
            >
              <Receipt v-if="config?.payee?.show_icon !== false" class="h-4 w-4 flex-shrink-0 text-muted-foreground" />
              <div class="flex-1 overflow-hidden">
                <div v-if="config?.payee?.show_label !== false" class="text-xs text-muted-foreground">{{ config?.payee?.label || 'Payee' }}</div>
                <div class="text-sm font-medium truncate">{{ payeeDisplayValue }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Contextual help text below both fields -->
        <p class="text-xs text-muted-foreground">
          {{ payeeContextHelp }}
        </p>
        
        <!-- Quick Inputs (moved closer to input) -->
        <div class="flex flex-wrap gap-2">
          <label
            v-for="input in availableInputs"
            :key="input.value"
            :class="[
              'flex items-center gap-2 rounded-md border px-3 py-2 transition-colors',
              input.value === 'otp' && otpInputState.disabled ? 'opacity-75 cursor-not-allowed' : 'cursor-pointer hover:bg-accent',
              quickInputs.includes(input.value) ? 'bg-accent ring-2 ring-primary' : ''
            ]"
            @click.prevent="input.value === 'otp' && otpInputState.disabled ? null : toggleInput(input.value)"
          >
            <Checkbox
              :checked="quickInputs.includes(input.value)"
              :disabled="input.value === 'otp' && otpInputState.disabled"
            />
            <span class="text-lg">{{ input.icon }}</span>
            <span class="text-sm">{{ input.label }}</span>
          </label>
        </div>
      </div>
      
      <!-- Consolidated Wallet Transaction Line -->
      <div class="flex items-center justify-between gap-4">
        <!-- Left: Wallet Balance -->
        <button
          v-if="is_authenticated"
          @click="showTopUpModal = true"
          class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-accent group"
          title="Click to top up"
        >
          <Wallet class="h-4 w-4 text-muted-foreground group-hover:text-foreground" />
          <span>{{ formatted_balance }}</span>
          <ChevronDown class="h-3 w-3 text-muted-foreground" />
        </button>
        
        <div v-else class="flex items-center gap-2 text-sm text-muted-foreground">
          <Wallet class="h-4 w-4" />
          <span>₱0.00</span>
          <span class="text-xs">(Sign in to top up)</span>
        </div>
        
        <!-- Middle: Pricing Computation -->
        <div v-if="breakdown && amount" class="text-xs text-muted-foreground">
          ₱{{ amount.toLocaleString() }} x {{ count }} + ₱{{ (breakdown.total / 100).toFixed(2) }} fee = ₱{{ estimatedCost.toFixed(2) }}
        </div>
        
        <!-- Right: Balance After -->
        <div v-if="amount && is_authenticated" class="flex items-center gap-2">
          <span 
            class="text-sm"
            :class="estimatedCost > wallet_balance ? 'text-destructive font-medium' : 'text-muted-foreground'"
          >
            Balance after: ₱{{ (wallet_balance - estimatedCost).toFixed(2) }}
          </span>
          <AlertCircle 
            v-if="estimatedCost > wallet_balance" 
            class="h-4 w-4 text-destructive"
            title="Insufficient balance"
          />
        </div>
      </div>
      
      <!-- Error Alert -->
      <Alert v-if="error" variant="destructive">
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>
      
      <!-- Advanced Mode Link -->
      <div v-if="config?.labels?.show_advanced_mode_link !== false" class="text-center">
        <Button
          variant="link"
          @click="router.visit('/vouchers/generate')"
        >
          {{ config?.labels?.advanced_mode_link_text || 'Need more options? →' }}
        </Button>
      </div>
    </div>
    
    <!-- Success Card (after generation) -->
    <div v-else class="w-full max-w-2xl space-y-6">
      <!-- Voucher Code Display -->
      <div class="rounded-xl border-2 border-primary bg-gradient-to-br from-primary/5 to-primary/10 p-8 text-center shadow-lg">
        <p class="mb-6 text-5xl font-bold tracking-widest text-foreground">
          {{ generatedVoucher.code }}
        </p>
        <div class="flex items-center justify-center gap-2">
          <Sparkles class="h-5 w-5 text-primary" />
          <p class="text-2xl font-semibold text-primary">
            ₱{{ generatedVoucher.amount?.toLocaleString() }}
          </p>
        </div>
      </div>
      
      <!-- Share Buttons -->
      <div class="space-y-2">
        <p class="text-sm font-medium">{{ config?.success?.share_section_label || 'Share:' }}</p>
        <div class="flex gap-2">
          <Button variant="outline" class="flex-1" @click="copyCode">
            <Copy class="mr-2 h-4 w-4" />
            {{ config?.success?.copy_button_text || 'Copy Code' }}
          </Button>
          <Button 
            v-if="canShare"
            variant="outline" 
            class="flex-1" 
            @click="handleShare"
          >
            <Share2 class="mr-2 h-4 w-4" />
            {{ config?.success?.share_button_text || 'Share' }}
          </Button>
        </div>
      </div>
      
      <!-- Actions -->
      <Button class="w-full" size="lg" @click="resetAndCreateAnother">
        {{ config?.success?.create_another_button_text || 'Create Another Voucher' }}
      </Button>
      
      <div v-if="config?.success?.show_dashboard_link !== false" class="text-center">
        <Button
          variant="link"
          @click="router.visit('/dashboard')"
        >
          {{ config?.success?.dashboard_link_text || 'Go to Dashboard →' }}
        </Button>
      </div>
    </div>
    
    <!-- Payee Edit Modal -->
    <Dialog v-model:open="showPayeeModal">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ config?.modals?.payee?.title || 'Edit Payee' }}</DialogTitle>
          <DialogDescription>
            {{ config?.modals?.payee?.description || 'Specify who can redeem this voucher' }}
          </DialogDescription>
        </DialogHeader>
        
        <div class="space-y-4">
          <div class="space-y-2">
            <Label for="payee-input">
              {{ config?.modals?.payee?.label || 'Payee' }}
            </Label>
            <Input
              id="payee-input"
              v-model="payee"
              :placeholder="config?.modals?.payee?.placeholder || 'CASH (anyone), mobile number, or vendor alias'"
              list="vendor-aliases-datalist"
              autofocus
            />
            <datalist id="vendor-aliases-datalist">
              <option v-for="alias in vendorAliases" :key="alias.id" :value="alias.alias" />
            </datalist>
            <p class="text-xs text-muted-foreground">
              {{ payeeContextHelp }}
            </p>
          </div>
          
          <!-- Quick Presets -->
          <div v-if="vendorAliases.length > 0" class="space-y-2">
            <Label class="text-xs">Quick Presets:</Label>
            <div class="flex flex-wrap gap-2">
              <Button
                variant="outline"
                size="sm"
                @click="payee = ''"
              >
                Anyone (CASH)
              </Button>
              <Button
                v-for="alias in vendorAliases.slice(0, 3)"
                :key="alias.id"
                variant="outline"
                size="sm"
                @click="payee = alias.alias"
              >
                {{ alias.alias }}
              </Button>
            </div>
          </div>
        </div>
        
        <DialogFooter>
          <DialogClose as-child>
            <Button variant="outline">{{ config?.modals?.payee?.cancel_text || 'Cancel' }}</Button>
          </DialogClose>
          <Button @click="showPayeeModal = false">{{ config?.modals?.payee?.save_text || 'Save' }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    
    <!-- Top-Up Modal (outside main if/else) -->
    <Dialog v-model:open="showTopUpModal">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ config?.modals?.top_up?.title || 'Top Up Wallet' }}</DialogTitle>
          <DialogDescription>
            Current balance: {{ formatted_balance }}
          </DialogDescription>
        </DialogHeader>
        
        <div class="space-y-4">
          <p class="text-center text-sm text-muted-foreground">
            Choose a top-up method below:
          </p>
          
          <div class="flex flex-col gap-2">
            <Button
              @click="() => { showTopUpModal = false; router.visit('/wallet/qr'); }"
            >
              <QrCode class="mr-2 h-4 w-4" />
              {{ config?.modals?.top_up?.qr_button_text || 'Scan QR Code to Load Wallet' }}
            </Button>
            <Button
              v-if="isSuperAdmin"
              variant="outline"
              @click="() => { showTopUpModal = false; router.visit('/topup'); }"
            >
              <CreditCard class="mr-2 h-4 w-4" />
              {{ config?.modals?.top_up?.bank_button_text || 'Bank Transfer (Admin Only)' }}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
    
    <!-- Confirmation Modal -->
    <Dialog v-model:open="showConfirmModal">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
              <Sparkles class="h-6 w-6 text-primary" />
            </div>
            <div>
              <DialogTitle class="text-xl">
                {{ count === 1 
                  ? (config?.modals?.confirm?.title_single || 'Generate voucher?')
                  : (config?.modals?.confirm?.title_multiple || 'Generate {count} vouchers?').replace('{count}', count.toString())
                }}
              </DialogTitle>
              <DialogDescription class="mt-1">
                {{ config?.modals?.confirm?.description || 'Please confirm the details below' }}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>
        
        <!-- Cost Breakdown Card -->
        <div class="space-y-3 rounded-lg border bg-muted/50 p-4">
          <!-- Payee info (NEW - above Amount) -->
          <div class="flex items-center justify-between pb-3 border-b">
            <span class="text-sm text-muted-foreground">{{ config?.modals?.confirm?.payee_label || 'Payee' }}</span>
            <div class="flex items-center gap-2">
              <Receipt class="h-4 w-4 text-muted-foreground" />
              <span class="font-mono text-base font-semibold">{{ payeeDisplayValue }}</span>
            </div>
          </div>
          
          <div class="flex items-center justify-between">
            <span class="text-sm text-muted-foreground">{{ config?.modals?.confirm?.amount_label || 'Amount' }}</span>
            <span class="font-mono text-base font-semibold">₱{{ amount?.toLocaleString() }} × {{ count }}</span>
          </div>
          
          <div class="flex items-center justify-between border-t pt-3">
            <span class="text-sm text-muted-foreground">{{ config?.modals?.confirm?.total_cost_label || 'Total Cost' }}</span>
            <span class="font-mono text-base font-semibold">₱{{ estimatedCost.toFixed(2) }}</span>
          </div>
          
          <div class="flex items-center justify-between border-t pt-3">
            <span class="text-sm font-medium">{{ config?.modals?.confirm?.balance_after_label || 'Balance After' }}</span>
            <span 
              class="font-mono text-lg font-bold"
              :class="estimatedCost > wallet_balance ? 'text-destructive' : 'text-primary'"
            >
              ₱{{ (wallet_balance - estimatedCost).toFixed(2) }}
            </span>
          </div>
          
          <div v-if="quickInputs.length > 0" class="border-t pt-3">
            <div class="text-sm text-muted-foreground mb-2">{{ config?.modals?.confirm?.required_inputs_label || 'Required Inputs:' }}</div>
            <div class="flex flex-wrap gap-2">
              <span 
                v-for="input in quickInputs" 
                :key="input"
                class="inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
              >
                {{ input }}
              </span>
            </div>
          </div>
        </div>
        
        <DialogFooter class="gap-2 sm:gap-2">
          <DialogClose as-child>
            <Button variant="outline" class="flex-1 sm:flex-initial">{{ config?.modals?.confirm?.cancel_button_text || 'Cancel' }}</Button>
          </DialogClose>
          <Button @click="confirmGeneration" :disabled="loading" class="flex-1 sm:flex-initial">
            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
            <Sparkles v-else class="mr-2 h-4 w-4" />
            {{ loading ? (config?.modals?.confirm?.generating_text || 'Generating...') : (config?.modals?.confirm?.confirm_button_text || 'Confirm') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
