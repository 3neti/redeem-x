<script setup lang="ts">
/**
 * VoucherCodeDisplay - Minimal code display widget
 * 
 * Compact component showing voucher code with copy button.
 * Perfect for tables, lists, and quick actions.
 * 
 * @component
 * @example
 * <VoucherCodeDisplay :code="voucher.code" />
 * 
 * @example
 * // With custom size
 * <VoucherCodeDisplay :code="voucher.code" size="sm" />
 */
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Copy, CheckCircle2 } from 'lucide-vue-next';

interface Props {
    code: string;
    size?: 'sm' | 'default' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'default',
});

const copied = ref(false);

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy code:', err);
    }
};

const sizeClasses = {
    sm: 'text-xs px-2 py-1',
    default: 'text-sm px-3 py-2',
    lg: 'text-base px-4 py-3',
};
</script>

<template>
    <div class="flex items-center gap-2">
        <code 
            :class="[
                'rounded-md bg-muted font-mono font-semibold',
                sizeClasses[size]
            ]"
        >
            {{ code }}
        </code>
        <Button 
            variant="ghost" 
            :size="size === 'sm' ? 'sm' : 'icon'"
            @click="copyCode"
            class="flex-shrink-0"
        >
            <CheckCircle2 v-if="copied" :class="size === 'sm' ? 'h-3 w-3' : 'h-4 w-4'" class="text-green-500" />
            <Copy v-else :class="size === 'sm' ? 'h-3 w-3' : 'h-4 w-4'" />
        </Button>
    </div>
</template>
