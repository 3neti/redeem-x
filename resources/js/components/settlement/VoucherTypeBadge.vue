<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Ticket, CircleDollarSign, FileText } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
    type: 'redeemable' | 'payable' | 'settlement'
    size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md'
})

const config = computed(() => {
    switch (props.type) {
        case 'redeemable':
            return {
                label: 'Redeemable',
                icon: Ticket,
                variant: 'default' as const,
                class: 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20'
            }
        case 'payable':
            return {
                label: 'Payable',
                icon: CircleDollarSign,
                variant: 'default' as const,
                class: 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20'
            }
        case 'settlement':
            return {
                label: 'Settlement',
                icon: FileText,
                variant: 'default' as const,
                class: 'bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20'
            }
        default:
            return {
                label: 'Unknown',
                icon: Ticket,
                variant: 'outline' as const,
                class: ''
            }
    }
})

const iconSize = computed(() => {
    switch (props.size) {
        case 'sm': return 'h-3 w-3'
        case 'lg': return 'h-5 w-5'
        case 'md':
        default: return 'h-4 w-4'
    }
})
</script>

<template>
    <Badge :variant="config.variant" :class="config.class">
        <component :is="config.icon" :class="['mr-1', iconSize]" />
        {{ config.label }}
    </Badge>
</template>
