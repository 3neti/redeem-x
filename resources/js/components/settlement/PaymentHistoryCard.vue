<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ChevronDown, History, ArrowDownLeft } from 'lucide-vue-next'
import axios from 'axios'

interface Props {
    voucherCode: string
}

interface Payment {
    id: number
    amount: number
    created_at: string
    meta?: {
        payment_id?: string
        voucher_code?: string
    }
}

const props = defineProps<Props>()

const isOpen = ref(false)
const payments = ref<Payment[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

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
    loading.value = true
    error.value = null
    
    try {
        // Fetch wallet transactions filtered by voucher_code and flow: 'pay'
        const response = await axios.get('/api/v1/wallet/transactions', {
            params: {
                type: 'deposit',
                per_page: 50,
            }
        })
        
        // Filter for payments to this voucher
        payments.value = response.data.data.filter((tx: Payment) => 
            tx.meta?.voucher_code === props.voucherCode
        )
    } catch (err: any) {
        error.value = err.message || 'Failed to load payment history'
        console.error('Failed to fetch payment history:', err)
    } finally {
        loading.value = false
    }
}

// Fetch when collapsed is opened
const handleOpenChange = (open: boolean) => {
    isOpen.value = open
    if (open && payments.value.length === 0 && !loading.value) {
        fetchPaymentHistory()
    }
}

// Auto-fetch on mount if user wants it immediately visible
onMounted(() => {
    // Optional: auto-fetch on mount
    // fetchPaymentHistory()
})
</script>

<template>
    <Collapsible v-model:open="isOpen" @update:open="handleOpenChange">
        <Card>
            <CollapsibleTrigger class="w-full">
                <CardHeader class="cursor-pointer hover:bg-muted/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <History class="h-5 w-5" />
                            <CardTitle>Payment History</CardTitle>
                        </div>
                        <ChevronDown 
                            class="h-4 w-4 transition-transform" 
                            :class="{ 'rotate-180': isOpen }" 
                        />
                    </div>
                    <CardDescription class="text-left">
                        View all payments made to this voucher
                    </CardDescription>
                </CardHeader>
            </CollapsibleTrigger>
            
            <CollapsibleContent>
                <CardContent>
                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-4 text-sm text-muted-foreground">
                        Loading payment history...
                    </div>
                    
                    <!-- Error State -->
                    <div v-else-if="error" class="text-center py-4 text-sm text-destructive">
                        {{ error }}
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
                                <p v-if="payment.meta?.payment_id" class="text-xs font-mono text-muted-foreground">
                                    ID: {{ payment.meta.payment_id }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                    <ArrowDownLeft class="h-3 w-3" />
                                    Payment
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Card>
    </Collapsible>
</template>
