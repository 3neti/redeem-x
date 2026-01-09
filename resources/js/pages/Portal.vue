<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
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
const instruction = ref('');
const quickInputs = ref<string[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const generatedVoucher = ref<any>(null);
const showTopUpModal = ref(false);

// Quick amounts (borrowed from TopUp page pattern)
const quickAmounts = [100, 200, 500, 1000, 2000, 5000];

// Available input fields
const availableInputs = [
  { value: 'OTP', label: 'OTP', icon: '🔢' },
  { value: 'SELFIE', label: 'Selfie', icon: '📸' },
  { value: 'LOCATION', label: 'Location', icon: '📍' },
  { value: 'SIGNATURE', label: 'Signature', icon: '✍️' },
  { value: 'KYC', label: 'KYC', icon: '🆔' },
  { value: 'BIRTH_DATE', label: 'Birthday', icon: '🎂' },
];

const estimatedCost = computed(() => {
  if (!amount.value) return 0;
  // Rough estimate: amount + 1% service fee + ₱2 gateway fee
  return amount.value + (amount.value * 0.01) + 2;
});

const dynamicPlaceholder = computed(() => {
  // Priority 1: Reflect current UI state
  if (amount.value && quickInputs.value.length > 0) {
    return `Press Enter for ₱${amount.value.toLocaleString()} with ${quickInputs.value.length} input(s)`;
  }
  
  if (amount.value) {
    return `Press Enter to generate ₱${amount.value.toLocaleString()} voucher`;
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
  instruction.value = amt.toString();
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
  
  loading.value = true;
  error.value = null;
  
  try {
    const payload = {
      amount: amt,
      count: 1,
      input_fields: quickInputs.value,
    };
    
    // Generate idempotency key for this request
    const idempotencyKey = `portal-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    
    const response = await axios.post('/api/v1/vouchers', payload, {
      headers: {
        'Idempotency-Key': idempotencyKey,
      },
    });
    
    if (response.data.success) {
      generatedVoucher.value = response.data.voucher;
      toast({
        title: 'Voucher created!',
        description: `Code: ${response.data.voucher.code}`,
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
  instruction.value = '';
  quickInputs.value = [];
  error.value = null;
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
});

const isSuperAdmin = computed(() => {
  return page.props.auth?.user?.roles?.includes('super-admin') || false;
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
      <!-- Quick Amounts -->
      <div class="space-y-2">
        <p class="text-sm font-medium">Quick amounts:</p>
        <div class="flex flex-wrap gap-2">
          <Button
            v-for="amt in quickAmounts"
            :key="amt"
            variant="outline"
            size="lg"
            @click="handleQuickAmount(amt)"
            :class="{ 'ring-2 ring-primary bg-accent': amount === amt }"
          >
            ₱{{ amt.toLocaleString() }}
          </Button>
        </div>
      </div>
      
      <!-- Amount Input -->
      <div class="space-y-2">
        <p class="text-sm font-medium">Or enter amount:</p>
        <div class="relative">
          <Input
            v-model="instruction"
            :placeholder="dynamicPlaceholder"
            class="pr-44 text-lg h-12"
            @keyup.enter="handleSubmit"
            :disabled="loading"
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
              Generate @ ₱{{ estimatedCost.toFixed(0) }}
            </template>
            <template v-else>
              →
            </template>
          </Button>
        </div>
        <p class="text-xs text-muted-foreground">
          Hint: "10 vouchers for 500 each" for batch mode (coming soon)
        </p>
      </div>
      
      <!-- Quick Inputs -->
      <div class="space-y-2">
        <p class="text-sm font-medium">Quick inputs (optional):</p>
        <div class="flex flex-wrap gap-2">
          <label
            v-for="input in availableInputs"
            :key="input.value"
            class="flex items-center gap-2 rounded-md border px-3 py-2 cursor-pointer transition-colors hover:bg-accent"
            :class="{ 'bg-accent ring-2 ring-primary': quickInputs.includes(input.value) }"
          >
            <Checkbox
              :checked="quickInputs.includes(input.value)"
              @update:checked="() => toggleInput(input.value)"
            />
            <span class="text-lg">{{ input.icon }}</span>
            <span class="text-sm">{{ input.label }}</span>
          </label>
        </div>
      </div>
      
      <!-- Balance & Cost Info -->
      <div class="flex items-center justify-between">
        <!-- Balance -->
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
        
        <!-- Estimated Cost -->
        <div v-if="amount" class="flex items-center gap-2">
          <span 
            class="text-sm"
            :class="estimatedCost > wallet_balance ? 'text-destructive font-medium' : 'text-muted-foreground'"
          >
            Est. cost: ~₱{{ estimatedCost.toFixed(2) }}
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
