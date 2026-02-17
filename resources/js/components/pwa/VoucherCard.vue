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

const formatDateHuman = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)}w ago`;
    
    // Fallback to formatted date
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
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
        case 'scheduled':
            return 'secondary'; // Muted color for future activation
        default:
            return 'default';
    }
};

const getVoucherTypeColor = (type?: string) => {
    // Option A: All types use consistent gray fill for visual consistency
    return 'secondary';
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
        case 'scheduled':
            return 'secondary'; // Gray/muted for future activation
        case 'redeemed':
            return 'default'; // Blue/primary (success state)
        case 'locked':
            return 'secondary'; // Gray/muted
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
        case 'scheduled':
            return 'Scheduled';
        case 'redeemed':
            return 'Redeemed';
        default:
            return state;
    }
};

const isExpired = (expiresAt?: string | null) => {
    if (!expiresAt) return false;
    return new Date(expiresAt) < new Date();
};

const shouldShowStateBadge = (voucher: Voucher) => {
    // Two-badge system: ALWAYS show status badge
    return true;
};

const getDisplayState = (voucher: Voucher) => {
    // Priority: redeemed > manual states > time-based states > active
    
    // Terminal states
    if (voucher.redeemed_at) {
        return 'redeemed';
    }
    if (voucher.state === 'cancelled') {
        return 'cancelled';
    }
    if (voucher.state === 'locked') {
        return 'locked';
    }
    if (voucher.state === 'closed') {
        return 'closed';
    }
    
    // Time-based states
    if (voucher.status === 'expired') {
        return 'expired';
    }
    if (voucher.status === 'scheduled') {
        return 'scheduled';
    }
    
    // Default: active
    return 'active';
};
</script>

<template>
    <Card class="hover:bg-muted/50 transition-colors">
        <CardContent class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="font-semibold text-base tracking-wide">{{ voucher.code }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ formatDateHuman(voucher.created_at) }}
                    </div>
                </div>
                <div class="text-right space-y-1">
                    <div class="font-semibold text-sm">
                        ₱{{ getAmountDisplay(voucher) }}
                    </div>
                    <div class="flex gap-1 justify-end flex-wrap">
                        <!-- Type Badge (always show if voucher_type exists) -->
                        <Badge 
                            v-if="showType && voucher.voucher_type" 
                            :variant="getVoucherTypeColor(voucher.voucher_type)" 
                            class="text-xs"
                        >
                            {{ getVoucherTypeLabel(voucher.voucher_type) }}
                        </Badge>
                        
                        <!-- Status Badge (always show) -->
                        <Badge 
                            :variant="getStateColor(getDisplayState(voucher))" 
                            class="text-xs"
                        >
                            {{ getStateLabel(getDisplayState(voucher)) }}
                        </Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
