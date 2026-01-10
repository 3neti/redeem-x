<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Loader2, Sparkles, Copy, MessageSquare, Share2, Wallet, ChevronDown, AlertCircle, QrCode, CreditCard } from 'lucide-vue-next';
import { useToast } from '@/components/ui/toast/use-toast';

interface Props {
  is_authenticated: boolean;
  wallet_balance: number;
  vouchers_count: number;
  formatted_balance: string;
}

const props = defineProps<Props>();
const page = usePage();
const { toast } = useToast();

// Form state
const amount = ref<number | null>(null);
const count = ref<number>(1);
const instruction = ref('');
const quickInputs = ref<string[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const generatedVoucher = ref<any>(null);
const showTopUpModal = ref(false);
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

// Quick amounts (borrowed from TopUp page pattern)
const quickAmounts = [100, 200, 500, 1000, 2000, 5000];

// Available input fields (API expects lowercase)
const availableInputs = [
  { value: 'otp', label: 'OTP', icon: '🔢' },
  { value: 'selfie', label: 'Selfie', icon: '📸' },
  { value: 'location', label: 'Location', icon: '📍' },
  { value: 'signature', label: 'Signature', icon: '✍️' },
  { value: 'kyc', label: 'KYC', icon: '🆔' },
];

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
  const index = quickInputs.value.indexOf(input);
  if (index > -1) {
    quickInputs.value.splice(index, 1);
  } else {
    quickInputs.value.push(input);
  }
};

const handleSubmit = async () => {
  const input = instruction.value.trim();
  
  if (!input) {
    error.value = 'Please enter an amount or instruction';
    return;
  }
  
  // STEP 1: Check authentication
  if (!props.is_authenticated) {
    sessionStorage.setItem('intended_voucher', JSON.stringify({
      amount: amount.value,
      inputs: quickInputs.value,
      instruction: input,
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
  
  // STEP 3: Generate
  if (/^\d+$/.test(input)) {
    await generateSimple(parseInt(input));
  } else {
    toast({
      title: 'Advanced parsing coming soon',
      description: 'For now, please enter a numeric amount',
      variant: 'destructive',
    });
  }
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
  if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || generatedVoucher.value || showTopUpModal.value) {
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
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center p-6">
    <!-- Header -->
    <Sparkles class="mb-6 h-16 w-16 text-primary" />
    <h1 class="mb-2 text-4xl font-bold">Create a voucher instantly</h1>
    <p class="mb-8 text-muted-foreground">
      Select amount or describe what you need
    </p>
    
    <!-- Main Form (hide after generation) -->
    <div v-if="!generatedVoucher" class="w-full max-w-2xl space-y-6">
      <!-- Amount Input with Embedded Quick Amounts -->
      <div class="space-y-2">
        <p class="text-sm font-medium">Amount</p>
        <div class="relative">
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
            :placeholder="dynamicPlaceholder"
            :class="[
              'text-lg h-12 pr-44 ring-2 ring-primary/50',
              showQuickAmounts ? 'pl-[360px]' : 'pl-3'
            ]"
            @keyup.enter="handleSubmit"
            @keydown="handleKeyDown"
            @click="handleInputClick"
            :disabled="loading"
            readonly
          />
          <Button
            @click="handleSubmit"
            :disabled="!instruction || loading || (amount && estimatedCost > wallet_balance)"
            class="absolute right-1 top-1 min-w-[140px] h-10"
            size="sm"
            :variant="amount && estimatedCost > wallet_balance ? 'destructive' : 'default'"
          >
            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
            <template v-else-if="amount">
              Generate voucher
            </template>
            <template v-else>
              →
            </template>
          </Button>
        </div>
        
        <!-- Quick Inputs (moved closer to input) -->
        <div class="flex flex-wrap gap-2">
          <label
            v-for="input in availableInputs"
            :key="input.value"
            class="flex items-center gap-2 rounded-md border px-3 py-2 cursor-pointer transition-colors hover:bg-accent"
            :class="{ 'bg-accent ring-2 ring-primary': quickInputs.includes(input.value) }"
            @click.prevent="toggleInput(input.value)"
          >
            <Checkbox
              :checked="quickInputs.includes(input.value)"
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
      <div class="text-center">
        <Button
          variant="link"
          @click="router.visit('/vouchers/generate')"
        >
          Need more options? →
        </Button>
      </div>
    </div>
    
    <!-- Success Card (after generation) -->
    <div v-else class="w-full max-w-2xl space-y-6">
      <Alert class="border-green-500 bg-green-50">
        <AlertDescription class="text-green-900">
          ✓ Voucher created successfully!
        </AlertDescription>
      </Alert>
      
      <!-- Voucher Code Display -->
      <div class="rounded-lg border-2 border-primary bg-primary/5 p-6 text-center">
        <p class="mb-2 text-sm font-medium text-muted-foreground">Voucher Code</p>
        <p class="mb-4 text-4xl font-bold tracking-wider">
          {{ generatedVoucher.code }}
        </p>
        <p class="text-lg text-muted-foreground">
          Amount: ₱{{ generatedVoucher.amount?.toLocaleString() }}
        </p>
      </div>
      
      <!-- Share Buttons -->
      <div class="space-y-2">
        <p class="text-sm font-medium">Share:</p>
        <div class="flex gap-2">
          <Button variant="outline" class="flex-1" @click="copyCode">
            <Copy class="mr-2 h-4 w-4" />
            Copy Code
          </Button>
          <Button variant="outline" class="flex-1" @click="shareViaSMS">
            <MessageSquare class="mr-2 h-4 w-4" />
            SMS
          </Button>
          <Button variant="outline" class="flex-1" @click="shareViaWhatsApp">
            <Share2 class="mr-2 h-4 w-4" />
            WhatsApp
          </Button>
        </div>
      </div>
      
      <!-- Create Another -->
      <Button class="w-full" size="lg" @click="resetAndCreateAnother">
        Create Another Voucher
      </Button>
    </div>
    
    <!-- Top-Up Modal (outside main if/else) -->
    <Dialog v-model:open="showTopUpModal">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Top Up Wallet</DialogTitle>
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
              Scan QR Code to Load Wallet
            </Button>
            <Button
              v-if="isSuperAdmin"
              variant="outline"
              @click="() => { showTopUpModal = false; router.visit('/topup'); }"
            >
              <CreditCard class="mr-2 h-4 w-4" />
              Bank Transfer (Admin Only)
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
