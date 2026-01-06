<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { CheckCircle, Lock, XCircle, Ban, Clock } from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
    state: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired'
    size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md'
})

const config = computed(() => {
    switch (props.state) {
        case 'active':
            return {
                label: 'Active',
                icon: CheckCircle,
                class: 'bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20'
            }
        case 'locked':
            return {
                label: 'Locked',
                icon: Lock,
                class: 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border-yellow-500/20'
            }
        case 'closed':
            return {
                label: 'Closed',
                icon: CheckCircle,
                class: 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20'
            }
        case 'cancelled':
            return {
                label: 'Cancelled',
                icon: Ban,
                class: 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20'
            }
        case 'expired':
            return {
                label: 'Expired',
                icon: Clock,
                class: 'bg-gray-500/10 text-gray-700 dark:text-gray-400 border-gray-500/20'
            }
        default:
            return {
                label: 'Unknown',
                icon: XCircle,
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
    <Badge variant="default" :class="config.class">
        <component :is="config.icon" :class="['mr-1', iconSize]" />
        {{ config.label }}
    </Badge>
</template>
