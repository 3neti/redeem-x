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
import { useDeviceInfo } from '@/composables/useDeviceInfo';
import { useKioskHistory, type KioskHistoryEntry } from '@/composables/useKioskHistory';
import RecentVoucherIndicator from '@/components/pwa/RecentVoucherIndicator.vue';
import RecentVoucherDrawer from '@/components/pwa/RecentVoucherDrawer.vue';

// ============================================================================
// TYPES & PROPS
// ============================================================================

/**
 * Payload field configuration
 * Supports both string (backward compatible) and object formats
 */
interface PayloadFieldConfig {
  name: string;
  type?: 'text' | 'auto_device_id' | 'auto_device_metadata';
  editable?: boolean;
  required?: boolean;
  placeholder?: string;
  hidden?: boolean;
}

interface ScannerConfig {
  enabled: boolean;
  format: string;
  buffer_timeout_ms: number;
  field_mapping: Record<string, string>;
  amount_key: string | null;
  target_amount_key: string | null;
  target_override: boolean;
}

interface KioskConfig {
  title: string;
  subtitle?: string;
  campaign?: string;
  driver?: string;
  amount?: number;
  targetAmount?: number;
  inputs?: string[];
  payload?: (string | PayloadFieldConfig)[];  // Payload fields - string or object format
  feedback?: string;
  type?: 'redeemable' | 'payable' | 'settlement';
  // UI Labels (from skin config)
  amountLabel?: string;
  amountPlaceholder?: string;
  amountKeypadTitle?: string;
  targetLabel?: string;
  targetPlaceholder?: string;
  targetKeypadTitle?: string;
  cardDescription?: string;
  typeLabel?: string;  // Voucher type label (e.g., "BST", "Settlement")
  buttonText?: string;
  successTitle?: string;
  successMessage?: string;
  printButton?: string;
  newButton?: string;
  errorTitle?: string;
  retryButton?: string;
  themeColor?: string;
  logo?: string;
  // Scanner config from driver
  scanner?: ScannerConfig | null;
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
  initialScan?: Record<string, any> | null;
}

