<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import QRCode from 'qrcode';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, CheckCircle, AlertCircle, Printer, RotateCcw } from 'lucide-vue-next';
import NumericKeypad from '@/components/NumericKeypad.vue';

// ============================================================================
// TYPES & PROPS
// ============================================================================

interface KioskConfig {
  title: string;
  subtitle?: string;
  campaign?: string;
  driver?: string;
  amount?: number;
  targetAmount?: number;
  inputs?: string[];
  payload?: string[];  // Payload fields to collect (e.g., ['reference', 'membership_id'])
  feedback?: string;
  type?: 'redeemable' | 'payable' | 'settlement';
  // UI Labels (from skin config)
  amountLabel?: string;
  amountPlaceholder?: string;
  targetLabel?: string;
  targetPlaceholder?: string;
  buttonText?: string;
  successTitle?: string;
  successMessage?: string;
  printButton?: string;
  newButton?: string;
  errorTitle?: string;
  retryButton?: string;
  themeColor?: string;
  logo?: string;
}

interface IssuedVoucher {
  code: string;
  amount: number;
  formatted_amount: string;
  redemption_url: string;
}

interface Props {
  config: KioskConfig;
  defaults?: Record<string, string>;
  campaignData?: any;
}

const props = withDefaults(defineProps<Props>(), {
  defaults: () => ({}),
  campaignData: null,
});

// ============================================================================
// STATE
// ============================================================================

type KioskState = 'input' | 'submitting' | 'issued' | 'error';

const state = ref<KioskState>('input');
const amount = ref<number | null>(props.config.amount ?? null);
const targetAmount = ref<number | null>(props.config.targetAmount ?? null);
const issuedVoucher = ref<IssuedVoucher | null>(null);
const errorMessage = ref<string>('');
const qrDataUrl = ref<string>('');

// Payload fields state (dynamic key-value pairs)
const payloadFields = ref<Record<string, string>>({});

// Initialize payload fields from config
const initPayloadFields = () => {
  if (props.config.payload) {
    props.config.payload.forEach(field => {
      payloadFields.value[field] = '';
    });
  }
};
initPayloadFields();

// Keypad state
const showAmountKeypad = ref(false);
const showTargetKeypad = ref(false);
const activeKeypadField = ref<'amount' | 'target' | null>(null);

// X-Ray debug panel
const showXRay = ref(false);

// Scanner buffer
const scanBuffer = ref('');
const scanTimeout = ref<ReturnType<typeof setTimeout> | null>(null);

// ============================================================================
// COMPUTED - Merged Labels
// ============================================================================

// Labels are pre-processed by SkinConfigLoader with automatic placeholders
const labels = computed(() => ({
  title: props.config.title || 'Quick Voucher',
  subtitle: props.config.subtitle || '',
  amountLabel: props.config.ui?.amount_label || 'Amount',
  amountPlaceholder: props.config.ui?.amount_placeholder || 'Enter amount',
  amountKeypadTitle: props.config.ui?.amount_keypad_title || 'Enter Amount',
  targetLabel: props.config.ui?.target_label || 'Target Amount',
  targetPlaceholder: props.config.ui?.target_placeholder || 'Enter target amount',
  targetKeypadTitle: props.config.ui?.target_keypad_title || 'Enter Target Amount',
  buttonText: props.config.ui?.button_text || 'Issue Voucher',
  successTitle: props.config.ui?.success_title || 'Voucher Issued!',
  successMessage: props.config.ui?.success_message || 'Scan QR code to redeem',
  printButton: props.config.ui?.print_button || 'Print',
  newButton: props.config.ui?.new_button || 'Issue Another',
  errorTitle: props.config.ui?.error_title || 'Error',
  retryButton: props.config.ui?.retry_button || 'Try Again',
}));

// Determine voucher type
const voucherType = computed(() => {
  if (props.config.voucher_type) return props.config.voucher_type;
  // BST = settlement with target_amount > 0
  // BEAST = settlement with target_amount = 0
  if (props.config.target_amount !== undefined) return 'settlement';
  return 'redeemable';
});

// Show target amount field?
const showTargetField = computed(() => {
  return voucherType.value === 'settlement' || voucherType.value === 'payable';
});

// Show amount field only when amount > 0 is explicitly passed
const showAmountField = computed(() => {
  // Only show if amount is explicitly set to a positive value
  return props.config.amount !== undefined && props.config.amount > 0;
});

