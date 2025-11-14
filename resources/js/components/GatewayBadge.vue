<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Building2, CreditCard, Wallet, Banknote } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    gateway: string;
    showIcon?: boolean;
    showLabel?: boolean;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    showIcon: true,
    showLabel: true,
    size: 'md',
});

const gatewayConfig = {
    netbank: {
        label: 'NetBank',
        icon: Building2,
        variant: 'default' as const,
        color: 'text-blue-600',
        bgColor: 'bg-blue-50 dark:bg-blue-950',
    },
    icash: {
        label: 'iCash',
        icon: Banknote,
        variant: 'default' as const,
        color: 'text-green-600',
        bgColor: 'bg-green-50 dark:bg-green-950',
    },
    paypal: {
        label: 'PayPal',
        icon: Wallet,
        variant: 'secondary' as const,
        color: 'text-indigo-600',
        bgColor: 'bg-indigo-50 dark:bg-indigo-950',
    },
    stripe: {
        label: 'Stripe',
        icon: CreditCard,
        variant: 'secondary' as const,
        color: 'text-purple-600',
        bgColor: 'bg-purple-50 dark:bg-purple-950',
    },
    gcash: {
        label: 'GCash',
        icon: Wallet,
        variant: 'default' as const,
        color: 'text-blue-500',
        bgColor: 'bg-blue-50 dark:bg-blue-950',
    },
};

const config = computed(() => {
    const key = props.gateway.toLowerCase();
    return gatewayConfig[key as keyof typeof gatewayConfig] || {
        label: props.gateway,
        icon: Building2,
        variant: 'outline' as const,
        color: 'text-gray-600',
        bgColor: 'bg-gray-50 dark:bg-gray-900',
    };
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-xs px-2 py-0.5';
        case 'lg':
            return 'text-base px-3 py-1.5';
        default:
            return 'text-sm px-2.5 py-1';
    }
});

const iconSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-3 w-3';
        case 'lg':
            return 'h-5 w-5';
        default:
            return 'h-4 w-4';
    }
});
</script>

<template>
    <Badge 
        :variant="config.variant" 
        :class="[
            'inline-flex items-center gap-1.5 font-medium',
            config.bgColor,
            sizeClasses
        ]"
    >
        <component 
            v-if="showIcon" 
            :is="config.icon" 
            :class="[config.color, iconSizeClasses]" 
        />
        <span v-if="showLabel" class="text-gray-900 dark:text-gray-100">{{ config.label }}</span>
    </Badge>
</template>
