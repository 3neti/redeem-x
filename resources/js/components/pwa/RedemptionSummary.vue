<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle } from 'lucide-vue-next';

interface Props {
  collectedData: any[];
}

const props = defineProps<Props>();

// Helper to find step data by step name
const findStep = (stepName: string) => {
  return props.collectedData.find(step => step._step_name === stepName) || {};
};

// Parse collected data into structured format
const walletInfo = computed(() => findStep('wallet_info'));
const bioFields = computed(() => findStep('bio_fields'));
const locationCapture = computed(() => findStep('location_capture'));
const selfieCapture = computed(() => findStep('selfie_capture'));
const signatureCapture = computed(() => findStep('signature_capture'));

// Format phone number
const formatPhone = (phone: string) => {
  if (!phone) return '';
  // Already formatted from form flow
  return phone;
};

// Format amount
const formatAmount = (amount: string | number) => {
  const num = typeof amount === 'string' ? parseFloat(amount) : amount;
  return new Intl.NumberFormat('en-PH', { 
    style: 'currency', 
    currency: 'PHP' 
  }).format(num);
};

// Format date
const formatDate = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
};

// Get bank/wallet name from code
const getBankName = (code: string) => {
  const banks: Record<string, string> = {
    'GXCHPHM2XXX': 'GCash',
    'PYMAPHM2XXX': 'PayMaya',
    'BDOPHMMM': 'BDO',
    'BOPIPHMM': 'BPI',
    // Add more as needed
  };
  return banks[code] || code;
};

// Get payment method label
const getPaymentMethod = (rail: string) => {
  const methods: Record<string, string> = {
    'INSTAPAY': 'InstaPay (Real-time)',
    'PESONET': 'PESONet (Next business day)',
  };
  return methods[rail] || rail;
};
</script>

<template>
  <div class="space-y-6">
    <!-- Redemption Details -->
    <Card v-if="walletInfo">
      <CardHeader>
        <CardTitle>Redemption Details</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="walletInfo.mobile" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Mobile Number</div>
          <div class="text-base">{{ formatPhone(walletInfo.mobile) }}</div>
        </div>
        
        <div v-if="walletInfo.bank_code" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Bank/Wallet Provider</div>
          <div class="text-base">{{ getBankName(walletInfo.bank_code) }}</div>
        </div>
        
        <div v-if="walletInfo.account_number" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Account Number</div>
          <div class="text-base">{{ walletInfo.account_number }}</div>
        </div>
        
        <div v-if="walletInfo.amount" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Amount</div>
          <div class="text-base font-semibold">{{ formatAmount(walletInfo.amount) }}</div>
        </div>
        
        <div v-if="walletInfo.settlement_rail" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Payment Method</div>
          <div class="text-base">{{ getPaymentMethod(walletInfo.settlement_rail) }}</div>
        </div>
      </CardContent>
    </Card>

    <!-- Personal Information -->
    <Card v-if="bioFields && Object.keys(bioFields).length > 1">
      <CardHeader>
        <CardTitle>Personal Information</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="bioFields.full_name" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Full Name</div>
          <div class="text-base">{{ bioFields.full_name }}</div>
        </div>
        
        <div v-if="bioFields.birth_date" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Date of Birth</div>
          <div class="text-base">{{ formatDate(bioFields.birth_date) }}</div>
        </div>
        
        <div v-if="bioFields.address" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Address</div>
          <div class="text-base">{{ bioFields.address }}</div>
        </div>
        
        <div v-if="bioFields.reference_code" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Reference Code</div>
          <div class="text-base font-mono">{{ bioFields.reference_code }}</div>
        </div>
      </CardContent>
    </Card>

    <!-- Location Verification -->
    <Card v-if="locationCapture && (locationCapture.latitude || locationCapture.longitude)">
      <CardHeader>
        <CardTitle>Location Verification</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="locationCapture.formatted_address" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Address</div>
          <div class="text-base">{{ locationCapture.formatted_address }}</div>
        </div>
        
        <div v-if="locationCapture.latitude && locationCapture.longitude" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Coordinates</div>
          <div class="text-base font-mono">
            {{ locationCapture.latitude }}, {{ locationCapture.longitude }}
          </div>
        </div>
        
        <div v-if="locationCapture.accuracy" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Accuracy</div>
          <div class="text-base">{{ locationCapture.accuracy }}m</div>
        </div>
        
        <!-- Map preview if available -->
        <div v-if="locationCapture.map" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Map</div>
          <img 
            :src="locationCapture.map" 
            alt="Location Map" 
            class="w-full h-48 object-cover rounded-lg border"
          />
        </div>
      </CardContent>
    </Card>

    <!-- Identity Verification -->
    <Card v-if="selfieCapture || signatureCapture">
      <CardHeader>
        <CardTitle>Identity Verification</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="selfieCapture && selfieCapture.selfie" class="space-y-2">
          <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-muted-foreground">Selfie</div>
            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
              <CheckCircle class="w-4 h-4" />
              <span class="text-sm">Captured</span>
            </div>
          </div>
          <img 
            :src="selfieCapture.selfie" 
            alt="Selfie" 
            class="w-32 h-32 object-cover rounded-lg border"
          />
        </div>
        
        <div v-if="signatureCapture && signatureCapture.signature" class="space-y-2">
          <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-muted-foreground">Signature</div>
            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
              <CheckCircle class="w-4 h-4" />
              <span class="text-sm">Captured</span>
            </div>
          </div>
          <img 
            :src="signatureCapture.signature" 
            alt="Signature" 
            class="w-full h-24 object-contain bg-muted rounded-lg border"
          />
        </div>
      </CardContent>
    </Card>
  </div>
</template>
