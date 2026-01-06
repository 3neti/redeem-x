<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { ArrowDownLeft, Plus, Loader2 } from 'lucide-vue-next'
import axios from 'axios'
import { useToast } from '@/components/ui/toast/use-toast'

interface Props {
    voucherCode: string
    isOwner: boolean
    canAcceptPayment: boolean
}

interface Payment {
    id: number
    amount: number
    created_at: string
    meta?: {
        payment_id?: string
        payer?: string
        confirmed_by?: string
    }
}

const props = defineProps<Props>()
const { toast } = useToast()
const page = usePage()

// Manual confirmation state
const confirmAmount = ref('')
const confirmPaymentId = ref('')
const confirmPayer = ref('')
const confirming = ref(false)

// Payment history state
const payments = ref<Payment[]>([])
const loadingHistory = ref(false)
const historyError = ref<string | null>(null)
const historyLoaded = ref(false)

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount / 100) // Convert minor units to major
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const fetchPaymentHistory = async () => {
    if (historyLoaded.value) return
    
    loadingHistory.value = true
    historyError.value = null
    
    try {
        const response = await axios.get(`/api/v1/vouchers/${props.voucherCode}/payments`)
        const data = response.data?.data || response.data || []
        payments.value = Array.isArray(data) ? data : []
        historyLoaded.value = true
    } catch (err: any) {
        historyError.value = err.message || 'Failed to load payment history'
        console.error('Failed to fetch payment history:', err)
    } finally {
        loadingHistory.value = false
    }
}

const confirmPayment = async () => {
    const amount = parseFloat(confirmAmount.value)
    
    if (!amount || amount <= 0) {
        toast({
            title: 'Invalid Amount',
            description: 'Please enter a valid payment amount',
            variant: 'destructive',
        })
        return
    }
    
    confirming.value = true
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await fetch('/api/v1/vouchers/confirm-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                voucher_code: props.voucherCode,
                amount: amount,
                payment_id: confirmPaymentId.value || undefined,
                payer: confirmPayer.value || undefined,
            }),
        })
        
        const data = await response.json()
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to confirm payment')
        }
        
        toast({
            title: 'Payment Confirmed',
            description: `₱${amount.toFixed(2)} has been credited to this voucher`,
        })
        
        // Reset form
        confirmAmount.value = ''
        confirmPaymentId.value = ''
        confirmPayer.value = ''
        
        // Refresh payment history
        historyLoaded.value = false
        await fetchPaymentHistory()
        
        // Reload page to update settlement details
        window.location.reload()
        
    } catch (err: any) {
        toast({
            title: 'Confirmation Failed',
            description: err.message || 'Failed to confirm payment',
            variant: 'destructive',
        })
    } finally {
        confirming.value = false
    }
}

// Auto-load payment history on mount
fetchPaymentHistory()
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Payments</CardTitle>
            <CardDescription>
                {{ isOwner ? 'Confirm payments and view history' : 'View payment history' }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Manual Payment Confirmation (Owner Only) -->
            <div v-if="isOwner && canAcceptPayment" class="space-y-4 p-4 rounded-lg border bg-muted/50">
                <div class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    <h4 class="font-medium">Confirm Payment</h4>
                </div>
                <p class="text-sm text-muted-foreground">
                    Manually confirm a payment received outside the system (e.g., GCash screenshot)
                </p>
                
                <div class="space-y-3">
                    <div class="space-y-2">
                        <Label for="amount">Amount (₱)</Label>
                        <Input
                            id="amount"
                            type="number"
                            v-model="confirmAmount"
                            placeholder="100.00"
                            step="0.01"
                            min="0.01"
                            :disabled="confirming"
                        />
                    </div>
                    
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="payment-id">Payment ID (Optional)</Label>
                            <Input
                                id="payment-id"
                                v-model="confirmPaymentId"
                                placeholder="GCASH-123456"
                                :disabled="confirming"
                            />
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="payer">Payer (Optional)</Label>
                            <Input
                                id="payer"
                                v-model="confirmPayer"
                                placeholder="Mobile or name"
                                :disabled="confirming"
                            />
                        </div>
                    </div>
                    
                    <Button 
                        @click="confirmPayment" 
                        :disabled="confirming || !confirmAmount"
                        class="w-full"
                    >
                        <Loader2 v-if="confirming" class="mr-2 h-4 w-4 animate-spin" />
                        {{ confirming ? 'Confirming...' : 'Confirm Payment' }}
                    </Button>
                </div>
            </div>
            
            <Separator v-if="isOwner && canAcceptPayment" />
            
            <!-- Payment History -->
            <div class="space-y-4">
                <h4 class="font-medium">Payment History</h4>
                
                <!-- Loading State -->
                <div v-if="loadingHistory" class="text-center py-8 text-sm text-muted-foreground">
                    <Loader2 class="h-6 w-6 mx-auto mb-2 animate-spin" />
                    Loading payment history...
                </div>
                
                <!-- Error State -->
                <div v-else-if="historyError" class="text-center py-4 text-sm text-destructive">
                    {{ historyError }}
                </div>
                
                <!-- Empty State -->
                <div v-else-if="payments.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                    <ArrowDownLeft class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p>No payments yet</p>
                    <p class="text-xs mt-1">Payments will appear here once received</p>
                </div>
                
                <!-- Payment List -->
                <div v-else class="space-y-3">
                    <div 
                        v-for="payment in payments" 
                        :key="payment.id"
                        class="flex items-center justify-between p-3 rounded-lg border bg-card hover:bg-muted/50 transition"
                    >
                        <div class="space-y-1">
                            <p class="font-medium">{{ formatCurrency(payment.amount) }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDate(payment.created_at) }}</p>
                            <div v-if="payment.meta" class="flex flex-wrap gap-2 text-xs">
                                <span v-if="payment.meta.payment_id" class="font-mono text-muted-foreground">
                                    ID: {{ payment.meta.payment_id }}
                                </span>
                                <span v-if="payment.meta.payer" class="text-muted-foreground">
                                    From: {{ payment.meta.payer }}
                                </span>
                                <span v-if="payment.meta.confirmed_by === 'console'" class="text-amber-600">
                                    Manual
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                <ArrowDownLeft class="h-3 w-3" />
                                Payment
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
