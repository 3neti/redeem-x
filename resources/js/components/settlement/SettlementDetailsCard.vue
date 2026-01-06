<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import Progress from '@/components/ui/progress.vue'
import { TrendingUp, Target, DollarSign, Wallet, Loader2 } from 'lucide-vue-next'
import VoucherStateBadge from './VoucherStateBadge.vue'
import axios from 'axios'
import { useToast } from '@/components/ui/toast/use-toast'

interface Props {
    voucherCode: string
    targetAmount: number
    paidTotal: number
    remaining: number
    state: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired'
    currency?: string
    isOwner?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    currency: 'PHP',
    isOwner: false
})

const page = usePage()
const { toast } = useToast()
const collecting = ref(false)

const progressPercentage = computed(() => {
    if (!props.targetAmount || props.targetAmount === 0) return 0
    return Math.min(Math.round((props.paidTotal / props.targetAmount) * 100), 100)
})

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: props.currency,
    }).format(amount)
}

const stateMessage = computed(() => {
    switch (props.state) {
        case 'active':
            return 'Accepting payments'
        case 'locked':
            return 'Temporarily locked - payments paused'
        case 'closed':
            return 'Target amount reached - no longer accepting payments'
        case 'cancelled':
            return 'Voucher cancelled'
        case 'expired':
            return 'Voucher expired'
        default:
            return ''
    }
})

const canCollect = computed(() => {
    return props.isOwner && props.paidTotal > 0
})

const collectPayments = async () => {
    collecting.value = true
    
    try {
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        
        const response = await axios.post(`/api/v1/vouchers/${props.voucherCode}/collect`, {}, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        })
        
        const data = response.data
        
        toast({
            title: 'Payments Collected',
            description: `${formatCurrency(data.data.amount_collected)} has been credited to your wallet`,
        })
        
        // Reload page to update balances
        window.location.reload()
        
    } catch (err: any) {
        toast({
            title: 'Collection Failed',
            description: err.response?.data?.message || 'Failed to collect payments',
            variant: 'destructive',
        })
    } finally {
        collecting.value = false
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Settlement Details</CardTitle>
                    <CardDescription>Payment collection progress</CardDescription>
                </div>
                <VoucherStateBadge :state="state" />
            </div>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Progress Bar -->
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Progress</span>
                    <span class="font-medium">{{ progressPercentage }}%</span>
                </div>
                <Progress :model-value="progressPercentage" class="h-2" />
                <p class="text-xs text-muted-foreground text-center">
                    {{ formatCurrency(paidTotal) }} / {{ formatCurrency(targetAmount) }} collected
                </p>
            </div>

            <!-- Amounts Grid -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- Target Amount -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Target class="h-4 w-4" />
                        <span>Target</span>
                    </div>
                    <p class="text-2xl font-bold">{{ formatCurrency(targetAmount) }}</p>
                </div>

                <!-- Paid Total -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <TrendingUp class="h-4 w-4" />
                        <span>Collected</span>
                    </div>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ formatCurrency(paidTotal) }}
                    </p>
                </div>

                <!-- Remaining -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <DollarSign class="h-4 w-4" />
                        <span>Remaining</span>
                    </div>
                    <p class="text-2xl font-bold" :class="remaining <= 0 ? 'text-blue-600 dark:text-blue-400' : ''">
                        {{ formatCurrency(remaining) }}
                    </p>
                </div>
            </div>

            <!-- State Message -->
            <div v-if="stateMessage" class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                {{ stateMessage }}
            </div>
            
            <!-- Collect Button (Owner Only) -->
            <div v-if="canCollect" class="pt-2">
                <Button 
                    @click="collectPayments" 
                    :disabled="collecting"
                    class="w-full"
                    size="lg"
                >
                    <Loader2 v-if="collecting" class="mr-2 h-4 w-4 animate-spin" />
                    <Wallet v-else class="mr-2 h-4 w-4" />
                    {{ collecting ? 'Collecting...' : `Collect ${formatCurrency(paidTotal)}` }}
                </Button>
                <p class="text-xs text-center text-muted-foreground mt-2">
                    Transfer collected payments to your wallet
                </p>
            </div>
        </CardContent>
    </Card>
</template>
