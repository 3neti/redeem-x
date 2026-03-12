<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useChargeBreakdown } from '@/composables/useChargeBreakdown';
import { usePhoneFormat } from '@/composables/usePhoneFormat';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetFooter } from '@/components/ui/sheet';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import PhoneInput from '@/components/ui/phone-input/PhoneInput.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Wallet, Plus, Minus, Settings as SettingsIcon, Loader2, Save, ChevronDown, RotateCcw } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useToast } from '@/components/ui/toast/use-toast';
import NumericKeypad from '@/components/NumericKeypad.vue';

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

interface EnvelopeDriver {
  id: string;
  version: string;
  title: string;
  description: string;
  key: string;
}

interface Props {
  campaigns?: Campaign[];
  inputFieldOptions?: InputFieldOption[];
  walletBalance?: number;
  formattedBalance?: string;
  envelopeDrivers?: EnvelopeDriver[];
}

const props = withDefaults(defineProps<Props>(), {
  campaigns: () => [],
  inputFieldOptions: () => [],
  walletBalance: 0,
  formattedBalance: '₱0.00',
  envelopeDrivers: () => [],
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

// Code & Expiry
const prefix = ref<string>('');
const mask = ref<string>('');
const ttlDays = ref<number | null>(null);

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
const ogMetaSource = ref<'message' | 'url' | 'splash' | null>(null);

// Settlement envelope
const envelopeConfig = ref<any>(null);
const selectedDriverKey = ref<string>('');

// Rail & Fees
const settlementRail = ref<'auto' | 'INSTAPAY' | 'PESONET' | null>(null);
const feeStrategy = ref<'absorb' | 'include' | 'add'>('absorb');

// Campaign state
const selectedCampaignId = ref<string>('');
const selectedCampaign = ref<Campaign | null>(null);

// UI state
const loading = ref(false);
const error = ref<string | null>(null);
const showAmountKeypad = ref(false);
const showCountKeypad = ref(false);
const showTargetAmountKeypad = ref(false);
const showInterestRateKeypad = ref(false);
const showCostBreakdownModal = ref(false);

// Sheet state management (following plan architecture)
const sheetState = ref({
  campaign: { open: false },
  inputs: { open: false },
  validation: { open: false, activeTab: 'payee' as 'payee' | 'secret' | 'location' | 'time' },
  feedback: { open: false },
  rider: { open: false },
  envelope: { open: false },
  railFees: { open: false },
  codeExpiry: { open: false },
  options: { open: false }, // Menu sheet
});

// Conditions presets — configurable
const secretPresets = [
  { label: 'Fruits', words: ['Mango', 'Apple', 'Cherry', 'Banana', 'Grape', 'Melon'] },
  { label: 'Animals', words: ['Tiger', 'Eagle', 'Shark', 'Panda', 'Wolf', 'Falcon'] },
  { label: 'Cars', words: ['Tesla', 'Honda', 'BMW', 'Toyota', 'Audi', 'Ford'] },
];

const locationPresets = [
  { name: 'BGC', latitude: 14.5547, longitude: 121.0508, radius: 500 },
  { name: 'Makati CBD', latitude: 14.5547, longitude: 121.0244, radius: 500 },
  { name: 'Ortigas', latitude: 14.5880, longitude: 121.0614, radius: 500 },
];

const timePresets = [
  { name: 'Office Hours', start_time: '09:00', end_time: '17:00' },
  { name: 'Business Hours', start_time: '08:00', end_time: '18:00' },
  { name: 'Morning', start_time: '06:00', end_time: '12:00' },
  { name: 'Afternoon', start_time: '12:00', end_time: '18:00' },
  { name: 'Evening', start_time: '18:00', end_time: '22:00' },
];

// Saved favorites (localStorage)
const savedPlaces = ref<{ name: string; latitude: number; longitude: number; radius: number }[]>([]);
const savedTimes = ref<{ name: string; start_time: string; end_time: string }[]>([]);
const savePlaceName = ref('');
const saveTimeName = ref('');
const showSavePlaceInput = ref(false);
const showSaveTimeInput = ref(false);

const loadSavedPlaces = () => {
  try {
    const raw = localStorage.getItem('pwa_saved_places');
    if (raw) savedPlaces.value = JSON.parse(raw);
  } catch { /* ignore */ }
};

const loadSavedTimes = () => {
  try {
    const raw = localStorage.getItem('pwa_saved_times');
    if (raw) savedTimes.value = JSON.parse(raw);
  } catch { /* ignore */ }
};

const savePlace = () => {
  if (!savePlaceName.value.trim() || !locationValidation.value?.latitude) return;
  savedPlaces.value.push({
    name: savePlaceName.value.trim(),
    latitude: locationValidation.value.latitude,
    longitude: locationValidation.value.longitude,
    radius: locationValidation.value.radius || 100,
  });
  localStorage.setItem('pwa_saved_places', JSON.stringify(savedPlaces.value));
  savePlaceName.value = '';
  showSavePlaceInput.value = false;
};

const deletePlace = (index: number) => {
  savedPlaces.value.splice(index, 1);
  localStorage.setItem('pwa_saved_places', JSON.stringify(savedPlaces.value));
};

const applyPlace = (place: { latitude: number; longitude: number; radius: number }) => {
  locationValidation.value = { latitude: place.latitude, longitude: place.longitude, radius: place.radius };
};

const saveTimeWindow = () => {
  if (!saveTimeName.value.trim() || !timeValidation.value?.start_time) return;
  savedTimes.value.push({
    name: saveTimeName.value.trim(),
    start_time: timeValidation.value.start_time,
    end_time: timeValidation.value.end_time,
  });
  localStorage.setItem('pwa_saved_times', JSON.stringify(savedTimes.value));
  saveTimeName.value = '';
  showSaveTimeInput.value = false;
};

const deleteTimeWindow = (index: number) => {
  savedTimes.value.splice(index, 1);
  localStorage.setItem('pwa_saved_times', JSON.stringify(savedTimes.value));
};

const applyTimeWindow = (tw: { start_time: string; end_time: string }) => {
  timeValidation.value = { start_time: tw.start_time, end_time: tw.end_time, timezone: 'Asia/Manila' };
};

const payeeMode = ref<'anyone' | 'mobile' | 'vendor'>('anyone');
const payeeError = ref('');

const payeeInputRef = ref<HTMLElement | null>(null);
const vendorInputRef = ref<HTMLInputElement | null>(null);
const secretInputRef = ref<HTMLInputElement | null>(null);

const pickSecret = (word: string) => {
  validationSecret.value = validationSecret.value === word ? '' : word;
};

const focusPayeeInput = () => {
  nextTick(() => {
    if (payeeMode.value === 'mobile' && payeeInputRef.value) {
      const input = payeeInputRef.value.$el?.querySelector?.('input') as HTMLInputElement
        ?? payeeInputRef.value?.querySelector?.('input') as HTMLInputElement;
      if (input) { input.focus(); input.select(); }
    } else if (payeeMode.value === 'vendor' && vendorInputRef.value) {
      const el = (vendorInputRef.value as any)?.$el ?? vendorInputRef.value;
      if (el?.focus) { el.focus(); el.select?.(); }
    }
  });
};

const setPayeeMode = (mode: 'anyone' | 'mobile' | 'vendor') => {
  payeeMode.value = mode;
  payeeError.value = '';
  if (mode === 'anyone') payee.value = '';
  else if (mode === 'mobile' && payeeType.value !== 'mobile') payee.value = '';
  else if (mode === 'vendor' && payeeType.value !== 'vendor') payee.value = 'VENDOR-';
  if (mode !== 'anyone') focusPayeeInput();
};

const validateConditions = (): boolean => {
  payeeError.value = '';
  if (payeeMode.value === 'mobile') {
    const ph = payee.value.trim();
    // Extract digits only — accepts E.164 (+639173011987), 09-format, or formatted (917) 301-1987
    const digits = ph.replace(/\D/g, '');
    const isValid = /^\+63\d{10}$/.test(ph)        // E.164
      || /^63\d{10}$/.test(digits)                  // 63 + 10 digits
      || /^09\d{9}$/.test(digits)                   // 09 + 9 digits
      || /^9\d{9}$/.test(digits);                   // 9 + 9 digits (no leading 0)
    if (!ph || !isValid) {
      payeeError.value = 'Enter a valid Philippine mobile number';
      sheetState.value.validation.activeTab = 'payee';
      return false;
    }
  } else if (payeeMode.value === 'vendor') {
    if (!payee.value.trim()) {
      payeeError.value = 'Enter a vendor alias';
      sheetState.value.validation.activeTab = 'payee';
      return false;
    }
  }
  return true;
};

const closeConditions = () => {
  if (validateConditions()) {
    sheetState.value.validation.open = false;
  }
};

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

// Dynamic button text based on voucher type
const generateButtonText = computed(() => {
  const countText = count.value > 1 ? `${count.value} ` : '';
  const voucherText = count.value > 1 ? 'Vouchers' : 'Voucher';
  
  switch (voucherType.value) {
    case 'payable':
      return `Generate ${countText}Payable ${voucherText}`;
    case 'settlement':
      return `Generate ${countText}Settlement ${voucherText}`;
    case 'redeemable':
    default:
      return `Generate ${countText}Redeemable ${voucherText}`;
  }
});

// Input field categories — configurable ordering and grouping
const inputFieldCategories = [
  { label: 'Capture', fields: ['signature', 'selfie', 'location'] },
  { label: 'Verification', fields: ['otp', 'kyc'] },
  { label: 'Details', fields: ['email', 'reference_code', 'name', 'address', 'birth_date'] },
];

// Map categories to full option objects from props
const categorizedFields = computed(() => {
  return inputFieldCategories.map(category => ({
    label: category.label,
    options: category.fields
      .map(value => props.inputFieldOptions.find(opt => opt.value === value))
      .filter((opt): opt is InputFieldOption => !!opt),
  }));
});

// Input field selection summary
const inputFieldsSummary = computed(() => {
  if (selectedInputFields.value.length === 0) return 'None selected';
  if (selectedInputFields.value.length === 1) return `${selectedInputFields.value.length} input`;
  return `${selectedInputFields.value.length} inputs`;
});

// Get input field labels for display
const inputFieldLabels = computed(() => {
  return selectedInputFields.value.map(field => {
    const option = props.inputFieldOptions.find(opt => opt.value === field);
    return option?.label || field;
  });
});

// Validation summary - check if actually configured with values
const hasLocationValidation = computed(() => {
  return locationValidation.value?.latitude && locationValidation.value?.longitude;
});

const hasTimeValidation = computed(() => {
  return timeValidation.value?.start_time && timeValidation.value?.end_time;
});

// Validation badges with actual values
const validationBadges = computed(() => {
  const badges: { label: string; value: string; variant?: string }[] = [];
  const { formatForDisplay } = usePhoneFormat();
  
  // Location
  if (hasLocationValidation.value) {
    badges.push({
      label: 'Location',
      value: `${locationValidation.value.radius || 100}m radius`,
      variant: 'default'
    });
  }
  
  // Time
  if (hasTimeValidation.value) {
    badges.push({
      label: 'Time',
      value: `${timeValidation.value.start_time}-${timeValidation.value.end_time}`,
      variant: 'default'
    });
  }
  
  // Secret
  if (validationSecret.value) {
    badges.push({
      label: 'Secret',
      value: '•'.repeat(Math.min(validationSecret.value.length, 6)),
      variant: 'default'
    });
  }
  
  // Payee
  if (normalizedPayee.value) {
    const label = payeeType.value === 'mobile' ? 'Mobile' : 'Vendor';
    const displayValue = payeeType.value === 'mobile' 
      ? formatForDisplay(normalizedPayee.value)
      : normalizedPayee.value;
    badges.push({
      label: label,
      value: displayValue,
      variant: 'secondary'
    });
  }
  
  return badges;
});

const validationSummary = computed(() => {
  return validationBadges.value.length > 0 
    ? `${validationBadges.value.length} rule${validationBadges.value.length > 1 ? 's' : ''}` 
    : 'None';
});

// Feedback validation
const feedbackEmailError = computed(() => {
  if (!feedbackEmail.value) return '';
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(feedbackEmail.value) ? '' : 'Invalid email address';
});

const feedbackMobileError = computed(() => {
  if (!feedbackMobile.value) return '';
  
  // Extract digits only to handle various formats
  const digitsOnly = feedbackMobile.value.replace(/\D/g, '');
  
  // Valid formats:
  // - E.164: +639173011987 (12 digits with +63)
  // - PH format: 09173011987 (11 digits starting with 0)
  // - Just digits: 9173011987 (10 digits)
  
  // Check if E.164 format
  if (feedbackMobile.value.startsWith('+63') && digitsOnly.length === 12 && digitsOnly.startsWith('63')) {
    return ''; // Valid E.164
  }
  
  // Check if PH format (09XXXXXXXXX)
  if (digitsOnly.length === 11 && digitsOnly.startsWith('09')) {
    return ''; // Valid PH format
  }
  
  // Check if just 10 digits (9XXXXXXXXX)
  if (digitsOnly.length === 10 && digitsOnly.startsWith('9')) {
    return ''; // Valid without country code or leading 0
  }
  
  return 'Invalid mobile number';
});

const feedbackWebhookError = computed(() => {
  if (!feedbackWebhook.value) return '';
  try {
    const url = new URL(feedbackWebhook.value);
    return (url.protocol === 'http:' || url.protocol === 'https:') ? '' : 'Must be HTTP or HTTPS URL';
  } catch {
    return 'Invalid URL format';
  }
});

const hasFeedbackErrors = computed(() => {
  return !!feedbackEmailError.value || !!feedbackMobileError.value || !!feedbackWebhookError.value;
});

// Feedback badges with actual values
const feedbackBadges = computed(() => {
  const badges: { label: string; value: string; variant?: string }[] = [];
  const { formatForDisplay } = usePhoneFormat();
  
  if (feedbackEmail.value && !feedbackEmailError.value) {
    badges.push({
      label: 'Email',
      value: feedbackEmail.value,
      variant: 'secondary'
    });
  }
  
  if (feedbackMobile.value && !feedbackMobileError.value) {
    badges.push({
      label: 'SMS',
      value: formatForDisplay(feedbackMobile.value),
      variant: 'secondary'
    });
  }
  
  if (feedbackWebhook.value && !feedbackWebhookError.value) {
    // Truncate long URLs
    const displayUrl = feedbackWebhook.value.length > 30 
      ? feedbackWebhook.value.substring(0, 30) + '...' 
      : feedbackWebhook.value;
    badges.push({
      label: 'Webhook',
      value: displayUrl,
      variant: 'secondary'
    });
  }
  
  return badges;
});

// Feedback summary (for backward compatibility)
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

// Code & Expiry summary
const codeExpirySummary = computed(() => {
  const parts = [];
  if (prefix.value) parts.push(prefix.value);
  if (mask.value) parts.push(mask.value);
  if (ttlDays.value) parts.push(`${ttlDays.value}d`);
  return parts.length > 0 ? parts.join(' · ') : 'Default';
});

const hasCodeExpiry = computed(() => !!prefix.value || !!mask.value || !!ttlDays.value);

// Mask validation
const maskError = computed(() => {
  if (!mask.value) return '';
  if (!/^[*\-]+$/.test(mask.value)) return 'Only * and - characters allowed';
  const asterisks = (mask.value.match(/\*/g) || []).length;
  if (asterisks < 4) return `Need at least 4 asterisks (currently ${asterisks})`;
  if (asterisks > 8) return `Maximum 8 asterisks (currently ${asterisks})`;
  return '';
});

// Rider config summary
const riderConfigSummary = computed(() => {
  const items = [];
  if (riderMessage.value) items.push('Message');
  if (riderUrl.value) items.push('URL');
  if (riderSplash.value) items.push('Splash');
  const base = items.length > 0 ? items.join(', ') : 'Not configured';
  return ogMetaSource.value ? `${base} · OG: ${ogMetaSource.value}` : base;
});

// OG Meta source toggle — radio that can be deselected
const toggleOgSource = (field: 'message' | 'url' | 'splash') => {
  ogMetaSource.value = ogMetaSource.value === field ? null : field;
};

// Envelope config summary
const envelopeConfigSummary = computed(() => {
  if (!envelopeConfig.value) return 'Not configured';
  if (selectedDriverKey.value) {
    const driver = props.envelopeDrivers.find(d => d.key === selectedDriverKey.value);
    return driver ? `${driver.title} (${driver.version})` : 'Configured';
  }
  return 'Configured';
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

// Group charges by category for display
const chargesByCategory = computed(() => {
  if (!breakdown.value || !breakdown.value.breakdown) return {};
  
  const categorized: Record<string, any[]> = {};
  
  breakdown.value.breakdown.forEach((charge: any, index: number) => {
    const category = charge.category || 'other';
    if (!categorized[category]) {
      categorized[category] = [];
    }
    categorized[category].push({
      ...charge,
      index,
    });
  });
  
  return categorized;
});

// Category labels for display
const categoryLabels: Record<string, string> = {
  'system': 'System Fees',
  'escrow': 'Escrow',
  'gateway': 'Gateway Fees',
  'other': 'Other Charges',
};

// Show modal when cost is clicked
const showCostModal = () => {
  if (amount.value && amount.value > 0) {
    console.log('[PWA Generate] Opening cost modal');
    console.log('[PWA Generate] breakdown.value:', breakdown.value);
    console.log('[PWA Generate] chargesByCategory:', chargesByCategory.value);
    showCostBreakdownModal.value = true;
  }
};

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
// WATCHERS
// ============================================================================

// Auto-select first driver when envelope is enabled
watch(envelopeConfig, (newValue) => {
  if (newValue && !selectedDriverKey.value && props.envelopeDrivers.length > 0) {
    selectedDriverKey.value = props.envelopeDrivers[0].key;
  }
});

// ============================================================================
// METHODS
// ============================================================================

// Open sheets
const openSheet = (sheet: keyof typeof sheetState.value) => {
  sheetState.value[sheet].open = true;
};

// Numeric keypad handlers
const openAmountKeypad = () => {
  showAmountKeypad.value = true;
};

const openCountKeypad = () => {
  showCountKeypad.value = true;
};

const confirmAmount = (value: number) => {
  amount.value = value;
  showAmountKeypad.value = false;
};

const confirmCount = (value: number) => {
  count.value = value;
  showCountKeypad.value = false;
};

const incrementCount = () => {
  count.value = Math.min(count.value + 1, 100);
};

const decrementCount = () => {
  count.value = Math.max(count.value - 1, 1);
};

const openTargetAmountKeypad = () => {
  showTargetAmountKeypad.value = true;
};

const openInterestRateKeypad = () => {
  showInterestRateKeypad.value = true;
};

const confirmTargetAmount = (value: number) => {
  targetAmount.value = value;
  // Back-calculate interest rate in settlement mode
  if (voucherType.value === 'settlement' && amount.value && amount.value > 0) {
    interestRate.value = parseFloat((((value / amount.value) - 1) * 100).toFixed(2));
  }
  showTargetAmountKeypad.value = false;
};

const confirmInterestRate = (value: number) => {
  interestRate.value = value;
  showInterestRateKeypad.value = false;
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
    
    // Add rider (flat keys — API expects rider_message, rider_url, etc.)
    if (riderMessage.value) requestData.rider_message = riderMessage.value;
    if (riderUrl.value) requestData.rider_url = riderUrl.value;
    if (riderRedirectTimeout.value !== null) requestData.rider_redirect_timeout = riderRedirectTimeout.value;
    if (riderSplash.value) requestData.rider_splash = riderSplash.value;
    if (riderSplashTimeout.value !== null) requestData.rider_splash_timeout = riderSplashTimeout.value;
    if (ogMetaSource.value) requestData.rider_og_source = ogMetaSource.value;
    
    // Add code & expiry
    if (prefix.value) requestData.prefix = prefix.value;
    if (mask.value) requestData.mask = mask.value;
    if (ttlDays.value) requestData.ttl_days = ttlDays.value;
    
    // Add settlement rail and fee strategy
    if (settlementRail.value && settlementRail.value !== 'auto') {
      requestData.settlement_rail = settlementRail.value;
    }
    if (feeStrategy.value !== 'absorb') {
      requestData.fee_strategy = feeStrategy.value;
    }
    
    // Add envelope config
    if (envelopeConfig.value && selectedDriverKey.value) {
      const [driverId, driverVersion] = selectedDriverKey.value.split('@');
      requestData.envelope = {
        enabled: true,
        driver_id: driverId,
        driver_version: driverVersion,
        initial_payload: {},
      };
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
      ogMetaSource.value = instructions.rider.og_source || null;
    }
    
    // Apply count if present
    if (instructions.count) {
      count.value = instructions.count;
    }
    
    // Apply code & expiry
    prefix.value = instructions.prefix || '';
    mask.value = instructions.mask || '';
    if (instructions.ttl) {
      const match = instructions.ttl.match?.(/P(\d+)D/);
      ttlDays.value = match ? parseInt(match[1]) : null;
    } else {
      ttlDays.value = null;
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

// Clear all input fields
const clearAllInputFields = () => {
  // Keep auto-added fields (like OTP for mobile validation)
  selectedInputFields.value = selectedInputFields.value.filter(field => 
    autoAddedFields.value.has(field)
  );
  toast({
    title: 'Input fields cleared',
    description: autoAddedFields.value.size > 0 
      ? 'Required fields retained' 
      : 'All fields cleared',
  });
};

// Clear rider config (Phase 8)
const clearRider = () => {
  riderMessage.value = '';
  riderUrl.value = '';
  riderRedirectTimeout.value = null;
  riderSplash.value = '';
  riderSplashTimeout.value = null;
  ogMetaSource.value = null;
};

// Full reset — everything back to factory defaults
const handleClearAll = () => {
  resetState();
  clearRider();
  prefix.value = '';
  mask.value = '';
  ttlDays.value = null;
  envelopeConfig.value = null;
  selectedDriverKey.value = '';
  settlementRail.value = null;
  feeStrategy.value = 'absorb';
  riderMessage.value = '';
  riderUrl.value = '';
  riderRedirectTimeout.value = null;
  riderSplash.value = '';
  riderSplashTimeout.value = null;
  clearSavedState();
  toast({
    title: 'Reset',
    description: 'All settings cleared',
  });
};

// Save as campaign
const saveCampaignLoading = ref(false);
const saveCampaignName = ref('');
const showSaveCampaignDialog = ref(false);

const openSaveCampaignDialog = () => {
  // Generate default name with timestamp
  const now = new Date();
  const dateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
  saveCampaignName.value = `Campaign ${dateStr} ${timeStr}`;
  showSaveCampaignDialog.value = true;
  sheetState.value.options.open = false;
};

const saveAsCampaign = async () => {
  if (!saveCampaignName.value.trim()) {
    toast({
      title: 'Name required',
      description: 'Please enter a campaign name',
      variant: 'destructive',
    });
    return;
  }
  
  saveCampaignLoading.value = true;
  
  // Build instructions object - MUST match VoucherInstructionsData::rules() structure
  const instructions: any = {
      // Cash section (required)
      cash: {
        amount: amount.value || 0,
        currency: 'PHP',
        settlement_rail: settlementRail.value || null,
        fee_strategy: feeStrategy.value || 'absorb',
        validation: {
          secret: validationSecret.value || null,
          mobile: (payeeType.value === 'mobile' ? normalizedPayee.value : null) || null,
          payable: (payeeType.value === 'vendor' ? normalizedPayee.value : null) || null,
          country: null,
          location: null,
          radius: null,
        },
      },
      
      // Inputs (required object)
      inputs: {
        fields: selectedInputFields.value.filter(f => !autoAddedFields.value.has(f)),
      },
      
      // Feedback (required object)
      feedback: {
        email: feedbackEmail.value || null,
        mobile: feedbackMobile.value || null,
        webhook: feedbackWebhook.value || null,
      },
      
      // Rider (required object)
      rider: {
        message: riderMessage.value || null,
        url: riderUrl.value || null,
        redirect_timeout: riderRedirectTimeout.value,
        splash: riderSplash.value || null,
        splash_timeout: riderSplashTimeout.value,
        og_source: ogMetaSource.value,
      },
      
      // Required fields
      count: count.value || 1,
      prefix: prefix.value || null,
      mask: mask.value || null,
      ttl: ttlDays.value ? `P${ttlDays.value}D` : null,
      
      // Optional voucher type fields
      voucher_type: voucherType.value !== 'redeemable' ? voucherType.value : null,
      target_amount: targetAmount.value || null,
      rules: null,
    };
    
    // Add validation section if any rules exist
    if (locationValidation.value?.latitude && locationValidation.value?.longitude) {
      if (!instructions.validation) instructions.validation = {};
      instructions.validation.location = {
        required: true,
        target_lat: locationValidation.value.latitude,
        target_lng: locationValidation.value.longitude,
        radius_meters: locationValidation.value.radius || 100,
        on_failure: 'block',
      };
    }
    if (timeValidation.value?.start_time && timeValidation.value?.end_time) {
      if (!instructions.validation) instructions.validation = {};
      instructions.validation.time = {
        window: {
          start_time: timeValidation.value.start_time,
          end_time: timeValidation.value.end_time,
          timezone: timeValidation.value.timezone || 'Asia/Manila',
        },
        limit_minutes: null,
        track_duration: true,
      };
    }
    
    // Save campaign via Inertia router (handles redirects properly)
    router.post('/settings/campaigns', 
      {
        name: saveCampaignName.value.trim(),
        status: 'active', // Required: draft, active, or archived
        instructions,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          toast({
            title: 'Campaign saved',
            description: `"${saveCampaignName.value}" has been saved`,
          });
          
          showSaveCampaignDialog.value = false;
          saveCampaignName.value = '';
          
          // Reload campaigns list
          router.reload({ only: ['campaigns'] });
        },
        onError: (errors) => {
          console.error('[Campaign Save Error]', errors);
          
          const errorMessage = Object.values(errors).flat().join(', ');
          toast({
            title: 'Save failed',
            description: errorMessage,
            variant: 'destructive',
          });
        },
        onFinish: () => {
          saveCampaignLoading.value = false;
        },
      }
    );
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
  payeeMode.value = 'anyone';
  validationSecret.value = '';
  locationValidation.value = { latitude: null, longitude: null, radius: null };
  timeValidation.value = { start_time: '', end_time: '', timezone: 'Asia/Manila' };
  feedbackEmail.value = '';
  feedbackMobile.value = '';
  feedbackWebhook.value = '';
  prefix.value = '';
  mask.value = '';
  ttlDays.value = null;
  selectedCampaignId.value = '';
  selectedCampaign.value = null;
  autoAddedFields.value.clear();
};

// Watch for settlement type changes (from Portal.vue)
watch(voucherType, (newType) => {
  if (newType === 'settlement' && amount.value && Number(interestRate.value || 0) >= 0) {
    targetAmount.value = parseFloat((amount.value * (1 + Number(interestRate.value || 0) / 100)).toFixed(2));
  }
  if (newType === 'payable') {
    amount.value = 0;
  }
});

watch([amount, interestRate], ([newAmount, newRate]) => {
  const rate = Number(newRate || 0);
  if (voucherType.value === 'settlement' && newAmount && rate >= 0) {
    targetAmount.value = parseFloat((newAmount * (1 + rate / 100)).toFixed(2));
  }
});

// State persistence (Phase 12)
const STORAGE_KEY = 'pwa_voucher_wizard_state';

// Save state to localStorage
const saveState = () => {
  try {
    const state = {
      amount: amount.value,
      count: count.value,
      voucherType: voucherType.value,
      selectedInputFields: selectedInputFields.value,
      targetAmount: targetAmount.value,
      interestRate: interestRate.value,
      payee: payee.value,
      validationSecret: validationSecret.value,
      feedbackEmail: feedbackEmail.value,
      feedbackMobile: feedbackMobile.value,
      feedbackWebhook: feedbackWebhook.value,
      prefix: prefix.value,
      mask: mask.value,
      ttlDays: ttlDays.value,
      settlementRail: settlementRail.value,
      feeStrategy: feeStrategy.value,
      selectedCampaignId: selectedCampaignId.value,
      ogMetaSource: ogMetaSource.value,
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch (e) {
    console.error('Failed to save state:', e);
  }
};

// Restore state from localStorage
const restoreState = () => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      const state = JSON.parse(saved);
      if (state.amount) amount.value = state.amount;
      if (state.count) count.value = state.count;
      if (state.voucherType) voucherType.value = state.voucherType;
      if (state.selectedInputFields) selectedInputFields.value = state.selectedInputFields;
      if (state.targetAmount) targetAmount.value = state.targetAmount;
      if (state.interestRate !== undefined) interestRate.value = Number(state.interestRate) || 0;
      if (state.payee) {
        payee.value = state.payee;
        // Sync payeeMode from restored value
        const normalized = state.payee.trim();
        if (!normalized || normalized.toUpperCase() === 'CASH') payeeMode.value = 'anyone';
        else if (/^(\+|09|\+63)/.test(normalized)) payeeMode.value = 'mobile';
        else payeeMode.value = 'vendor';
      }
      if (state.validationSecret) validationSecret.value = state.validationSecret;
      if (state.feedbackEmail) feedbackEmail.value = state.feedbackEmail;
      if (state.feedbackMobile) feedbackMobile.value = state.feedbackMobile;
      if (state.feedbackWebhook) feedbackWebhook.value = state.feedbackWebhook;
      if (state.prefix) prefix.value = state.prefix;
      if (state.mask) mask.value = state.mask;
      if (state.ttlDays) ttlDays.value = state.ttlDays;
      if (state.settlementRail) settlementRail.value = state.settlementRail;
      if (state.feeStrategy) feeStrategy.value = state.feeStrategy;
      if (state.selectedCampaignId) selectedCampaignId.value = state.selectedCampaignId;
      if (state.ogMetaSource) ogMetaSource.value = state.ogMetaSource;
    }
  } catch (e) {
    console.error('Failed to restore state:', e);
  }
};

// Clear saved state
const clearSavedState = () => {
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch (e) {
    console.error('Failed to clear state:', e);
  }
};

// Watch key fields and save state on change
watch(
  [amount, count, voucherType, selectedInputFields, targetAmount, payee, validationSecret, feedbackEmail, settlementRail, prefix, mask, ttlDays, ogMetaSource],
  () => {
    saveState();
  },
  { deep: true }
);

// Restore state on mount
onMounted(() => {
  restoreState();
  loadSavedPlaces();
  loadSavedTimes();
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
            <div class="flex items-center">
              <h1 class="text-lg font-semibold">Generate</h1>
              <button 
                class="ml-1.5 p-0.5 text-muted-foreground/60 hover:text-foreground transition-colors disabled:invisible"
                :disabled="count <= 1"
                @click="decrementCount"
              >
                <Minus class="h-3 w-3" />
              </button>
              <button 
                class="text-lg font-semibold tabular-nums px-0.5 hover:text-primary/70 transition-colors"
                @click="openCountKeypad"
              >{{ count }}</button>
              <button 
                class="p-0.5 text-muted-foreground/60 hover:text-foreground transition-colors disabled:opacity-20"
                :disabled="count >= 100"
                @click="incrementCount"
              >
                <Plus class="h-3 w-3" />
              </button>
              <span class="text-lg font-semibold">{{ count === 1 ? 'voucher' : 'vouchers' }}</span>
            </div>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <button class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
                  {{ voucherTypeDisplay }}
                  <ChevronDown class="h-3 w-3" />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start">
                <DropdownMenuItem @click="voucherType = 'redeemable'">
                  <span :class="voucherType === 'redeemable' && 'font-semibold'">Redeemable</span>
                </DropdownMenuItem>
                <DropdownMenuItem @click="voucherType = 'payable'">
                  <span :class="voucherType === 'payable' && 'font-semibold'">Payable</span>
                </DropdownMenuItem>
                <DropdownMenuItem @click="voucherType = 'settlement'">
                  <span :class="voucherType === 'settlement' && 'font-semibold'">Settlement</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <div class="flex items-center">
          <Button variant="ghost" size="icon" @click="handleClearAll">
            <RotateCcw class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" @click="openSheet('options')">
            <SettingsIcon class="h-5 w-5" />
          </Button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <div class="flex flex-col h-[calc(100dvh-64px-80px)]">
      <!-- Scrollable Config Summary -->
      <div class="flex-1 overflow-y-auto p-4 space-y-2">
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
        <div class="space-y-2 rounded-lg bg-[var(--section-chips)]">
          <!-- Required Info -->
          <div class="p-3 rounded-lg border hover:bg-muted/50 cursor-pointer" @click="openSheet('inputs')">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs text-muted-foreground">Required Info</p>
              <Plus class="h-4 w-4 text-muted-foreground" />
            </div>
            <div v-if="selectedInputFields.length === 0" class="text-sm font-medium text-muted-foreground">
              None selected
            </div>
            <div v-else class="flex flex-wrap gap-1">
              <Badge v-for="label in inputFieldLabels" :key="label" variant="secondary" class="text-xs">
                {{ label }}
              </Badge>
            </div>
          </div>

          <!-- Conditions -->
          <div class="p-3 rounded-lg border hover:bg-muted/50 cursor-pointer" @click="openSheet('validation')">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs text-muted-foreground">Conditions</p>
              <Plus class="h-4 w-4 text-muted-foreground" />
            </div>
            <div v-if="validationBadges.length === 0" class="text-sm font-medium text-muted-foreground">
              None
            </div>
            <div v-else class="flex flex-wrap gap-1">
              <Badge 
                v-for="(badge, index) in validationBadges" 
                :key="index" 
                :variant="badge.variant || 'secondary'" 
                class="text-xs"
              >
                {{ badge.label }}: {{ badge.value }}
              </Badge>
            </div>
          </div>

          <!-- Feedback -->
          <div class="p-3 rounded-lg border hover:bg-muted/50 cursor-pointer" @click="openSheet('feedback')">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs text-muted-foreground">Feedback</p>
              <Plus class="h-4 w-4 text-muted-foreground" />
            </div>
            <div v-if="feedbackBadges.length === 0" class="text-sm font-medium text-muted-foreground">
              None
            </div>
            <div v-else class="flex flex-wrap gap-1">
              <Badge 
                v-for="(badge, index) in feedbackBadges" 
                :key="index" 
                :variant="badge.variant || 'secondary'" 
                class="text-xs"
              >
                {{ badge.label }}: {{ badge.value }}
              </Badge>
            </div>
          </div>
        </div>

        <!-- Amount Display (Large) - Context-Aware -->

        <!-- Redeemable: Single amount -->
        <div v-if="voucherType === 'redeemable'" class="text-center py-4 rounded-lg bg-[var(--section-amount)]">
          <p class="text-sm text-muted-foreground mb-1">Amount</p>
          <p 
            class="text-4xl font-bold tabular-nums cursor-pointer hover:text-primary transition-colors"
            @click="openAmountKeypad"
          >
            {{ amount ? `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '₱0.00' }}
          </p>
        </div>

        <!-- Payable: Target amount -->
        <div v-else-if="voucherType === 'payable'" class="text-center py-4 rounded-lg bg-[var(--section-amount)]">
          <p class="text-sm text-muted-foreground mb-1">Target Amount</p>
          <p 
            class="text-4xl font-bold tabular-nums cursor-pointer hover:text-primary transition-colors"
            @click="openTargetAmountKeypad"
          >
            {{ targetAmount ? `₱${targetAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '₱0.00' }}
          </p>
        </div>

        <!-- Settlement: Principal -->
        <div v-else class="text-center py-4 rounded-lg bg-[var(--section-amount)]">
          <div class="flex items-center justify-center gap-3">
            <div class="cursor-pointer hover:text-primary transition-colors" @click="openAmountKeypad">
              <p class="text-xs text-muted-foreground mb-1">Principal</p>
              <p class="text-2xl font-bold tabular-nums">
                {{ amount ? `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '₱0.00' }}
              </p>
            </div>
            <span class="text-muted-foreground/40 text-lg select-none mt-4">→</span>
            <div class="cursor-pointer hover:text-primary transition-colors" @click="openTargetAmountKeypad">
              <p class="text-xs text-muted-foreground mb-1">Target</p>
              <p class="text-2xl font-bold tabular-nums">
                {{ targetAmount ? `₱${targetAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '₱0.00' }}
              </p>
            </div>
          </div>
          <p 
            class="text-xs text-muted-foreground mt-2 cursor-pointer hover:text-primary transition-colors underline decoration-dotted"
            @click="openInterestRateKeypad"
          >
            {{ Number(interestRate || 0).toFixed(2) }}% interest
          </p>
        </div>


        <!-- Quick Amount Grid -->
        <div class="grid grid-cols-3 gap-2 rounded-lg p-2 bg-[var(--section-quickgrid)]">
          <template v-if="voucherType === 'payable'">
            <Button variant="outline" size="sm" @click="targetAmount = 100">₱100</Button>
            <Button variant="outline" size="sm" @click="targetAmount = 500">₱500</Button>
            <Button variant="outline" size="sm" @click="targetAmount = 1000">₱1K</Button>
            <Button variant="outline" size="sm" @click="targetAmount = 2000">₱2K</Button>
            <Button variant="outline" size="sm" @click="targetAmount = 5000">₱5K</Button>
            <Button variant="outline" size="sm" @click="targetAmount = 10000">₱10K</Button>
          </template>
          <template v-else>
            <Button variant="outline" size="sm" @click="amount = 100">₱100</Button>
            <Button variant="outline" size="sm" @click="amount = 500">₱500</Button>
            <Button variant="outline" size="sm" @click="amount = 1000">₱1K</Button>
            <Button variant="outline" size="sm" @click="amount = 2000">₱2K</Button>
            <Button variant="outline" size="sm" @click="amount = 5000">₱5K</Button>
            <Button variant="outline" size="sm" @click="amount = 10000">₱10K</Button>
          </template>
        </div>

      </div>

      <!-- Fixed Bottom: Balance + Generate -->
      <div class="px-4 py-2 space-y-1.5 bg-[var(--section-generate)]">
        <div class="flex items-center justify-between text-sm rounded-lg px-2 py-1 bg-[var(--section-balance)]">
          <div class="flex items-center gap-2 text-muted-foreground">
            <Wallet class="h-4 w-4" />
            <span>{{ formattedBalance }}</span>
          </div>
          <div 
            v-if="amount" 
            class="text-muted-foreground cursor-pointer hover:text-primary transition-colors underline decoration-dotted"
            @click="showCostModal"
          >
            Cost: ₱{{ estimatedCost.toFixed(2) }}
          </div>
        </div>
        <Button
          class="w-full"
          :disabled="!canGenerate"
          @click="handleGenerate"
        >
          <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
          {{ generateButtonText }}
        </Button>
      </div>
    </div>

    <!-- ========================================================================
         COST BREAKDOWN MODAL
         ======================================================================== -->
    <Dialog v-model:open="showCostBreakdownModal">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle>Cost Breakdown</DialogTitle>
          <DialogDescription>
            Detailed charges for your voucher generation
          </DialogDescription>
        </DialogHeader>
        
        <div class="space-y-4">
          <!-- Loading state -->
          <div v-if="pricingLoading" class="text-sm text-muted-foreground text-center py-4">
            Calculating charges...
          </div>
          
          <!-- Breakdown -->
          <div v-else class="space-y-3">
            <!-- Base voucher amount -->
            <div class="flex justify-between pb-2 border-b text-sm">
              <span class="text-muted-foreground">Voucher Amount × {{ count }}</span>
              <span class="font-medium">₱{{ (amount * count).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
            </div>
            
            <!-- Charges by category -->
            <div v-if="Object.keys(chargesByCategory).length === 0" class="text-sm text-muted-foreground text-center py-2">
              No additional charges
            </div>
            <div
              v-for="(items, category) in chargesByCategory"
              :key="category"
              class="space-y-1"
            >
              <!-- Category header -->
              <div class="text-xs font-semibold text-foreground/70 uppercase tracking-wide pt-1">
                {{ categoryLabels[category] || category }}
              </div>
              
              <!-- Items in this category -->
              <div
                v-for="item in items"
                :key="item.index"
                class="flex justify-between pl-2 text-sm"
              >
                <span class="text-muted-foreground">{{ item.label }}</span>
                <span class="font-medium">{{ item.price_formatted }}</span>
              </div>
            </div>
            
            <!-- Total -->
            <div class="flex justify-between pt-2 border-t text-base font-semibold">
              <span>Total Deduction</span>
              <span>₱{{ estimatedCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
            </div>
            
            <!-- Balance after -->
            <div class="flex justify-between text-sm">
              <span class="text-muted-foreground">Balance After Generation</span>
              <span
                :class="
                  estimatedCost > walletBalance
                    ? 'text-destructive font-medium'
                    : 'text-green-600 dark:text-green-400 font-medium'
                "
              >
                ₱{{ (walletBalance - estimatedCost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
              </span>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
    
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
          <!-- Campaign Template -->
          <button
            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-muted/50 transition-colors text-left"
            @click="openSheet('campaign')"
          >
            <div class="flex-1">
              <div class="font-medium text-sm">Campaign Template</div>
              <div class="text-xs text-muted-foreground mt-0.5">{{ campaignDisplay }}</div>
            </div>
            <Badge v-if="selectedCampaign" variant="secondary" class="ml-2">Selected</Badge>
          </button>
          
          <!-- Code & Expiry -->
          <button
            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-muted/50 transition-colors text-left"
            @click="openSheet('codeExpiry')"
          >
            <div class="flex-1">
              <div class="font-medium text-sm">Code & Expiry</div>
              <div class="text-xs text-muted-foreground mt-0.5">
                {{ codeExpirySummary }}
              </div>
            </div>
            <Badge v-if="hasCodeExpiry" variant="secondary" class="ml-2">Active</Badge>
          </button>
          
          <!-- Rider Config -->
          <button
            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-muted/50 transition-colors text-left"
            @click="openSheet('rider')"
          >
            <div class="flex-1">
              <div class="font-medium text-sm">Rider Config</div>
              <div class="text-xs text-muted-foreground mt-0.5">
                {{ riderConfigSummary }}
              </div>
            </div>
            <Badge v-if="riderMessage || riderUrl || riderSplash" variant="secondary" class="ml-2">Active</Badge>
          </button>
          
          <!-- Settlement Envelope -->
          <button
            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-muted/50 transition-colors text-left"
            @click="openSheet('envelope')"
          >
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm">Settlement Envelope</div>
              <div class="text-xs text-muted-foreground mt-0.5 truncate">
                {{ envelopeConfigSummary }}
              </div>
            </div>
            <Badge v-if="envelopeConfig" variant="secondary" class="ml-2 flex-shrink-0">Active</Badge>
          </button>
          
          <!-- Rail & Fees -->
          <button
            class="w-full flex items-center justify-between p-3 rounded-lg hover:bg-muted/50 transition-colors text-left"
            @click="openSheet('railFees')"
          >
            <div class="flex-1">
              <div class="font-medium text-sm">Rail & Fees</div>
              <div class="text-xs text-muted-foreground mt-0.5">
                {{ settlementRail || 'Auto' }} • {{ feeStrategy === 'absorb' ? 'Absorb fees' : feeStrategy === 'include' ? 'Include in amount' : 'Add to amount' }}
              </div>
            </div>
            <Badge variant="outline" class="ml-2">{{ settlementRail || 'Auto' }}</Badge>
          </button>
        </div>
        
        <!-- Action Buttons -->
        <div class="py-4 px-1 border-t">
          <Button 
            variant="outline" 
            class="w-full" 
            @click="openSaveCampaignDialog"
          >
            <Save class="mr-2 h-4 w-4" />
            Save as Campaign
          </Button>
        </div>
        
        <SheetFooter>
          <Button variant="outline" class="w-full" @click="sheetState.options.open = false">
            Close
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Save Campaign Dialog -->
    <Dialog v-model:open="showSaveCampaignDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Save as Campaign</DialogTitle>
          <DialogDescription>
            Save your current configuration as a reusable campaign template
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="campaign-name">Campaign Name</Label>
            <Input
              id="campaign-name"
              v-model="saveCampaignName"
              placeholder="Enter campaign name"
              @keyup.enter="saveAsCampaign"
            />
          </div>
          <div class="text-sm text-muted-foreground">
            This will save your current inputs, validation, feedback, rider, and other settings.
          </div>
        </div>
        <DialogFooter>
          <Button 
            variant="outline" 
            @click="showSaveCampaignDialog = false"
            :disabled="saveCampaignLoading"
          >
            Cancel
          </Button>
          <Button 
            @click="saveAsCampaign"
            :disabled="saveCampaignLoading"
          >
            <Loader2 v-if="saveCampaignLoading" class="mr-2 h-4 w-4 animate-spin" />
            Save Campaign
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Campaign Selection Sheet (Phase 3) -->
    <Sheet v-model:open="sheetState.campaign.open">
      <SheetContent side="bottom" class="h-[80dvh] flex flex-col">
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

    <!-- Required Info Sheet -->
    <Sheet v-model:open="sheetState.inputs.open">
      <SheetContent side="bottom" class="h-[80dvh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Required Info</SheetTitle>
          <div class="flex items-baseline justify-between">
            <SheetDescription>
              What should the redeemer provide?
            </SheetDescription>
            <button 
              v-if="selectedInputFields.length > 0" 
              class="text-xs text-muted-foreground hover:text-destructive transition-colors shrink-0"
              @click="clearAllInputFields"
            >Clear</button>
          </div>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-4 px-1 space-y-5">
          <div v-for="category in categorizedFields" :key="category.label" class="space-y-2">
            <p class="text-[11px] font-medium text-muted-foreground/60 uppercase tracking-widest px-1">{{ category.label }}</p>
            <div class="space-y-1.5">
              <button
                v-for="option in category.options"
                :key="option.value"
                :class="[
                  'w-full text-left px-3.5 py-2.5 rounded-xl border transition-all duration-150',
                  selectedInputFields.includes(option.value)
                    ? 'bg-primary/8 border-primary/30 text-foreground'
                    : 'border-border text-muted-foreground hover:bg-muted/40',
                  option.value === 'otp' && autoAddedFields.has('otp') && 'opacity-50 cursor-default'
                ]"
                @click="option.value === 'otp' && autoAddedFields.has('otp') ? null : toggleInputField(option.value)"
              >
                <span class="text-sm font-medium">{{ option.label }}</span>
                <span 
                  v-if="option.value === 'otp' && autoAddedFields.has('otp')" 
                  class="text-[10px] text-muted-foreground ml-2"
                >Required</span>
              </button>
            </div>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button @click="sheetState.inputs.open = false" class="w-full">
            Done
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Conditions Sheet -->
    <Sheet v-model:open="sheetState.validation.open">
      <SheetContent side="bottom" class="h-[85dvh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Conditions</SheetTitle>
          <SheetDescription>
            Control who, what, where, and when
          </SheetDescription>
        </SheetHeader>
        
        <Tabs :default-value="sheetState.validation.activeTab" @update:model-value="(val: any) => sheetState.validation.activeTab = val" class="flex-1 flex flex-col min-h-0 mt-4">
          <TabsList class="grid w-full grid-cols-4">
            <TabsTrigger value="payee">Who</TabsTrigger>
            <TabsTrigger value="secret">What</TabsTrigger>
            <TabsTrigger value="location">Where</TabsTrigger>
            <TabsTrigger value="time">When</TabsTrigger>
          </TabsList>
          
          <!-- Who Tab -->
          <TabsContent value="payee" class="flex-1 overflow-y-auto mt-4 px-3 space-y-4">
            <div class="space-y-3">
              <!-- Mode cards -->
              <button
                class="w-full text-left p-4 rounded-xl border transition-all duration-150"
                :class="payeeMode === 'anyone' ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                @click="setPayeeMode('anyone')"
              >
                <p class="text-sm font-medium">Anyone</p>
                <p class="text-xs text-muted-foreground">No restriction — any redeemer</p>
              </button>
              <button
                class="w-full text-left p-4 rounded-xl border transition-all duration-150"
                :class="payeeMode === 'mobile' ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                @click="setPayeeMode('mobile')"
              >
                <p class="text-sm font-medium">Mobile number</p>
                <p class="text-xs text-muted-foreground">Only this phone can redeem</p>
              </button>
              <button
                class="w-full text-left p-4 rounded-xl border transition-all duration-150"
                :class="payeeMode === 'vendor' ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                @click="setPayeeMode('vendor')"
              >
                <p class="text-sm font-medium">Vendor</p>
                <p class="text-xs text-muted-foreground">Restrict to a vendor alias</p>
              </button>

              <!-- Inline input when mobile/vendor selected -->
              <div v-if="payeeMode === 'mobile'" class="pt-1">
                <PhoneInput ref="payeeInputRef" v-model="payee" :error="payeeError" />
              </div>
              <div v-else-if="payeeMode === 'vendor'" class="pt-1 space-y-1">
                <Input ref="vendorInputRef" v-model="payee" type="text" class="text-base" :class="{ 'border-red-500': payeeError }" />
                <p v-if="payeeError" class="text-xs text-red-600">{{ payeeError }}</p>
              </div>

              <!-- Clear -->
              <button
                v-if="payee"
                class="text-xs text-muted-foreground hover:text-destructive transition-colors"
                @click="payee = ''; payeeMode = 'anyone'"
              >Clear</button>
            </div>
          </TabsContent>

          <!-- What Tab -->
          <TabsContent value="secret" class="flex-1 overflow-y-auto mt-4 px-3 space-y-5">
            <!-- Prominent input -->
            <div class="shrink-0">
              <Input
                ref="secretInputRef"
                v-model="validationSecret"
                type="text"
                placeholder="Enter a secret code"
              />
            </div>
            <button
              v-if="validationSecret"
              class="text-xs text-muted-foreground hover:text-destructive transition-colors"
              @click="validationSecret = ''"
            >Clear</button>

            <!-- Quick suggestions -->
            <div class="space-y-4 pt-2">
              <p class="text-[11px] font-medium text-muted-foreground/50 uppercase tracking-widest">Or pick one</p>
              <div v-for="cat in secretPresets" :key="cat.label" class="space-y-2">
                <p class="text-xs text-muted-foreground/50">{{ cat.label }}</p>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="word in cat.words"
                    :key="word"
                    class="px-3 py-1.5 rounded-full text-sm border transition-all duration-150"
                    :class="validationSecret === word ? 'bg-primary/8 border-primary/30 font-medium text-foreground' : 'text-muted-foreground border-muted hover:bg-muted/30'"
                    @click="pickSecret(word)"
                  >{{ word }}</button>
                </div>
              </div>
            </div>
          </TabsContent>

          <!-- Where Tab -->
          <TabsContent value="location" class="flex-1 overflow-y-auto mt-4 px-3 space-y-5">
            <!-- Preset places -->
            <div v-if="locationPresets.length" class="space-y-2">
              <p class="text-xs text-muted-foreground">Presets</p>
              <div class="space-y-2">
                <button
                  v-for="place in locationPresets"
                  :key="place.name"
                  class="w-full text-left p-3 rounded-xl border transition-all duration-150"
                  :class="locationValidation?.latitude === place.latitude && locationValidation?.longitude === place.longitude ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                  @click="applyPlace(place)"
                >
                  <p class="text-sm font-medium">{{ place.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ place.latitude.toFixed(4) }}, {{ place.longitude.toFixed(4) }} · {{ place.radius }}m</p>
                </button>
              </div>
            </div>

            <!-- Saved places -->
            <div v-if="savedPlaces.length" class="space-y-2">
              <p class="text-xs text-muted-foreground">Saved</p>
              <div class="space-y-2">
                <div
                  v-for="(place, idx) in savedPlaces"
                  :key="idx"
                  class="flex items-center gap-2"
                >
                  <button
                    class="flex-1 text-left p-3 rounded-xl border transition-all duration-150"
                    :class="locationValidation?.latitude === place.latitude && locationValidation?.longitude === place.longitude ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                    @click="applyPlace(place)"
                  >
                    <p class="text-sm font-medium">{{ place.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ place.latitude.toFixed(4) }}, {{ place.longitude.toFixed(4) }} · {{ place.radius }}m</p>
                  </button>
                  <button class="p-1.5 text-muted-foreground/60 hover:text-destructive transition-colors" @click="deletePlace(idx)">×</button>
                </div>
              </div>
            </div>

            <!-- Custom coordinates -->
            <div class="space-y-3">
              <p class="text-xs text-muted-foreground">Custom</p>
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                  <Label class="text-xs">Lat</Label>
                  <Input v-model.number="locationValidation.latitude" type="number" step="0.0001" class="text-sm" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">Lng</Label>
                  <Input v-model.number="locationValidation.longitude" type="number" step="0.0001" class="text-sm" />
                </div>
              </div>
              <div class="space-y-1">
                <Label class="text-xs">Radius (m)</Label>
                <Input v-model.number="locationValidation.radius" type="number" min="1" class="text-sm" />
              </div>

              <!-- Save / Clear -->
              <div v-if="locationValidation?.latitude" class="flex items-center gap-2">
                <template v-if="!showSavePlaceInput">
                  <button class="text-xs text-primary hover:underline" @click="showSavePlaceInput = true">Save this place</button>
                  <span class="text-xs text-muted-foreground/40">·</span>
                  <button class="text-xs text-muted-foreground hover:text-destructive transition-colors" @click="locationValidation = null">Clear</button>
                </template>
                <template v-else>
                  <Input v-model="savePlaceName" class="text-sm flex-1" @keyup.enter="savePlace" />
                  <Button size="sm" variant="ghost" @click="savePlace">Save</Button>
                  <button class="text-xs text-muted-foreground" @click="showSavePlaceInput = false">Cancel</button>
                </template>
              </div>
            </div>
          </TabsContent>

          <!-- When Tab -->
          <TabsContent value="time" class="flex-1 overflow-y-auto mt-4 px-3 space-y-5">
            <!-- Preset windows -->
            <div v-if="timePresets.length" class="space-y-2">
              <p class="text-xs text-muted-foreground">Presets</p>
              <div class="space-y-2">
                <button
                  v-for="tw in timePresets"
                  :key="tw.name"
                  class="w-full text-left p-3 rounded-xl border transition-all duration-150"
                  :class="timeValidation?.start_time === tw.start_time && timeValidation?.end_time === tw.end_time ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                  @click="applyTimeWindow(tw)"
                >
                  <p class="text-sm font-medium">{{ tw.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ tw.start_time }} – {{ tw.end_time }}</p>
                </button>
              </div>
            </div>

            <!-- Saved windows -->
            <div v-if="savedTimes.length" class="space-y-2">
              <p class="text-xs text-muted-foreground">Saved</p>
              <div class="space-y-2">
                <div
                  v-for="(tw, idx) in savedTimes"
                  :key="idx"
                  class="flex items-center gap-2"
                >
                  <button
                    class="flex-1 text-left p-3 rounded-xl border transition-all duration-150"
                    :class="timeValidation?.start_time === tw.start_time && timeValidation?.end_time === tw.end_time ? 'bg-primary/8 border-primary/30' : 'hover:bg-muted/50'"
                    @click="applyTimeWindow(tw)"
                  >
                    <p class="text-sm font-medium">{{ tw.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ tw.start_time }} – {{ tw.end_time }}</p>
                  </button>
                  <button class="p-1.5 text-muted-foreground/60 hover:text-destructive transition-colors" @click="deleteTimeWindow(idx)">×</button>
                </div>
              </div>
            </div>

            <!-- Custom time inputs -->
            <div class="space-y-3">
              <p class="text-xs text-muted-foreground">Custom</p>
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                  <Label class="text-xs">Start</Label>
                  <Input v-model="timeValidation.start_time" type="time" class="text-sm" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">End</Label>
                  <Input v-model="timeValidation.end_time" type="time" class="text-sm" />
                </div>
              </div>

              <!-- Save / Clear -->
              <div v-if="timeValidation?.start_time" class="flex items-center gap-2">
                <template v-if="!showSaveTimeInput">
                  <button class="text-xs text-primary hover:underline" @click="showSaveTimeInput = true">Save this window</button>
                  <span class="text-xs text-muted-foreground/40">·</span>
                  <button class="text-xs text-muted-foreground hover:text-destructive transition-colors" @click="timeValidation = null">Clear</button>
                </template>
                <template v-else>
                  <Input v-model="saveTimeName" class="text-sm flex-1" @keyup.enter="saveTimeWindow" />
                  <Button size="sm" variant="ghost" @click="saveTimeWindow">Save</Button>
                  <button class="text-xs text-muted-foreground" @click="showSaveTimeInput = false">Cancel</button>
                </template>
              </div>
            </div>
          </TabsContent>
        </Tabs>
        
        <SheetFooter class="mt-4">
          <Button @click="closeConditions" class="w-full">
            Done
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Feedback Sheet -->
    <Sheet v-model:open="sheetState.feedback.open">
      <SheetContent side="bottom" class="h-auto max-h-[80dvh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Feedback</SheetTitle>
          <SheetDescription>
            Get notified when vouchers are redeemed
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-4 px-3 space-y-5">
          <!-- Email -->
          <div class="space-y-1.5">
            <div class="flex items-baseline justify-between">
              <Label for="feedback-email" class="text-sm">Email</Label>
              <button 
                v-if="!feedbackEmail && (page.props as any).auth?.user?.email" 
                class="text-xs text-primary/60 hover:text-primary transition-colors"
                @click="feedbackEmail = (page.props as any).auth.user.email"
              >Use mine</button>
            </div>
            <Input
              id="feedback-email"
              v-model="feedbackEmail"
              type="email"
              :class="{ 'border-red-500': feedbackEmailError }"
            />
            <p v-if="feedbackEmailError" class="text-xs text-red-600">{{ feedbackEmailError }}</p>
          </div>
          
          <!-- SMS -->
          <div class="space-y-1.5">
            <div class="flex items-baseline justify-between">
              <Label for="feedback-mobile" class="text-sm">SMS</Label>
              <button 
                v-if="!feedbackMobile && (page.props as any).auth?.user?.mobile" 
                class="text-xs text-primary/60 hover:text-primary transition-colors"
                @click="feedbackMobile = (page.props as any).auth.user.mobile"
              >Use mine</button>
            </div>
            <PhoneInput
              id="feedback-mobile"
              v-model="feedbackMobile"
              :error="feedbackMobileError"
            />
          </div>
          
          <!-- Webhook -->
          <div class="space-y-1.5">
            <div class="flex items-baseline justify-between">
              <Label for="feedback-webhook" class="text-sm">Webhook</Label>
              <button 
                v-if="!feedbackWebhook && (page.props as any).auth?.user?.webhook" 
                class="text-xs text-primary/60 hover:text-primary transition-colors"
                @click="feedbackWebhook = (page.props as any).auth.user.webhook"
              >Use mine</button>
            </div>
            <Input
              id="feedback-webhook"
              v-model="feedbackWebhook"
              type="url"
              :class="{ 'border-red-500': feedbackWebhookError }"
            />
            <p v-if="feedbackWebhookError" class="text-xs text-red-600">{{ feedbackWebhookError }}</p>
          </div>
        </div>
        
        <SheetFooter class="mt-4">
          <Button @click="sheetState.feedback.open = false" class="w-full" :disabled="hasFeedbackErrors">
            Done
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Code & Expiry Sheet -->
    <Sheet v-model:open="sheetState.codeExpiry.open">
      <SheetContent side="bottom" class="h-auto max-h-[80vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Code & Expiry</SheetTitle>
          <SheetDescription>
            Customize voucher code format and expiration
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-4 px-1 space-y-5">
          <!-- Prefix -->
          <div class="space-y-2">
            <Label for="code-prefix">Code Prefix</Label>
            <Input
              id="code-prefix"
              v-model="prefix"
              placeholder="e.g. PROMO"
              maxlength="10"
            />
            <p class="text-xs text-muted-foreground">
              Prepended to generated codes (1-10 characters)
            </p>
          </div>
          
          <!-- Mask -->
          <div class="space-y-2">
            <Label for="code-mask">Code Mask</Label>
            <Input
              id="code-mask"
              v-model="mask"
              class="font-mono"
              :class="{ 'border-red-500': maskError }"
            />
            <p v-if="maskError" class="text-xs text-red-600">{{ maskError }}</p>
            <p class="text-xs text-muted-foreground">
              Use * for random characters, - as separator. Needs 4-8 asterisks.
            </p>
          </div>
          
          <!-- TTL -->
          <div class="space-y-3">
            <Label for="code-ttl">Expires After</Label>
            <Input
              id="code-ttl"
              v-model.number="ttlDays"
              type="number"
              placeholder="No expiry"
              min="1"
            />
            <p class="text-xs text-muted-foreground">
              Days until voucher expires (leave empty for no expiry)
            </p>
            <div class="flex flex-wrap gap-2">
              <Button v-for="d in [7, 14, 30, 60, 90]" :key="d" variant="outline" size="sm"
                :class="ttlDays === d && 'border-primary bg-primary/5'"
                @click="ttlDays = ttlDays === d ? null : d"
              >{{ d }}d</Button>
            </div>
          </div>
          
          <!-- Preview -->
          <div v-if="hasCodeExpiry" class="p-3 bg-muted/50 rounded-lg space-y-1 text-xs text-muted-foreground">
            <p class="font-medium text-foreground text-sm mb-1">Preview</p>
            <p v-if="prefix">Prefix: <span class="font-mono">{{ prefix }}-</span></p>
            <p v-if="mask">Pattern: <span class="font-mono">{{ prefix ? prefix + '-' : '' }}{{ mask }}</span></p>
            <p v-if="ttlDays">Expires: {{ ttlDays }} days after generation</p>
          </div>
          
          <!-- Clear -->
          <Button
            v-if="hasCodeExpiry"
            variant="outline"
            class="w-full"
            @click="prefix = ''; mask = ''; ttlDays = null;"
          >
            Clear All
          </Button>
        </div>
        
        <SheetFooter class="mt-4">
          <Button @click="sheetState.codeExpiry.open = false" class="w-full">
            Done
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
    
    <!-- Rider Sheet (Phase 8 - Advanced) -->
    <Sheet v-model:open="sheetState.rider.open">
      <SheetContent side="bottom" class="h-[85dvh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Rider Configuration</SheetTitle>
          <SheetDescription>
            Customize the redemption experience
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 px-1 space-y-5">
          <!-- Customer Message -->
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Label for="rider-message" class="flex-1">Customer Message</Label>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase transition-all duration-150"
                :class="ogMetaSource === 'message'
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'bg-muted text-muted-foreground/60 hover:text-muted-foreground hover:bg-muted/80'"
                @click="toggleOgSource('message')"
              >OG</button>
            </div>
            <Textarea
              id="rider-message"
              v-model="riderMessage"
              rows="3"
            />
            <p class="text-xs text-muted-foreground">
              Shown to the redeemer after redemption
            </p>
          </div>
          
          <!-- Custom URL -->
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Label for="rider-url" class="flex-1">Custom URL</Label>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase transition-all duration-150"
                :class="ogMetaSource === 'url'
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'bg-muted text-muted-foreground/60 hover:text-muted-foreground hover:bg-muted/80'"
                @click="toggleOgSource('url')"
              >OG</button>
            </div>
            <Input
              id="rider-url"
              v-model="riderUrl"
              type="url"
            />
            <p class="text-xs text-muted-foreground">
              Redirect destination after redemption
            </p>
          </div>
          
          <!-- Redirect Timeout -->
          <div class="space-y-2">
            <Label for="rider-redirect-timeout">Redirect Delay</Label>
            <div class="flex items-center gap-2">
              <Input
                id="rider-redirect-timeout"
                v-model.number="riderRedirectTimeout"
                type="number"
                min="0"
                max="60"
                class="flex-1"
              />
              <span class="text-xs text-muted-foreground whitespace-nowrap">seconds</span>
            </div>
            <p class="text-xs text-muted-foreground">
              Wait before redirecting (0 = immediate)
            </p>
          </div>
          
          <!-- Splash Text -->
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Label for="rider-splash" class="flex-1">Splash Text</Label>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium tracking-wide uppercase transition-all duration-150"
                :class="ogMetaSource === 'splash'
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'bg-muted text-muted-foreground/60 hover:text-muted-foreground hover:bg-muted/80'"
                @click="toggleOgSource('splash')"
              >OG</button>
            </div>
            <Textarea
              id="rider-splash"
              v-model="riderSplash"
              rows="2"
            />
            <p class="text-xs text-muted-foreground">
              Content shown during redirect countdown (supports HTML, Markdown, SVG)
            </p>
          </div>
          
          <!-- Splash Duration -->
          <div class="space-y-2">
            <Label for="rider-splash-timeout">Splash Duration</Label>
            <div class="flex items-center gap-2">
              <Input
                id="rider-splash-timeout"
                v-model.number="riderSplashTimeout"
                type="number"
                min="0"
                max="30"
                class="flex-1"
              />
              <span class="text-xs text-muted-foreground whitespace-nowrap">seconds</span>
            </div>
            <p class="text-xs text-muted-foreground">
              How long splash is visible before redirect
            </p>
          </div>
          
          <!-- OG Meta hint -->
          <div v-if="ogMetaSource" class="flex items-start gap-2 p-3 rounded-lg bg-primary/5 border border-primary/10">
            <span class="text-xs leading-relaxed text-primary/80">
              <span class="font-medium">OG Override:</span>
              The <span class="font-medium">{{ ogMetaSource }}</span> field will be used as the link preview title when this voucher is shared.
            </span>
          </div>
          
          <!-- Preview -->
          <div v-if="riderMessage || riderUrl || riderSplash" class="p-3 bg-muted/50 rounded-lg">
            <p class="text-sm font-medium mb-2">Preview</p>
            <div class="space-y-1.5 text-xs text-muted-foreground">
              <p v-if="riderMessage">Message: "{{ riderMessage }}"</p>
              <p v-if="riderUrl">Redirect: {{ riderUrl }}</p>
              <p v-if="riderRedirectTimeout !== null">Delay: {{ riderRedirectTimeout }}s</p>
              <p v-if="riderSplash">Splash: "{{ riderSplash.length > 60 ? riderSplash.substring(0, 60) + '…' : riderSplash }}"</p>
              <p v-if="riderSplashTimeout !== null">Splash duration: {{ riderSplashTimeout }}s</p>
              <p v-if="ogMetaSource" class="text-primary/70 font-medium">OG source: {{ ogMetaSource }}</p>
            </div>
          </div>
          
          <!-- Clear All -->
          <Button
            variant="outline"
            class="w-full"
            @click="clearRider"
            v-if="riderMessage || riderUrl || riderSplash || ogMetaSource"
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
      <SheetContent side="bottom" class="h-auto max-h-[75vh] flex flex-col">
        <SheetHeader>
          <SheetTitle>Settlement Envelope</SheetTitle>
          <SheetDescription>
            Attach an evidence envelope for tracking and documentation
          </SheetDescription>
        </SheetHeader>
        
        <div class="flex-1 overflow-y-auto mt-6 px-1 space-y-4">
          <div v-if="voucherType === 'payable' || voucherType === 'settlement'">
            <!-- Toggle Card (matching desktop design) -->
            <div class="border rounded-lg">
              <!-- Header with toggle -->
              <div class="flex items-center justify-between p-4 hover:bg-muted/50 transition-colors">
                <div class="flex items-center gap-3 flex-1">
                  <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <span class="text-xl">📋</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-sm">Settlement Envelope</h3>
                    <p class="text-xs text-muted-foreground truncate">
                      {{ envelopeConfig ? 'Tracking enabled' : 'Attach an evidence envelope' }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Label for="envelope-enabled" class="text-xs text-muted-foreground">
                    {{ envelopeConfig ? 'Enabled' : 'Disabled' }}
                  </Label>
                  <Switch
                    id="envelope-enabled"
                    :checked="!!envelopeConfig"
                    @update:checked="(checked) => envelopeConfig = checked ? {} : null"
                  />
                </div>
              </div>
              
              <!-- Expanded content when enabled -->
              <div v-if="envelopeConfig" class="px-4 pb-4 space-y-4 border-t pt-4">
                <!-- Driver Selection -->
                <div class="space-y-2">
                  <Label for="envelope-driver">Envelope Driver</Label>
                  <Select v-model="selectedDriverKey">
                    <SelectTrigger id="envelope-driver">
                      <SelectValue placeholder="Select driver..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="driver in envelopeDrivers"
                        :key="driver.key"
                        :value="driver.key"
                      >
                        {{ driver.title }} ({{ driver.version }})
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <p class="text-xs text-muted-foreground">
                    {{ envelopeDrivers.find(d => d.key === selectedDriverKey)?.description || 'Select a driver to track payments and documents' }}
                  </p>
                </div>
                
                <!-- Info Card -->
                <div v-if="selectedDriverKey" class="p-3 bg-primary/5 border border-primary/20 rounded-lg">
                  <div class="flex items-start gap-2">
                    <div class="mt-0.5 h-5 w-5 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                      <span class="text-white text-xs">✓</span>
                    </div>
                    <div class="flex-1">
                      <p class="text-sm font-medium">Envelope Tracking Enabled</p>
                      <p class="text-xs text-muted-foreground mt-1">
                        Using {{ envelopeDrivers.find(d => d.key === selectedDriverKey)?.title || 'selected driver' }}
                      </p>
                    </div>
                  </div>
                </div>
                
                <!-- Features list -->
                <div class="space-y-2">
                  <p class="text-xs font-medium text-muted-foreground">Features:</p>
                  <div class="space-y-1.5">
                    <div class="flex items-center gap-2 text-xs">
                      <div class="h-1.5 w-1.5 rounded-full bg-primary"></div>
                      <span class="text-muted-foreground">Automatic payment tracking</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                      <div class="h-1.5 w-1.5 rounded-full bg-primary"></div>
                      <span class="text-muted-foreground">Document upload support</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                      <div class="h-1.5 w-1.5 rounded-full bg-primary"></div>
                      <span class="text-muted-foreground">Settlement status monitoring</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                      <div class="h-1.5 w-1.5 rounded-full bg-primary"></div>
                      <span class="text-muted-foreground">Evidence checklist workflow</span>
                    </div>
                  </div>
                </div>
                
                <!-- Note -->
                <div class="text-xs text-muted-foreground p-3 bg-muted/50 rounded-lg">
                  <p class="font-medium mb-1">💡 Advanced Configuration</p>
                  <p>Envelopes track evidence, approvals, and documents before settlement. Configure custom drivers and workflows in the desktop version.</p>
                </div>
              </div>
              
              <!-- Disabled info -->
              <div v-else class="px-4 pb-4 pt-4 border-t">
                <p class="text-sm text-muted-foreground">
                  Enable to attach a settlement envelope to vouchers. Envelopes track evidence, approvals, and documents before settlement.
                </p>
              </div>
            </div>
          </div>
          
          <div v-else class="py-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-muted mb-4">
              <span class="text-2xl">📋</span>
            </div>
            <p class="text-sm font-medium">Settlement Envelopes Not Available</p>
            <p class="text-xs text-muted-foreground mt-2 max-w-xs mx-auto">
              Settlement envelopes are only available for payable and settlement vouchers
            </p>
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
        
        <div class="flex-1 overflow-y-auto mt-6 px-1 space-y-4">
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
    
    <!-- Numeric Keypads -->
    <NumericKeypad
      v-model:open="showAmountKeypad"
      :model-value="amount"
      mode="amount"
      :min="1"
      :allow-decimal="true"
      title="Enter Amount"
      @confirm="confirmAmount"
    />
    
    <NumericKeypad
      v-model:open="showCountKeypad"
      :model-value="count"
      mode="count"
      :min="1"
      :max="100"
      :allow-decimal="false"
      title="Number of Vouchers"
      @confirm="confirmCount"
    />
    
    <NumericKeypad
      v-model:open="showTargetAmountKeypad"
      :model-value="targetAmount"
      mode="amount"
      :min="1"
      :allow-decimal="true"
      title="Enter Target Amount"
      @confirm="confirmTargetAmount"
    />
    
    <NumericKeypad
      v-model:open="showInterestRateKeypad"
      :model-value="interestRate"
      mode="amount"
      :min="0"
      :max="100"
      :allow-decimal="true"
      :hide-currency="true"
      title="Interest Rate (%)"
      @confirm="confirmInterestRate"
    />
  </PwaLayout>
</template>
