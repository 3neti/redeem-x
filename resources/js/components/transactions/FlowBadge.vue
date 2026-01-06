<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { ArrowUpRight, ArrowDownLeft, Plus } from 'lucide-vue-next'

interface Props {
    flow: 'pay' | 'redeem' | 'topup' | string
}

const props = defineProps<Props>()

const flowConfig = computed(() => {
    switch (props.flow) {
        case 'pay':
            return {
                label: 'Payment',
                variant: 'default' as const,
                icon: ArrowDownLeft,
                color: 'text-blue-600 dark:text-blue-400',
            }
        case 'redeem':
            return {
                label: 'Redemption',
                variant: 'secondary' as const,
                icon: ArrowUpRight,
                color: 'text-green-600 dark:text-green-400',
            }
        case 'topup':
            return {
                label: 'Top-up',
                variant: 'outline' as const,
                icon: Plus,
                color: 'text-purple-600 dark:text-purple-400',
            }
        default:
            return {
                label: props.flow,
                variant: 'outline' as const,
                icon: null,
                color: 'text-muted-foreground',
            }
    }
})
</script>

<template>
    <Badge :variant="flowConfig.variant" class="inline-flex items-center gap-1">
        <component 
            v-if="flowConfig.icon" 
            :is="flowConfig.icon" 
            class="h-3 w-3"
            :class="flowConfig.color"
        />
        <span>{{ flowConfig.label }}</span>
    </Badge>
</template>
