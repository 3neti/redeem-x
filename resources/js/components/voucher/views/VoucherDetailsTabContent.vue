<script setup lang="ts">
/**
 * VoucherDetailsTabContent - Composite view component for details tab
 * 
 * Combines VoucherDetailsView and VoucherRedemptionView into a single
 * component for simplified usage in voucher detail pages.
 * 
 * @component
 * @example
 * <VoucherDetailsTabContent 
 *   :voucher="voucher" 
 *   :redemption="redemption" 
 * />
 */
import VoucherDetailsView from './VoucherDetailsView.vue';
import VoucherRedemptionView from './VoucherRedemptionView.vue';

interface VoucherData {
    code: string;
    amount: number;
    currency: string;
    created_at: string;
    expires_at?: string;
    redeemed_at?: string;
    starts_at?: string;
    is_expired: boolean;
    is_redeemed: boolean;
}

interface RedemptionData {
    name?: string;
    email?: string;
    address?: string;
    selfie?: string;
    signature?: string;
    location?: string;
    [key: string]: any;
}

interface Props {
    voucher: VoucherData;
    redemption?: RedemptionData | null;
    voucherType?: string;
    availableBalance?: number;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="space-y-6">
        <!-- Voucher Details -->
        <VoucherDetailsView 
            :voucher="voucher" 
            :voucher-type="voucherType"
            :available-balance="availableBalance"
        />

        <!-- Redemption Information (if redeemed) -->
        <VoucherRedemptionView
            v-if="voucher.is_redeemed && redemption"
            :redemption="redemption"
        />
    </div>
</template>
