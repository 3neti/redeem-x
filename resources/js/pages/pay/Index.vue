<script setup lang="ts">
import { ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import QrDisplay from '@/components/shared/QrDisplay.vue'
import RedeemWidget from '@/components/RedeemWidget.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { useToast } from '@/components/ui/toast/use-toast'
import { useQrShare } from '@/composables/useQrShare'
import { initializeTheme } from '@/composables/useTheme'
import { Download, Info, AlertCircle, ArrowLeft, QrCode } from 'lucide-vue-next'

interface Props {
    initial_code?: string | null;
}

defineProps<Props>()

initializeTheme()

const page = usePage()
const { toast } = useToast()
const { downloadQr } = useQrShare()
const voucherCode = ref('')
const amount = ref('')
const quote = ref<any>(null)
const loading = ref(false)
const error = ref('')
const showQrStep = ref(false)
const paymentQr = ref<any>(null)
const qrLoading = ref(false)
const markingDone = ref(false)
const paymentMarkedDone = ref(false)

// Handle quote loaded from PayWidget
function handleQuoteLoaded(quoteData: any) {
  quote.value = quoteData
  voucherCode.value = quoteData.voucher_code
  amount.value = quoteData.remaining.toString()
}

async function getQuote() {
  if (!voucherCode.value.trim()) {
    error.value = 'Please enter a voucher code'
    return
  }

  loading.value = true
  error.value = ''
  quote.value = null

  try {
    // Get CSRF token from Inertia page props
    const csrfToken = (page.props as any).csrf_token || 
                      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                      document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    
    const response = await fetch('/pay/quote', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ code: voucherCode.value }),
    })

    const data = await response.json()

    if (!response.ok) {
      error.value = data.error || 'Failed to validate voucher'
      return
    }

    quote.value = data
    amount.value = data.remaining.toString()
  } catch (err: any) {
    error.value = err.message || 'Network error'
  } finally {
    loading.value = false
  }
}

// Generate InstaPay QR code for payment
async function generatePaymentQR() {
  if (!amount.value || parseFloat(amount.value) <= 0) {
    error.value = 'Please enter a valid amount'
    return
  }

  const amountNum = parseFloat(amount.value)
  if (amountNum < quote.value.min_amount || amountNum > quote.value.max_amount) {
    error.value = `Amount must be between ${formatCurrency(quote.value.min_amount)} and ${formatCurrency(quote.value.max_amount)}`
    return
  }

  error.value = ''
  qrLoading.value = true
  paymentQr.value = null
  
  try {
    // Get CSRF token
    const csrfToken = (page.props as any).csrf_token || 
                      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    
    // Generate InstaPay QR via API
    const response = await fetch('/api/v1/pay/generate-qr', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ 
        voucher_code: quote.value.voucher_code,
        amount: amountNum 
      }),
    })

    const data = await response.json()

    if (!response.ok) {
      error.value = data.message || 'Failed to generate payment QR'
      return
    }

    paymentQr.value = data.data
    showQrStep.value = true
    
    toast({
      title: 'QR Code Generated',
      description: `Payment QR code ready for ${formatCurrency(amountNum)}`,
    })
  } catch (err: any) {
    error.value = err.message || 'Failed to generate QR code'
    toast({
      title: 'Generation Failed',
      description: err.message || 'Failed to generate QR code',
      variant: 'destructive',
    })
  } finally {
    qrLoading.value = false
  }
}

async function markPaymentDone() {
  if (!paymentQr.value?.payment_request_id) {
    error.value = 'No payment request found'
    return
  }

  markingDone.value = true
  error.value = ''

  try {
    const csrfToken = (page.props as any).csrf_token || 
                      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

    const response = await fetch('/api/v1/pay/mark-done', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ 
        payment_request_id: paymentQr.value.payment_request_id,
      }),
    })

    const data = await response.json()

    if (!response.ok) {
      error.value = data.message || 'Failed to mark payment as done'
      toast({
        title: 'Marking Failed',
        description: data.message || 'Failed to mark payment as done',
        variant: 'destructive',
      })
      return
    }

    paymentMarkedDone.value = true
    
    toast({
      title: 'Payment Confirmed',
      description: `Payment of ${formatCurrency(parseFloat(amount.value))} marked as complete`,
    })
  } catch (err: any) {
    error.value = err.message || 'Failed to mark payment'
    toast({
      title: 'Error',
      description: err.message || 'Failed to mark payment',
      variant: 'destructive',
    })
  } finally {
    markingDone.value = false
  }
}

function backToDetails() {
  showQrStep.value = false
  paymentQr.value = null
  error.value = ''
  paymentMarkedDone.value = false
}

function resetFlow() {
  quote.value = null
  amount.value = ''
  error.value = ''
  showQrStep.value = false
  paymentQr.value = null
  paymentMarkedDone.value = false
}

function formatCurrency(amount: number) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

function handleDownloadQr() {
  if (paymentQr.value?.qr_code) {
    const timestamp = new Date().getTime()
    const filename = `payment-qr-${quote.value.voucher_code}-${timestamp}.png`
    downloadQr(paymentQr.value.qr_code, filename)
  }
}
</script>

