<script setup lang="ts">
import { ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import QrDisplay from '@/components/shared/QrDisplay.vue'
import PayWidget from '@/components/PayWidget.vue'
import { useToast } from '@/components/ui/toast/use-toast'

interface Props {
    initial_code?: string | null;
}

defineProps<Props>()

const page = usePage()
const { toast } = useToast()
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
</script>

<template>
  <Head title="Pay Voucher" />

  <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
    <div class="w-full max-w-sm">
      <!-- Step 1: Enter Voucher Code (PayWidget) -->
      <div v-if="!quote">
        <PayWidget 
          :initial-code="initial_code" 
          @quote-loaded="handleQuoteLoaded"
        />
      </div>

      <!-- Main Card (Steps 2-3) -->
      <div v-else class="bg-white rounded-lg shadow-md p-6">

        <!-- Step 2: Payment Details -->
        <div v-if="!showQrStep" class="space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Payment Details</h2>
            
            <div class="space-y-3 bg-gray-50 p-4 rounded-lg">
              <div class="flex justify-between">
                <span class="text-gray-600">Voucher Code:</span>
                <span class="font-mono font-semibold">{{ quote.voucher_code }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Type:</span>
                <span class="capitalize">{{ quote.voucher_type }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Target Amount:</span>
                <span class="font-semibold">{{ formatCurrency(quote.target_amount) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Paid Total:</span>
                <span class="text-green-600">{{ formatCurrency(quote.paid_total) }}</span>
              </div>
              <div class="flex justify-between border-t pt-3">
                <span class="text-gray-900 font-semibold">Remaining:</span>
                <span class="text-blue-600 font-bold text-lg">{{ formatCurrency(quote.remaining) }}</span>
              </div>
            </div>
          </div>

          <div>
            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
              Payment Amount
            </label>
            <input
              id="amount"
              v-model="amount"
              type="number"
              :min="quote.min_amount"
              :max="quote.max_amount"
              step="0.01"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Enter amount"
            />
            <p class="text-xs text-gray-500 mt-1">
              Min: {{ formatCurrency(quote.min_amount) }} | Max: {{ formatCurrency(quote.max_amount) }}
            </p>
          </div>

          <div class="flex gap-3">
            <button
              @click="resetFlow"
              class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg font-semibold hover:bg-gray-300 transition"
            >
              Back
            </button>
            <button
              @click="generatePaymentQR"
              :disabled="qrLoading || !amount || parseFloat(amount) <= 0"
              class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition"
            >
              {{ qrLoading ? 'Generating...' : 'Generate QR' }}
            </button>
          </div>

          <p v-if="error" class="text-red-600 text-sm text-center">{{ error }}</p>

          <p class="text-xs text-center text-gray-500">
            Payment via InstaPay • Secure • Real-time
          </p>
        </div>
        
        <!-- Step 3: QR Code Display -->
        <div v-else class="space-y-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Payment QR Code</h2>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
              <p class="text-sm text-blue-800">
                <strong>Scan this QR code</strong> to make a payment of 
                <strong class="text-lg">{{ formatCurrency(parseFloat(amount)) }}</strong>
                to voucher <strong class="font-mono">{{ quote.voucher_code }}</strong>
              </p>
            </div>
            
            <div class="flex justify-center py-4">
              <div class="w-full max-w-xs">
                <QrDisplay
                  :qr-code="paymentQr?.qr_code ?? null"
                  :loading="qrLoading"
                  :error="error"
                />
              </div>
            </div>
            
            <div class="text-center space-y-2">
              <p class="text-sm text-gray-600">
                Scan with GCash, Maya, or any InstaPay-enabled app
              </p>
              <p class="text-xs text-gray-500 font-mono">
                QR ID: {{ paymentQr?.qr_id }}
              </p>
              <p v-if="paymentQr?.expires_at" class="text-xs text-amber-600">
                Expires: {{ new Date(paymentQr.expires_at).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) }}
              </p>
            </div>
          </div>
          
          <!-- Payment Done Confirmation -->
          <div v-if="!paymentMarkedDone" class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <p class="text-sm text-amber-800 mb-3">
              After scanning and completing payment in your app, please confirm:
            </p>
            <button
              @click="markPaymentDone"
              :disabled="markingDone"
              class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition"
            >
              {{ markingDone ? 'Processing...' : '✓ Payment Done' }}
            </button>
          </div>
          
          <div v-else class="bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-sm text-green-800 text-center">
              ✓ Payment marked as done! The voucher owner will confirm receipt.
            </p>
          </div>
          
          <p v-if="error" class="text-red-600 text-sm text-center">{{ error }}</p>
          
          <div class="flex gap-3">
            <button
              @click="backToDetails"
              class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg font-semibold hover:bg-gray-300 transition"
            >
              Back
            </button>
            <button
              @click="resetFlow"
              class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
            >
              New Payment
            </button>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-sm text-gray-500 mt-6">
        Settlement Vouchers • Powered by x-Change
      </p>
    </div>
  </div>
</template>
