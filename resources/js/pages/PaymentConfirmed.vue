<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { CheckCircle2 } from 'lucide-vue-next'

interface Props {
    voucherCode: string
    amount: number
    currency: string
    timestamp: string
}

const props = defineProps<Props>()

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.currency || 'PHP',
    }).format(amount)
}

const formatDate = (timestamp: string) => {
    return new Date(timestamp).toLocaleString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <Head title="Payment Confirmed" />
    
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-green-50 to-emerald-100 p-6">
        <div class="w-full max-w-md">
            <!-- Success Card -->
            <div class="bg-white rounded-2xl shadow-2xl p-8 text-center space-y-6">
                <!-- Success Icon -->
                <div class="flex justify-center">
                    <div class="rounded-full bg-green-100 p-4">
                        <CheckCircle2 class="h-16 w-16 text-green-600" />
                    </div>
                </div>
                
                <!-- Heading -->
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold text-gray-900">Payment Confirmed!</h1>
                    <p class="text-gray-600">Your payment has been successfully processed</p>
                </div>
                
                <!-- Payment Details -->
                <div class="bg-gray-50 rounded-lg p-6 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Amount</span>
                        <span class="text-2xl font-bold text-green-600">{{ formatCurrency(amount) }}</span>
                    </div>
                    <div class="border-t pt-3 flex justify-between items-center">
                        <span class="text-gray-600">Voucher Code</span>
                        <span class="font-mono font-semibold text-lg">{{ voucherCode }}</span>
                    </div>
                    <div class="border-t pt-3 flex justify-between items-center">
                        <span class="text-gray-600">Confirmed At</span>
                        <span class="text-sm text-gray-800">{{ formatDate(timestamp) }}</span>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
                    <p class="text-sm text-blue-900">
                        <strong class="block mb-2">What happens next?</strong>
                        The voucher owner will be notified and funds have been credited to the voucher. 
                        You will receive a confirmation notification shortly.
                    </p>
                </div>
                
                <!-- Footer Text -->
                <p class="text-xs text-gray-500 pt-4">
                    Transaction ID: {{ voucherCode }}-{{ new Date(timestamp).getTime() }}
                </p>
            </div>
            
            <!-- Branding Footer -->
            <p class="text-center text-sm text-gray-600 mt-6">
                Powered by <strong>redeem-x</strong>
            </p>
        </div>
    </div>
</template>
