<script setup lang="ts">
import { Card, CardContent } from './ui/card';
import { Badge } from './ui/badge';

interface Voucher {
    code: string;
    amount: number;
    target_amount?: number | null;
    voucher_type?: 'redeemable' | 'payable' | 'settlement';
    currency: string;
    status: string;
    state?: 'active' | 'locked' | 'closed' | 'cancelled' | 'expired';
    redeemed_at?: string | null;
    expires_at?: string | null;
    created_at: string;
}

interface Props {
    voucher: Voucher;
    showType?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showType: true,
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
};

const formatAmount = (amount: number | string | null | undefined) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    const validNum = typeof num === 'number' && !isNaN(num) ? num : 0;
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(validNum);
};

const getAmountDisplay = (voucher: Voucher) => {
    if (voucher.voucher_type === 'settlement' && voucher.target_amount) {
        // Settlement: Show "loan → payback"
        return `${formatAmount(voucher.amount)} → ${formatAmount(voucher.target_amount)}`;
    }
    // Redeemable and Payable: Show single amount
    return formatAmount(voucher.amount);
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'redeemed':
            return 'success';
        case 'pending':
            return 'warning';
        default:
            return 'default';
    }
};

const getVoucherTypeColor = (type?: string) => {
    switch (type) {
        case 'payable':
            return 'default'; // Blue/gray
        case 'settlement':
            return 'secondary'; // Purple/muted
        case 'redeemable':
        default:
            return 'outline'; // Border only
    }
};

const getVoucherTypeLabel = (type?: string) => {
    switch (type) {
        case 'payable':
            return 'Payable';
        case 'settlement':
            return 'Settlement';
        case 'redeemable':
        default:
            return 'Redeemable';
    }
};

const getStateColor = (state?: string) => {
    switch (state) {
        case 'active':
            return 'default'; // Blue/primary
        case 'locked':
            return 'secondary'; // Gray/muted (closest to warning)
        case 'closed':
            return 'secondary'; // Gray/muted
        case 'cancelled':
            return 'destructive'; // Red
        case 'expired':
            return 'outline'; // Border only
        default:
            return 'default';
    }
};

const getStateLabel = (state?: string) => {
    switch (state) {
        case 'active':
            return 'Active';
        case 'locked':
            return 'Locked';
        case 'closed':
            return 'Closed';
        case 'cancelled':
            return 'Cancelled';
        case 'expired':
            return 'Expired';
        default:
            return state;
    }
};

const isExpired = (expiresAt?: string | null) => {
    if (!expiresAt) return false;
    return new Date(expiresAt) < new Date();
};

const shouldShowStateBadge = (voucher: Voucher) => {
    // Show state badge if: cancelled, locked, closed, or expired
    return voucher.state === 'cancelled' || 
           voucher.state === 'locked' || 
           voucher.state === 'closed' ||
           isExpired(voucher.expires_at);
};

const getDisplayState = (voucher: Voucher) => {
    // Priority: Manual states (cancelled, locked, closed) > expired > active
    if (voucher.state && voucher.state !== 'active') {
        return voucher.state; // Show manual state (cancelled, locked, closed)
    }
    // Only show expired if state is still active
    if (isExpired(voucher.expires_at)) {
        return 'expired';
    }
    return 'active';
};
</script>

<template>
    <Card class="hover:bg-muted/50 transition-colors">
        <CardContent class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="font-medium text-sm">{{ voucher.code }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ formatDate(voucher.created_at) }}
                    </div>
                </div>
                <div class="text-right space-y-1">
                    <div class="font-semibold text-sm">
                        ₱{{ getAmountDisplay(voucher) }}
                    </div>
                    <div class="flex gap-1 justify-end flex-wrap">
                        <Badge 
                            v-if="showType && voucher.voucher_type" 
                            :variant="getVoucherTypeColor(voucher.voucher_type)" 
                            class="text-xs"
                        >
                            {{ getVoucherTypeLabel(voucher.voucher_type) }}
                        </Badge>
                        <Badge 
                            v-if="shouldShowStateBadge(voucher)" 
                            :variant="getStateColor(getDisplayState(voucher))" 
                            class="text-xs"
                        >
                            {{ getStateLabel(getDisplayState(voucher)) }}
                        </Badge>
                        <Badge 
                            v-else-if="voucher.redeemed_at" 
                            variant="default" 
                            class="text-xs"
                        >
                            Redeemed
                        </Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
