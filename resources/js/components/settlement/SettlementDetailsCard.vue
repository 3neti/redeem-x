<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import Progress from '@/components/ui/progress.vue'
import { TrendingUp, Target, DollarSign } from 'lucide-vue-next'
import VoucherStateBadge from './VoucherStateBadge.vue'

interface Props {
    targetAmount: number
    paidTotal: number
    remaining: number
    state: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired'
    currency?: string
}

const props = withDefaults(defineProps<Props>(), {
    currency: 'PHP'
})

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
        </CardContent>
    </Card>
</template>
