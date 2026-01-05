<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'

const voucherCode = ref('')
const amount = ref('')
const quote = ref<any>(null)
const loading = ref(false)
const error = ref('')

async function getQuote() {
  if (!voucherCode.value.trim()) {
    error.value = 'Please enter a voucher code'
    return
  }

  loading.value = true
  error.value = ''
  quote.value = null

  try {
    const response = await fetch('/pay/quote', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ code: voucherCode.value }),
    })

    const data = await response.json()

    if (!response.ok) {
      error.value = data.error || 'Failed to validate voucher'
      return
    }

    quote.value = data
  } catch (err: any) {
    error.value = err.message || 'Network error'
  } finally {
    loading.value = false
  }
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

  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Pay Voucher</h1>
        <p class="text-gray-600 mt-2">Enter voucher code to make a payment</p>
      </div>

      <!-- Main Card -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Step 1: Enter Voucher Code -->
        <div v-if="!quote" class="space-y-4">
          <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
              Voucher Code
            </label>
            <input
              id="code"
              v-model="voucherCode"
              type="text"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Enter voucher code"
              :disabled="loading"
              @keyup.enter="getQuote"
            />
          </div>

          <button
            @click="getQuote"
            :disabled="loading"
            class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition"
          >
            {{ loading ? 'Validating...' : 'Continue' }}
          </button>

          <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
        </div>

        <!-- Step 2: Payment Details -->
        <div v-else class="space-y-6">
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
              @click="quote = null; error = ''"
              class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg font-semibold hover:bg-gray-300 transition"
            >
              Back
            </button>
            <button
              class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
            >
              Generate QR
            </button>
          </div>

          <p class="text-xs text-center text-gray-500">
            Payment via InstaPay • Secure • Real-time
          </p>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-sm text-gray-500 mt-6">
        Settlement Vouchers • Powered by x-Change
      </p>
    </div>
  </div>
</template>
