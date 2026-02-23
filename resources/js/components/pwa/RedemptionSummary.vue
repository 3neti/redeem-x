<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle, User, Banknote, MapPin, Camera, PenTool } from 'lucide-vue-next';

interface RedemptionSummary {
  redeemed_at: string;
  contact: {
    mobile: string;
    name: string | null;
  } | null;
  disbursement: {
    amount: number | null;
    currency: string;
    bank_name: string | null;
    bank_code: string | null;
    account: string | null;
    settlement_rail: string | null;
    transaction_id: string | null;
    status: string | null;
    disbursed_at: string | null;
  } | null;
  inputs: Array<{ name: string; value: string }>;
}

interface Props {
  redemptionData: RedemptionSummary;
}

const props = defineProps<Props>();

// Helper to get input value by name
const getInput = (name: string): string | null => {
  const input = props.redemptionData.inputs.find(i => i.name === name);
  return input?.value ?? null;
};

// Check if inputs contain specific fields
const hasLocation = computed(() => getInput('latitude') && getInput('longitude'));
const hasSelfie = computed(() => !!getInput('selfie'));
const hasSignature = computed(() => !!getInput('signature'));
const hasBioFields = computed(() => 
  getInput('full_name') || getInput('birth_date') || getInput('address') || getInput('reference_code')
);

// Format amount
const formatAmount = (amount: number | null, currency = 'PHP') => {
  if (amount === null) return '';
  return new Intl.NumberFormat('en-PH', { 
    style: 'currency', 
    currency 
  }).format(amount);
};

// Format date/time
const formatDateTime = (dateString: string | null) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Format date only
const formatDate = (dateString: string | null) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
};

// Get payment method label
const getPaymentMethod = (rail: string | null) => {
  if (!rail) return '';
  const methods: Record<string, string> = {
    'INSTAPAY': 'InstaPay (Real-time)',
    'PESONET': 'PESONet (Next business day)',
  };
  return methods[rail] || rail;
};

// Get status badge color
const getStatusColor = (status: string | null) => {
  switch (status) {
    case 'success': return 'text-green-600';
    case 'pending': return 'text-yellow-600';
    case 'failed': return 'text-red-600';
    default: return 'text-muted-foreground';
  }
};
</script>

<template>
  <div class="space-y-6">
    <!-- Redeemer Contact Info -->
    <Card v-if="redemptionData.contact">
      <CardHeader class="pb-3">
        <div class="flex items-center gap-2">
          <User class="h-5 w-5 text-primary" />
          <CardTitle>Redeemer</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="redemptionData.contact.name" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Name</div>
          <div class="text-base">{{ redemptionData.contact.name }}</div>
        </div>
        <div v-if="redemptionData.contact.mobile" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Mobile</div>
          <div class="text-base font-mono">{{ redemptionData.contact.mobile }}</div>
        </div>
        <div class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Redeemed At</div>
          <div class="text-base">{{ formatDateTime(redemptionData.redeemed_at) }}</div>
        </div>
      </CardContent>
    </Card>

    <!-- Disbursement Details -->
    <Card v-if="redemptionData.disbursement">
      <CardHeader class="pb-3">
        <div class="flex items-center gap-2">
          <Banknote class="h-5 w-5 text-primary" />
          <CardTitle>Disbursement</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="redemptionData.disbursement.amount" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Amount</div>
          <div class="text-lg font-semibold">{{ formatAmount(redemptionData.disbursement.amount, redemptionData.disbursement.currency) }}</div>
        </div>
        <div v-if="redemptionData.disbursement.bank_name" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Bank/Wallet</div>
          <div class="text-base">{{ redemptionData.disbursement.bank_name }}</div>
        </div>
        <div v-if="redemptionData.disbursement.account" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Account</div>
          <div class="text-base font-mono">{{ redemptionData.disbursement.account }}</div>
        </div>
        <div v-if="redemptionData.disbursement.settlement_rail" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Settlement Rail</div>
          <div class="text-base">{{ getPaymentMethod(redemptionData.disbursement.settlement_rail) }}</div>
        </div>
        <div v-if="redemptionData.disbursement.transaction_id" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Transaction ID</div>
          <div class="text-base font-mono">{{ redemptionData.disbursement.transaction_id }}</div>
        </div>
        <div v-if="redemptionData.disbursement.status" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Status</div>
          <div class="text-base capitalize" :class="getStatusColor(redemptionData.disbursement.status)">
            {{ redemptionData.disbursement.status }}
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Personal Information (from inputs) -->
    <Card v-if="hasBioFields">
      <CardHeader class="pb-3">
        <div class="flex items-center gap-2">
          <User class="h-5 w-5 text-primary" />
          <CardTitle>Personal Information</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="getInput('full_name')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Full Name</div>
          <div class="text-base">{{ getInput('full_name') }}</div>
        </div>
        <div v-if="getInput('birth_date')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Date of Birth</div>
          <div class="text-base">{{ formatDate(getInput('birth_date')) }}</div>
        </div>
        <div v-if="getInput('address')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Address</div>
          <div class="text-base">{{ getInput('address') }}</div>
        </div>
        <div v-if="getInput('reference_code')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Reference Code</div>
          <div class="text-base font-mono">{{ getInput('reference_code') }}</div>
        </div>
      </CardContent>
    </Card>

    <!-- Location Verification (from inputs) -->
    <Card v-if="hasLocation">
      <CardHeader class="pb-3">
        <div class="flex items-center gap-2">
          <MapPin class="h-5 w-5 text-primary" />
          <CardTitle>Location</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="space-y-3">
        <div v-if="getInput('address')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Address</div>
          <div class="text-base">{{ getInput('address') }}</div>
        </div>
        <div class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Coordinates</div>
          <div class="text-base font-mono">{{ getInput('latitude') }}, {{ getInput('longitude') }}</div>
        </div>
        <div v-if="getInput('accuracy')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Accuracy</div>
          <div class="text-base">{{ getInput('accuracy') }}m</div>
        </div>
        <div v-if="getInput('map')" class="space-y-1">
          <div class="text-sm font-medium text-muted-foreground">Map</div>
          <img 
            :src="getInput('map')" 
            alt="Location Map" 
            class="w-full h-48 object-cover rounded-lg border"
          />
        </div>
      </CardContent>
    </Card>

    <!-- Identity Verification (from inputs) -->
    <Card v-if="hasSelfie || hasSignature">
      <CardHeader class="pb-3">
        <div class="flex items-center gap-2">
          <Camera class="h-5 w-5 text-primary" />
          <CardTitle>Identity Verification</CardTitle>
        </div>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="hasSelfie" class="space-y-2">
          <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-muted-foreground">Selfie</div>
            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
              <CheckCircle class="w-4 h-4" />
              <span class="text-sm">Captured</span>
            </div>
          </div>
          <img 
            :src="getInput('selfie')!" 
            alt="Selfie" 
            class="w-32 h-32 object-cover rounded-lg border"
          />
        </div>
        <div v-if="hasSignature" class="space-y-2">
          <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-muted-foreground">Signature</div>
            <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
              <CheckCircle class="w-4 h-4" />
              <span class="text-sm">Captured</span>
            </div>
          </div>
          <img 
            :src="getInput('signature')!" 
            alt="Signature" 
            class="w-full h-24 object-contain bg-muted rounded-lg border"
          />
        </div>
      </CardContent>
    </Card>
  </div>
</template>
