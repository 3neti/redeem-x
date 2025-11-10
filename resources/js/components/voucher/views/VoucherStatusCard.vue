<script setup lang="ts">
/**
 * VoucherStatusCard - Specialized status widget component
 * 
 * Displays voucher status badge with icon and amount in a card layout.
 * Perfect for voucher lists, dashboards, and preview modals.
 * 
 * @component
 * @example
 * <VoucherStatusCard
 *   :is-redeemed="voucher.is_redeemed"
 *   :is-expired="voucher.is_expired"
 *   :amount="voucher.amount"
 *   :currency="voucher.currency"
 * />
 */
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TicketCheck, Clock, XCircle } from 'lucide-vue-next';

interface Props {
    isRedeemed: boolean;
    isExpired: boolean;
    amount: number;
    currency: string;
}

const props = defineProps<Props>();

const statusInfo = computed(() => {
    if (props.isRedeemed) {
        return { 
            variant: 'default' as const, 
            label: 'Redeemed', 
            icon: TicketCheck,
            description: 'This voucher has been successfully redeemed'
        };
    }
    if (props.isExpired) {
        return { 
            variant: 'destructive' as const, 
            label: 'Expired', 
            icon: XCircle,
            description: 'This voucher has expired and can no longer be used'
        };
    }
    return { 
        variant: 'secondary' as const, 
        label: 'Active', 
        icon: Clock,
        description: 'This voucher is active and can be redeemed'
    };
});

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};
</script>

<template>
    <Card>
        <CardContent class="pt-6">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <Badge :variant="statusInfo.variant" class="text-sm">
                            <component :is="statusInfo.icon" class="mr-1 h-3 w-3" />
                            {{ statusInfo.label }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ statusInfo.description }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-muted-foreground">Amount</div>
                    <div class="text-3xl font-bold">
                        {{ formatAmount(amount, currency) }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