// Can submit?
const canSubmit = computed(() => {
  const needsAmount = showAmountField.value;
  const needsTarget = showTargetField.value;
  
  // Check amount requirement
  if (needsAmount && (amount.value === null || amount.value <= 0)) {
    return false;
  }
  
  // Check target requirement
  if (needsTarget && (targetAmount.value === null || targetAmount.value <= 0)) {
    return false;
  }
  
  // Check payload fields are filled
  if (!payloadFieldsFilled.value) {
    return false;
  }
  
  // At least one field must be shown and filled (or payload fields exist)
  return needsAmount || needsTarget || (props.config.payload && props.config.payload.length > 0);
});

// Type label for display
const typeLabel = computed(() => {
  if (voucherType.value === 'settlement') {
    return targetAmount.value && targetAmount.value > 0 ? 'BST' : 'BEAST';
  }
  return voucherType.value.charAt(0).toUpperCase() + voucherType.value.slice(1);
});

// Format payload field name to label (e.g., 'membership_id' -> 'Membership ID')
const formatFieldLabel = (field: string): string => {
  return field
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

// Check if all payload fields are filled
const payloadFieldsFilled = computed(() => {
  if (!props.config.payload || props.config.payload.length === 0) return true;
  return props.config.payload.every(field => payloadFields.value[field]?.trim());
});

// X-Ray configuration data for debug panel
const xRayData = computed(() => {
  // Helper to filter out null/undefined/empty values
  const clean = (obj: Record<string, any>) => {
    const result: Record<string, any> = {};
    for (const [key, value] of Object.entries(obj)) {
      if (value !== null && value !== undefined && value !== '' && 
          !(Array.isArray(value) && value.length === 0)) {
        result[key] = value;
      }
    }
    return Object.keys(result).length > 0 ? result : null;
  };

  return {
    kiosk: {
      title: labels.value.title,
      subtitle: labels.value.subtitle || undefined,
      voucher_type: voucherType.value,
      type_label: typeLabel.value,
    },
    config: clean({
      amount: props.config.amount,
      target_amount: props.config.targetAmount,
      campaign: props.config.campaign,
      driver: props.config.driver,
    }),
    fields: clean({
      inputs: props.config.inputs?.length ? props.config.inputs : undefined,
      payload: props.config.payload?.length ? props.config.payload : undefined,
    }),
    callbacks: clean({
      feedback: props.config.feedback,
    }),
    ui: {
      show_amount_field: showAmountField.value,
      show_target_field: showTargetField.value,
    },
  };
});

// ============================================================================
// METHODS
// ============================================================================

const openAmountKeypad = () => {
  activeKeypadField.value = 'amount';
  showAmountKeypad.value = true;
};

const openTargetKeypad = () => {
  activeKeypadField.value = 'target';
  showTargetKeypad.value = true;
};

const confirmAmount = (value: number) => {
  amount.value = value;
  showAmountKeypad.value = false;
  activeKeypadField.value = null;
};

const confirmTarget = (value: number) => {
  targetAmount.value = value;
  showTargetKeypad.value = false;
  activeKeypadField.value = null;
};

const generateQrCode = async (url: string) => {
  try {
    qrDataUrl.value = await QRCode.toDataURL(url, {
      width: 200,
      margin: 2,
      color: { dark: '#000000', light: '#ffffff' },
    });
  } catch (err) {
    console.error('QR generation failed:', err);
  }
};

const handleSubmit = async () => {
  if (!canSubmit.value) return;

  state.value = 'submitting';
  errorMessage.value = '';

  try {
    // Build request payload
    // Use config amount if field is hidden, otherwise use entered amount
    const effectiveAmount = showAmountField.value 
      ? amount.value 
      : (props.config.amount ?? 0);
    
    const requestData: Record<string, any> = {
      amount: effectiveAmount,
      count: 1,
      input_fields: props.config.inputs || [],
    };

    // Add voucher type
    if (voucherType.value !== 'redeemable') {
      requestData.voucher_type = voucherType.value;
    }

    // Add target amount for settlement/payable
    if (targetAmount.value && targetAmount.value > 0) {
      requestData.target_amount = targetAmount.value;
    }

    // Add feedback webhook
    if (props.config.feedback) {
      requestData.feedback_webhook = props.config.feedback;
    }

    // Build payload from collected fields
    const collectedPayload: Record<string, string> = {};
    if (props.config.payload) {
      props.config.payload.forEach(field => {
        if (payloadFields.value[field]) {
          collectedPayload[field] = payloadFields.value[field].trim();
        }
      });
    }

    // Add envelope config from driver
    if (props.config.driver) {
      const [driverId, driverVersion] = props.config.driver.includes('@')
        ? props.config.driver.split('@')
        : [props.config.driver, '1.0.0'];
      requestData.envelope = {
        enabled: true,
        driver_id: driverId,
        driver_version: driverVersion || '1.0.0',
        initial_payload: collectedPayload,  // Include payload in envelope
      };
    }

    // Add kiosk metadata (include payload fields)
    requestData.external_metadata = {
      kiosk_title: labels.value.title,
      issued_via: 'kiosk',
      issued_at: new Date().toISOString(),
      ...collectedPayload,  // Spread payload fields into metadata
    };

    const headers: Record<string, string> = {
      'Idempotency-Key': `kiosk-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    };

    const response = await axios.post('/api/v1/vouchers', requestData, { headers });

    if (response.data.success && response.data.data?.length > 0) {
      const voucher = response.data.data[0];
      issuedVoucher.value = {
        code: voucher.code,
        amount: voucher.amount,
        formatted_amount: voucher.formatted_amount || `₱${voucher.amount.toFixed(2)}`,
        redemption_url: `${window.location.origin}/disburse?code=${voucher.code}`,
      };

      // Generate QR code
      await generateQrCode(issuedVoucher.value.redemption_url);

      state.value = 'issued';
    } else {
      throw new Error(response.data.message || 'Failed to issue voucher');
    }
  } catch (err: any) {
    console.error('Voucher issuance failed:', err);
    errorMessage.value = err.response?.data?.message || err.message || 'An error occurred';
    state.value = 'error';
  }
};

const handlePrint = () => {
  window.print();
};

const handleReset = () => {
  state.value = 'input';
  // Reset amount only if not fixed
  if (!props.config.amount) {
    amount.value = null;
  }
  // Reset target only if not fixed
  if (!props.config.targetAmount) {
    targetAmount.value = null;
  }
  // Reset payload fields
  if (props.config.payload) {
    props.config.payload.forEach(field => {
      payloadFields.value[field] = '';
    });
  }
  issuedVoucher.value = null;
  qrDataUrl.value = '';
  errorMessage.value = '';
};

// ============================================================================
// SCANNER SUPPORT
// ============================================================================

const handleKeydown = (event: KeyboardEvent) => {
  // Only capture in input state
  if (state.value !== 'input') return;

  // Ignore if focused on an input
  if (document.activeElement?.tagName === 'INPUT') return;

  // Scanner typically sends rapid keystrokes ending with Enter
  if (event.key === 'Enter' && scanBuffer.value) {
    // Process scanned value
    const scanned = parseInt(scanBuffer.value, 10);
    if (!isNaN(scanned) && scanned > 0) {
      if (showTargetField.value && !targetAmount.value) {
        targetAmount.value = scanned;
      } else if (showAmountField.value) {
        amount.value = scanned;
      }
    }
    scanBuffer.value = '';
    return;
  }

  // Capture digits
  if (/^\d$/.test(event.key)) {
    scanBuffer.value += event.key;

    // Clear buffer after 100ms of no input
    if (scanTimeout.value) clearTimeout(scanTimeout.value);
    scanTimeout.value = setTimeout(() => {
      scanBuffer.value = '';
    }, 100);
  }
};

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);

  // Pre-fill from config
  if (props.config.amount) amount.value = props.config.amount;
  if (props.config.targetAmount) targetAmount.value = props.config.targetAmount;
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  if (scanTimeout.value) clearTimeout(scanTimeout.value);
});
</script>

<template>
  <div class="kiosk-container min-h-screen bg-gradient-to-b from-primary/5 to-background flex flex-col">
    <!-- Header (tap to toggle X-Ray) -->
    <header 
      class="bg-primary text-primary-foreground p-6 text-center print:hidden cursor-pointer select-none"
      @click="showXRay = !showXRay"
    >
      <h1 class="text-2xl font-bold">{{ labels.title }}</h1>
      <p v-if="labels.subtitle" class="text-primary-foreground/80 mt-1">{{ labels.subtitle }}</p>
      <p class="text-primary-foreground/50 text-xs mt-2">tap for config</p>
    </header>

    <!-- X-Ray Debug Panel -->
    <div v-if="showXRay" class="bg-muted border-b p-4 print:hidden">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">Kiosk Configuration</span>
          <button 
            class="text-xs text-muted-foreground hover:text-foreground"
            @click.stop="showXRay = false"
          >
            close
          </button>
        </div>
        <pre class="overflow-x-auto rounded-md bg-background p-3 text-xs border"><code>{{ JSON.stringify(xRayData, null, 2) }}</code></pre>
      </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4">
      <!-- INPUT STATE -->
      <Card v-if="state === 'input'" class="w-full max-w-md print:hidden">
        <CardHeader class="text-center">
          <CardTitle>{{ labels.title }}</CardTitle>
          <CardDescription v-if="typeLabel">{{ typeLabel }} Voucher</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Amount Field -->
          <div v-if="showAmountField" class="space-y-2">
            <Label>{{ labels.amountLabel }}</Label>
            <div
              class="flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-muted/50 transition-colors"
              @click="openAmountKeypad"
            >
              <span class="text-2xl font-bold">
                {{ amount ? `₱${amount.toLocaleString()}` : labels.amountPlaceholder }}
              </span>
            </div>
          </div>

          <!-- Target Amount Field (for BST/Settlement) -->
          <div v-if="showTargetField" class="space-y-2">
            <Label>{{ labels.targetLabel }}</Label>
            <div
              class="flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-muted/50 transition-colors"
              @click="openTargetKeypad"
            >
              <span class="text-2xl font-bold">
                {{ targetAmount ? `₱${targetAmount.toLocaleString()}` : labels.targetPlaceholder }}
              </span>
            </div>
          </div>

          <!-- Payload Fields (dynamic text inputs) -->
          <div v-for="field in config.payload" :key="field" class="space-y-2">
            <Label :for="field">{{ formatFieldLabel(field) }}</Label>
            <Input
              :id="field"
              v-model="payloadFields[field]"
              :placeholder="`Enter ${formatFieldLabel(field).toLowerCase()}`"
              class="h-12 text-lg"
            />
          </div>

          <!-- Submit Button -->
          <Button
            class="w-full h-14 text-lg"
            :disabled="!canSubmit"
            @click="handleSubmit"
          >
            {{ labels.buttonText }}
          </Button>
        </CardContent>
      </Card>

      <!-- SUBMITTING STATE -->
      <Card v-else-if="state === 'submitting'" class="w-full max-w-md print:hidden">
        <CardContent class="py-12 text-center">
          <Loader2 class="h-16 w-16 animate-spin mx-auto text-primary" />
          <p class="mt-4 text-lg text-muted-foreground">Issuing voucher...</p>
        </CardContent>
      </Card>

      <!-- ISSUED STATE -->
      <Card v-else-if="state === 'issued'" class="w-full max-w-md">
        <CardContent class="py-8 text-center space-y-6">
          <div class="print:hidden">
            <CheckCircle class="h-16 w-16 mx-auto text-green-500" />
          </div>
          <div>
            <h2 class="text-2xl font-bold text-green-600">{{ labels.successTitle }}</h2>
            <p class="text-muted-foreground mt-1">{{ labels.successMessage }}</p>
          </div>

          <!-- Voucher Code -->
          <div class="bg-muted p-4 rounded-lg">
            <p class="text-sm text-muted-foreground mb-1">Voucher Code</p>
            <p class="text-3xl font-mono font-bold tracking-wider">{{ issuedVoucher?.code }}</p>
          </div>

          <!-- Amount Display -->
          <div v-if="issuedVoucher?.amount" class="text-2xl font-bold">
            {{ issuedVoucher.formatted_amount }}
          </div>

          <!-- QR Code -->
          <div v-if="qrDataUrl" class="flex justify-center">
            <img :src="qrDataUrl" alt="QR Code" class="w-48 h-48" />
          </div>

          <!-- Actions -->
          <div class="flex gap-3 print:hidden">
            <Button variant="outline" class="flex-1" @click="handlePrint">
              <Printer class="mr-2 h-4 w-4" />
              {{ labels.printButton }}
            </Button>
            <Button class="flex-1" @click="handleReset">
              <RotateCcw class="mr-2 h-4 w-4" />
              {{ labels.newButton }}
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- ERROR STATE -->
      <Card v-else-if="state === 'error'" class="w-full max-w-md print:hidden">
        <CardContent class="py-8 text-center space-y-6">
          <AlertCircle class="h-16 w-16 mx-auto text-destructive" />
          <div>
            <h2 class="text-2xl font-bold text-destructive">{{ labels.errorTitle }}</h2>
            <p class="text-muted-foreground mt-2">{{ errorMessage }}</p>
          </div>
          <Button class="w-full" @click="handleReset">
            <RotateCcw class="mr-2 h-4 w-4" />
            {{ labels.retryButton }}
          </Button>
        </CardContent>
      </Card>
    </main>

    <!-- Numeric Keypads -->
    <NumericKeypad
      v-model:open="showAmountKeypad"
      mode="amount"
      :title="labels.amountKeypadTitle"
      :model-value="amount"
      :min="1"
      @confirm="confirmAmount"
    />

    <NumericKeypad
      v-model:open="showTargetKeypad"
      mode="amount"
      :title="labels.targetKeypadTitle"
      :model-value="targetAmount"
      :min="1"
      @confirm="confirmTarget"
    />
  </div>
</template>

<style>
/* Print styles - only show receipt area */
@media print {
  body * {
    visibility: hidden;
  }
  .kiosk-container,
  .kiosk-container * {
    visibility: visible;
  }
  .kiosk-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  .print\\:hidden {
    display: none !important;
  }
}
</style>