<template>
  <Head title="Pay Voucher" />

  <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6 md:p-10">
    <div class="w-full max-w-sm">
      <!-- Step 1: Enter Voucher Code (RedeemWidget with x-ray preview) -->
      <div v-if="!quote">
        <RedeemWidget
          route-prefix="pay"
          :initial-code="initial_code"
          @quote-loaded="handleQuoteLoaded"
        />
      </div>

      <!-- Step 2-3 -->
      <div v-else class="space-y-4">

        <!-- Step 2: Payment Action -->
        <div v-if="!showQrStep" class="space-y-4">
          <!-- Financial Summary -->
          <Card>
            <CardHeader class="pb-2">
              <CardTitle class="text-base">Payment Summary</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-sm text-muted-foreground">Target</span>
                <span class="text-sm font-medium">{{ formatCurrency(quote.target_amount) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-muted-foreground">Paid</span>
                <span class="text-sm font-medium text-emerald-600">{{ formatCurrency(quote.paid_total) }}</span>
              </div>
              <div class="border-t pt-3 flex justify-between items-center">
                <span class="text-sm font-semibold">Remaining</span>
                <span class="text-lg font-bold text-primary">{{ formatCurrency(quote.remaining) }}</span>
              </div>
            </CardContent>
          </Card>

          <!-- Amount Input -->
          <Card>
            <CardContent class="pt-6 space-y-2">
              <Label for="amount">Payment Amount</Label>
              <Input
                id="amount"
                v-model="amount"
                type="number"
                :min="quote.min_amount"
                :max="quote.max_amount"
                step="0.01"
                placeholder="Enter amount"
                class="text-center text-lg"
              />
              <p class="text-xs text-muted-foreground text-center">
                {{ formatCurrency(quote.min_amount) }} – {{ formatCurrency(quote.max_amount) }}
              </p>
            </CardContent>
          </Card>

          <!-- Error -->
          <Alert v-if="error" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>

          <!-- Actions -->
          <div class="flex gap-3">
            <Button variant="outline" class="flex-1" @click="resetFlow">
              <ArrowLeft class="h-4 w-4 mr-2" />
              Back
            </Button>
            <Button
              class="flex-1"
              :disabled="qrLoading || !amount || parseFloat(amount) <= 0"
              @click="generatePaymentQR"
            >
              <QrCode class="h-4 w-4 mr-2" />
              {{ qrLoading ? 'Generating...' : 'Generate QR' }}
            </Button>
          </div>

          <p class="text-xs text-center text-muted-foreground">
            Payment via InstaPay • Secure • Real-time
          </p>
        </div>
        
        <!-- Step 3: QR Code Display -->
        <div v-else class="space-y-4">
          <!-- QR Header -->
          <Card>
            <CardContent class="pt-6 pb-4 text-center">
              <p class="text-sm text-muted-foreground">
                Scan to pay <span class="font-bold text-foreground text-lg">{{ formatCurrency(parseFloat(amount)) }}</span>
              </p>
              <p class="text-xs text-muted-foreground font-mono mt-1">{{ quote.voucher_code }}</p>
            </CardContent>
          </Card>

          <!-- QR Code -->
          <div class="flex justify-center py-2">
            <div class="w-full max-w-xs">
              <QrDisplay
                :qr-code="paymentQr?.qr_code ?? null"
                :loading="qrLoading"
                :error="error"
              />
            </div>
          </div>

          <!-- Download -->
          <Button
            variant="outline"
            class="w-full"
            :disabled="!paymentQr?.qr_code"
            @click="handleDownloadQr"
          >
            <Download class="h-4 w-4 mr-2" />
            Download QR Code
          </Button>

          <!-- Mobile Hint -->
          <Alert>
            <Info class="h-4 w-4" />
            <AlertDescription>
              <strong>On mobile?</strong> Download the QR code and upload it in your GCash or Maya app.
            </AlertDescription>
          </Alert>

          <!-- QR Meta -->
          <div class="text-center space-y-1">
            <p class="text-xs text-muted-foreground">GCash · Maya · InstaPay</p>
            <p class="text-xs text-muted-foreground font-mono">{{ paymentQr?.qr_id }}</p>
            <p v-if="paymentQr?.expires_at" class="text-xs text-amber-600">
              Expires {{ new Date(paymentQr.expires_at).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) }}
            </p>
          </div>

          <!-- Payment Done -->
          <Card v-if="!paymentMarkedDone" class="border-amber-200 bg-amber-50/50">
            <CardContent class="pt-5 pb-5 space-y-3">
              <p class="text-sm text-center text-muted-foreground">
                After paying, tap below <strong>or</strong> check your SMS
              </p>
              <Button
                class="w-full h-12 text-base"
                :disabled="markingDone"
                @click="markPaymentDone"
              >
                {{ markingDone ? 'Processing...' : '✓ Payment Done' }}
              </Button>
            </CardContent>
          </Card>

          <Alert v-else class="border-emerald-200 bg-emerald-50/50">
            <AlertDescription class="text-center text-emerald-700">
              ✓ Payment marked as done. The voucher owner will confirm receipt.
            </AlertDescription>
          </Alert>

          <!-- Error -->
          <Alert v-if="error" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>

          <!-- Navigation -->
          <div class="flex gap-3">
            <Button variant="outline" class="flex-1" @click="backToDetails">
              Back
            </Button>
            <Button variant="outline" class="flex-1" @click="resetFlow">
              New Payment
            </Button>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-xs text-muted-foreground mt-6">
        Settlement Vouchers · Powered by x-Change
      </p>
    </div>
  </div>
</template>