const props = withDefaults(defineProps<Props>(), {
  defaults: () => ({}),
  campaignData: null,
  initialScan: null,
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

// Device information composable
const { getDeviceId, getDeviceInfo } = useDeviceInfo();

// Payload fields state (dynamic key-value pairs)
const payloadFields = ref<Record<string, string>>({});

// ============================================================================
// HELPER FUNCTIONS (must be declared before initPayloadFields)
// ============================================================================

/**
 * Get field name from field config (string or object)
 */
const getFieldName = (fieldConfig: string | PayloadFieldConfig): string => {
  return typeof fieldConfig === 'string' ? fieldConfig : fieldConfig.name;
};

/**
 * Format payload field name to label (e.g., 'membership_id' -> 'Membership ID')
 */
const formatFieldLabel = (field: string): string => {
  return field
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

// Initialize payload fields from config
const initPayloadFields = () => {
  if (!props.config.payload) return;

  props.config.payload.forEach(fieldConfig => {
    const fieldName = getFieldName(fieldConfig);
    
    // Auto-inject based on field type
    if (typeof fieldConfig === 'object' && fieldConfig.type) {
      switch (fieldConfig.type) {
        case 'auto_device_id':
          payloadFields.value[fieldName] = getDeviceId();
          break;
        case 'auto_device_metadata':
          payloadFields.value[fieldName] = JSON.stringify(getDeviceInfo());
          break;
        default:
          payloadFields.value[fieldName] = '';
      }
    } else {
      // Default: empty string for manual entry
      payloadFields.value[fieldName] = '';
    }
  });
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
const scanFeedback = ref<string | null>(null);
const scanFeedbackTimeout = ref<ReturnType<typeof setTimeout> | null>(null);

// History management
const skinName = computed(() => {
  // Extract skin name from URL or use default
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get('skin') || 'default';
});

const {
  history,
  historyCount,
  hasHistory,
  addToHistory,
  getRelativeTime,
} = useKioskHistory(skinName.value);

// History UI state
const showHistoryDrawer = ref(false);

// ============================================================================
// COMPUTED - Merged Labels
// ============================================================================

const labels = computed(() => ({
  title: props.config.title || props.defaults.title || 'Quick Voucher',
  subtitle: props.config.subtitle || props.defaults.subtitle || '',
  cardDescription: props.config.cardDescription || props.defaults.card_description || null,
  typeLabel: props.config.typeLabel || props.defaults.type_label || null,
  amountLabel: props.config.amountLabel || props.defaults.amount_label || 'Amount',
  amountPlaceholder: props.config.amountPlaceholder || props.defaults.amount_placeholder || 'Enter amount',
  amountKeypadTitle: props.config.amountKeypadTitle || props.defaults.amount_keypad_title || 'Enter Amount',
  targetLabel: props.config.targetLabel || props.defaults.target_label || 'Target Amount',
  targetPlaceholder: props.config.targetPlaceholder || props.defaults.target_placeholder || 'Enter target amount',
  targetKeypadTitle: props.config.targetKeypadTitle || props.defaults.target_keypad_title || 'Enter Target Amount',
  buttonText: props.config.buttonText || props.defaults.button_text || 'Issue Voucher',
  successTitle: props.config.successTitle || props.defaults.success_title || 'Voucher Issued!',
  successMessage: props.config.successMessage || props.defaults.success_message || 'Scan QR code to redeem',
  printButton: props.config.printButton || props.defaults.print_button || 'Print',
  newButton: props.config.newButton || props.defaults.new_button || 'Issue Another',
  errorTitle: props.config.errorTitle || props.defaults.error_title || 'Error',
  retryButton: props.config.retryButton || props.defaults.retry_button || 'Try Again',
}));

// Determine voucher type
const voucherType = computed(() => {
  if (props.config.type) return props.config.type;
  if (props.config.targetAmount !== undefined) return 'settlement';
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
  // Use configured label if provided
  if (labels.value.typeLabel) {
    return labels.value.typeLabel;
  }
  
  // Default: capitalize voucher type
  return voucherType.value.charAt(0).toUpperCase() + voucherType.value.slice(1);
});

// ============================================================================
// ADDITIONAL PAYLOAD FIELD HELPERS
// ============================================================================

/**
 * Check if field is editable
 */
const isFieldEditable = (fieldConfig: string | PayloadFieldConfig): boolean => {
  if (typeof fieldConfig === 'string') return true;
  return fieldConfig.editable !== false;
};

/**
 * Get field placeholder
 */
const getFieldPlaceholder = (fieldConfig: string | PayloadFieldConfig): string => {
  if (typeof fieldConfig === 'string') {
    return `Enter ${formatFieldLabel(fieldConfig)}`;
  }
  return fieldConfig.placeholder || `Enter ${formatFieldLabel(fieldConfig.name)}`;
};

/**
 * Truncate long values for display
 */
const truncateValue = (value: string, maxLength: number = 40): string => {
  if (!value || value.length <= maxLength) return value;
  return value.slice(0, maxLength - 3) + '...';
};

/**
 * Check if all required payload fields are filled
 */
const payloadFieldsFilled = computed(() => {
  if (!props.config.payload || props.config.payload.length === 0) return true;
  
  return props.config.payload.every(fieldConfig => {
    const fieldName = getFieldName(fieldConfig);
    const value = payloadFields.value[fieldName];
    
    // Auto-injected fields are always considered filled
    if (typeof fieldConfig === 'object' && 
        (fieldConfig.type === 'auto_device_id' || fieldConfig.type === 'auto_device_metadata')) {
      return true;
    }
    
    // Manual fields must have non-empty value
    return value?.trim();
  });
});

// X-Ray configuration data for debug panel
const xRayData = computed(() => {
  const { getDeviceId, isSessionOnly, STORAGE_KEY } = useDeviceInfo();
  
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
    payload_values: payloadFields.value,
    device: {
      device_id: getDeviceId(),
      storage_key: STORAGE_KEY,
      is_session_only: isSessionOnly(),
    },
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
      props.config.payload.forEach(fieldConfig => {
        const fieldName = getFieldName(fieldConfig);
        if (payloadFields.value[fieldName]) {
          collectedPayload[fieldName] = payloadFields.value[fieldName].trim();
        }
      });
    }

    // Add envelope config from driver
    if (props.config.driver) {
      const [driverId, driverVersion] = props.config.driver.includes('@')
        ? props.config.driver.split('@')
        : [props.config.driver, '1.0.0'];
      requestData.envelope_config = {
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

    console.log('API Response:', response.data);

    if (response.data.data?.vouchers?.length > 0) {
      const voucher = response.data.data.vouchers[0];
      issuedVoucher.value = {
        code: voucher.code,
        amount: voucher.amount,
        formatted_amount: voucher.formatted_amount || `₱${voucher.amount.toFixed(2)}`,
        redemption_url: `${window.location.origin}/disburse?code=${voucher.code}`,
      };

      // Generate QR code
      await generateQrCode(issuedVoucher.value.redemption_url);

      // Add to history (localStorage persistence)
      addToHistory(
        issuedVoucher.value.code,
        issuedVoucher.value.amount,
        issuedVoucher.value.formatted_amount,
        issuedVoucher.value.redemption_url,
        qrDataUrl.value
      );

      state.value = 'issued';
    } else {
      throw new Error(response.data.message || 'Failed to issue voucher');
    }
  } catch (err: any) {
    console.error('Voucher issuance failed:', err);
    console.log('Error response:', err.response);
    console.log('Error response data:', err.response?.data);
    
    // Build error message with field-specific details if available
    let message = err.response?.data?.message || err.message || 'An error occurred';
    
    // If there are validation errors, append them
    if (err.response?.data?.errors) {
      const fieldErrors = Object.entries(err.response.data.errors)
        .map(([field, messages]: [string, any]) => {
          const errorList = Array.isArray(messages) ? messages : [messages];
          return errorList.join(', ');
        })
        .join('; ');
      
      if (fieldErrors) {
        message = `${message}\n\n${fieldErrors}`;
      }
    }
    
    errorMessage.value = message;
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
  // Re-initialize payload fields (preserves auto-injection)
  initPayloadFields();
  
  issuedVoucher.value = null;
  qrDataUrl.value = '';
  errorMessage.value = '';
};

// ============================================================================
// HISTORY HANDLERS
// ============================================================================

const handleOpenHistory = () => {
  showHistoryDrawer.value = true;
};

const handleShowQR = (voucher: KioskHistoryEntry) => {
  // Reuse the existing issued state display
  issuedVoucher.value = {
    code: voucher.code,
    amount: voucher.amount,
    formatted_amount: voucher.formatted_amount,
    redemption_url: voucher.redemption_url,
  };
  qrDataUrl.value = voucher.qr_data_url;
  
  // Close drawer and show issued state
  showHistoryDrawer.value = false;
  state.value = 'issued';
};

// ============================================================================
// SCANNER SUPPORT
// ============================================================================

// Resolved scanner config from driver
const scannerConfig = computed(() => props.config.scanner ?? null);
const scannerEnabled = computed(() => scannerConfig.value?.enabled ?? false);
const bufferTimeout = computed(() => scannerConfig.value?.buffer_timeout_ms ?? 100);

/**
 * Show brief scan feedback indicator
 */
const showScanFeedback = (message: string) => {
  if (scanFeedbackTimeout.value) clearTimeout(scanFeedbackTimeout.value);
  scanFeedback.value = message;
  scanFeedbackTimeout.value = setTimeout(() => {
    scanFeedback.value = null;
  }, 2000);
};

/**
 * Process a scanned JSON payload using driver field_mapping
 */
const processJsonScan = (data: Record<string, any>): boolean => {
  const config = scannerConfig.value;
  if (!config) return false;

  let fieldsPopulated = 0;

  // Map QR keys to payload fields via driver field_mapping
  if (config.field_mapping) {
    for (const [qrKey, payloadField] of Object.entries(config.field_mapping)) {
      if (data[qrKey] !== undefined && payloadField in payloadFields.value) {
        payloadFields.value[payloadField] = String(data[qrKey]);
        fieldsPopulated++;
      }
    }
  }

  // Also map any direct matches (QR key matches payload field name)
  for (const [key, value] of Object.entries(data)) {
    if (key in payloadFields.value && value !== undefined) {
      payloadFields.value[key] = String(value);
      fieldsPopulated++;
    }
  }

  // Handle target_amount from QR
  if (config.target_amount_key && data[config.target_amount_key] !== undefined) {
    const scannedTarget = Number(data[config.target_amount_key]);
    if (!isNaN(scannedTarget) && scannedTarget > 0) {
      targetAmount.value = scannedTarget;
      fieldsPopulated++;
    }
  }

  // Handle amount from QR
  if (config.amount_key && data[config.amount_key] !== undefined) {
    const scannedAmount = Number(data[config.amount_key]);
    if (!isNaN(scannedAmount) && scannedAmount > 0) {
      amount.value = scannedAmount;
      fieldsPopulated++;
    }
  }

  return fieldsPopulated > 0;
};

const handleKeydown = (event: KeyboardEvent) => {
  // Only capture in input state
  if (state.value !== 'input') return;

  // Ignore if focused on an input
  if (document.activeElement?.tagName === 'INPUT') return;

  // Scanner typically sends rapid keystrokes ending with Enter
  if (event.key === 'Enter' && scanBuffer.value) {
    const raw = scanBuffer.value;
    scanBuffer.value = '';

    // If driver scanner is enabled, try JSON parsing first
    if (scannerEnabled.value) {
      try {
        const parsed = JSON.parse(raw);
        if (typeof parsed === 'object' && parsed !== null) {
          if (processJsonScan(parsed)) {
            showScanFeedback('\u2713 QR Scanned');
            return;
          }
        }
      } catch {
        // Not JSON — fall through to other strategies
      }

      // Plain string fallback: fill first empty editable payload field
      if (raw && !/^\d+$/.test(raw)) {
        const emptyField = props.config.payload?.find(fc => {
          const name = getFieldName(fc);
          return isFieldEditable(fc) && !payloadFields.value[name]?.trim();
        });
        if (emptyField) {
          payloadFields.value[getFieldName(emptyField)] = raw;
          showScanFeedback('\u2713 Scanned');
          return;
        }
      }
    }

    // Numeric fallback (original behavior)
    const scanned = parseInt(raw, 10);
    if (!isNaN(scanned) && scanned > 0) {
      if (showTargetField.value && !targetAmount.value) {
        targetAmount.value = scanned;
      } else if (showAmountField.value) {
        amount.value = scanned;
      }
    }
    return;
  }

  // Capture characters into scan buffer
  // Driver scanner enabled: capture all printable characters (for JSON)
  // Default: capture digits only (original behavior)
  const isCapturableChar = scannerEnabled.value
    ? event.key.length === 1  // All printable characters
    : /^\d$/.test(event.key); // Digits only

  if (isCapturableChar) {
    scanBuffer.value += event.key;

    // Clear buffer after timeout of no input
    if (scanTimeout.value) clearTimeout(scanTimeout.value);
    scanTimeout.value = setTimeout(() => {
      scanBuffer.value = '';
    }, bufferTimeout.value);
  }
};

/**
 * Handle paste events — some scanners paste content directly,
 * and this also enables testing by pasting JSON into the page.
 */
const handlePaste = (event: ClipboardEvent) => {
  if (state.value !== 'input') return;
  if (!scannerEnabled.value) return;

  const pasted = event.clipboardData?.getData('text')?.trim();
  if (!pasted) return;

  const isFocusedOnInput = document.activeElement?.tagName === 'INPUT';

  // Try JSON parse — intercept even when focused on an input
  try {
    const parsed = JSON.parse(pasted);
    if (typeof parsed === 'object' && parsed !== null) {
      if (processJsonScan(parsed)) {
        event.preventDefault();
        // Blur the input so the cursor doesn't stay in the field
        if (isFocusedOnInput) (document.activeElement as HTMLElement)?.blur();
        showScanFeedback('\u2713 QR Scanned');
        return;
      }
    }
  } catch {
    // Not JSON — let normal paste proceed if focused on input
  }

  // If focused on an input, let the browser handle non-JSON paste normally
  if (isFocusedOnInput) return;

  event.preventDefault();

  // Plain string fallback: fill first empty editable payload field
  if (pasted && !/^\d+$/.test(pasted)) {
    const emptyField = props.config.payload?.find(fc => {
      const name = getFieldName(fc);
      return isFieldEditable(fc) && !payloadFields.value[name]?.trim();
    });
    if (emptyField) {
      payloadFields.value[getFieldName(emptyField)] = pasted;
      showScanFeedback('\u2713 Scanned');
      return;
    }
  }

  // Numeric fallback
  const scanned = parseInt(pasted, 10);
  if (!isNaN(scanned) && scanned > 0) {
    if (showTargetField.value && !targetAmount.value) {
      targetAmount.value = scanned;
    } else if (showAmountField.value) {
      amount.value = scanned;
    }
  }
};

// ============================================================================
// LIFECYCLE
// ============================================================================

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('paste', handlePaste);

  // Pre-fill from config
  if (props.config.amount) amount.value = props.config.amount;
  if (props.config.targetAmount) targetAmount.value = props.config.targetAmount;

  // Process ?scan= query param if present
  if (props.initialScan && scannerEnabled.value) {
    if (processJsonScan(props.initialScan)) {
      showScanFeedback('\u2713 Pre-filled from URL');
    }
  }
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('paste', handlePaste);
  if (scanTimeout.value) clearTimeout(scanTimeout.value);
  if (scanFeedbackTimeout.value) clearTimeout(scanFeedbackTimeout.value);
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

    <!-- Scan Feedback Toast -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      leave-active-class="transition-all duration-300 ease-in"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div v-if="scanFeedback" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 bg-green-600 text-white px-4 py-2 rounded-full shadow-lg text-sm font-medium">
        {{ scanFeedback }}
      </div>
    </Transition>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4">
      <!-- INPUT STATE -->
      <Card v-if="state === 'input'" class="w-full max-w-md print:hidden">
        <CardHeader class="text-center">
          <CardTitle>{{ labels.title }}</CardTitle>
          <CardDescription v-if="labels.cardDescription || typeLabel">
            {{ labels.cardDescription || `${typeLabel} Voucher` }}
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Amount Field -->
          <div v-if="showAmountField" class="space-y-2">
            <Label>{{ labels.amountLabel }}</Label>
            <div
              class="flex items-center justify-center p-4 border rounded-lg cursor-pointer hover:bg-muted/50 transition-colors"
              @click="openAmountKeypad"
            >
              <span class="text-2xl font-bold text-center">
                {{ amount ? `₱${amount.toLocaleString()}` : labels.amountPlaceholder }}
              </span>
            </div>
          </div>

          <!-- Target Amount Field (for Settlement) -->
          <div v-if="showTargetField" class="space-y-2">
            <Label>{{ labels.targetLabel }}</Label>
            <div
              class="flex items-center justify-center p-4 border rounded-lg cursor-pointer hover:bg-muted/50 transition-colors"
              @click="openTargetKeypad"
            >
              <span class="text-2xl font-bold text-center">
                {{ targetAmount ? `₱${targetAmount.toLocaleString()}` : labels.targetPlaceholder }}
              </span>
            </div>
          </div>

          <!-- Payload Fields (dynamic text inputs) -->
          <template v-for="fieldConfig in config.payload" :key="getFieldName(fieldConfig)">
            <!-- Hidden fields: still in data, not rendered -->
            <template v-if="typeof fieldConfig === 'object' && fieldConfig.hidden" />

            <!-- Editable Field -->
            <div v-else-if="isFieldEditable(fieldConfig)" class="space-y-2">
              <Label :for="getFieldName(fieldConfig)">{{ formatFieldLabel(getFieldName(fieldConfig)) }}</Label>
              <Input
                :id="getFieldName(fieldConfig)"
                v-model="payloadFields[getFieldName(fieldConfig)]"
                :placeholder="getFieldPlaceholder(fieldConfig)"
                class="h-12 text-lg text-center"
              />
            </div>
            
            <!-- Readonly Auto-Injected Field -->
            <div v-else class="space-y-2">
              <Label class="text-muted-foreground">{{ formatFieldLabel(getFieldName(fieldConfig)) }}</Label>
              <div class="p-3 bg-muted rounded-lg border border-dashed">
                <p class="text-sm font-mono text-center break-all">
                  {{ truncateValue(payloadFields[getFieldName(fieldConfig)]) }}
                </p>
                <p class="text-xs text-muted-foreground text-center mt-1">
                  Auto-generated device identifier
                </p>
              </div>
            </div>
          </template>

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
            <p class="text-muted-foreground mt-2 whitespace-pre-line">{{ errorMessage }}</p>
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

    <!-- History Components -->
    <RecentVoucherIndicator
      :count="historyCount"
      @open="handleOpenHistory"
    />

    <RecentVoucherDrawer
      v-model:open="showHistoryDrawer"
      :vouchers="history"
      :get-relative-time="getRelativeTime"
      @show-qr="handleShowQR"
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
