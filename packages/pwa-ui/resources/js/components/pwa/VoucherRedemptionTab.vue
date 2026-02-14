<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { User, Phone, Mail, MapPin, Camera, FileSignature, CheckCircle } from 'lucide-vue-next';

interface Props {
  voucherData: any;
}

const props = defineProps<Props>();

// Format date
const formatDate = (dateStr: string | null | undefined) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Get contact data
const contact = props.voucherData?.full_data?.contact;
const inputs = props.voucherData?.full_data?.inputs || [];

// Find specific inputs
const locationInput = inputs.find((i: any) => i.key === 'location');
const selfieInput = inputs.find((i: any) => i.key === 'selfie');
const signatureInput = inputs.find((i: any) => i.key === 'signature');
const kycStatus = contact?.kyc_status;
</script>

<template>
  <div class="space-y-4">
    <div v-if="!voucherData.redeemed_at" class="text-center text-muted-foreground py-8">
      Voucher not yet redeemed
    </div>

    <template v-else>
      <!-- Contact Information -->
      <Card v-if="contact">
        <CardHeader>
          <CardTitle class="text-lg flex items-center gap-2">
            <User class="h-5 w-5" />
            Contact Information
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="contact.name">
            <div class="text-sm text-muted-foreground">Name</div>
            <div class="text-sm font-medium">{{ contact.name }}</div>
          </div>

          <div v-if="contact.mobile">
            <div class="text-sm text-muted-foreground flex items-center gap-1">
              <Phone class="h-3 w-3" />
              Mobile
            </div>
            <div class="text-sm font-medium">{{ contact.mobile }}</div>
          </div>

          <div v-if="contact.email">
            <div class="text-sm text-muted-foreground flex items-center gap-1">
              <Mail class="h-3 w-3" />
              Email
            </div>
            <div class="text-sm font-medium">{{ contact.email }}</div>
          </div>
        </CardContent>
      </Card>

      <!-- Redemption Time -->
      <Card>
        <CardHeader>
          <CardTitle class="text-lg">Redemption Details</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="text-sm text-muted-foreground">Redeemed At</div>
          <div class="text-sm font-medium">{{ formatDate(voucherData.redeemed_at) }}</div>
        </CardContent>
      </Card>

      <!-- Collected Inputs -->
      <Card v-if="inputs.length > 0">
        <CardHeader>
          <CardTitle class="text-lg">Collected Inputs</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <!-- Location -->
          <div v-if="locationInput" class="flex items-start gap-3 p-3 bg-muted rounded-lg">
            <MapPin class="h-5 w-5 text-muted-foreground mt-0.5" />
            <div class="flex-1">
              <div class="text-sm font-medium mb-1">Location</div>
              <div class="text-xs text-muted-foreground">
                {{ locationInput.value?.address || 'Location captured' }}
              </div>
            </div>
          </div>

          <!-- Selfie -->
          <div v-if="selfieInput" class="flex items-start gap-3 p-3 bg-muted rounded-lg">
            <Camera class="h-5 w-5 text-muted-foreground mt-0.5" />
            <div class="flex-1">
              <div class="text-sm font-medium mb-1">Selfie</div>
              <div class="text-xs text-muted-foreground">Photo captured</div>
            </div>
          </div>

          <!-- Signature -->
          <div v-if="signatureInput" class="flex items-start gap-3 p-3 bg-muted-rounded-lg">
            <FileSignature class="h-5 w-5 text-muted-foreground mt-0.5" />
            <div class="flex-1">
              <div class="text-sm font-medium mb-1">Signature</div>
              <div class="text-xs text-muted-foreground">Signature captured</div>
            </div>
          </div>

          <!-- KYC Status -->
          <div v-if="kycStatus" class="flex items-start gap-3 p-3 bg-muted rounded-lg">
            <CheckCircle class="h-5 w-5 text-muted-foreground mt-0.5" />
            <div class="flex-1">
              <div class="text-sm font-medium mb-1">Identity Verification</div>
              <Badge 
                :variant="kycStatus === 'approved' ? 'success' : 'secondary'"
                class="capitalize"
              >
                {{ kycStatus }}
              </Badge>
            </div>
          </div>

          <!-- Other inputs -->
          <div 
            v-for="input in inputs.filter((i: any) => !['location', 'selfie', 'signature'].includes(i.key))" 
            :key="input.key"
            class="p-3 bg-muted rounded-lg"
          >
            <div class="text-sm font-medium mb-1 capitalize">{{ input.key }}</div>
            <div class="text-xs text-muted-foreground">{{ input.value || 'N/A' }}</div>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
