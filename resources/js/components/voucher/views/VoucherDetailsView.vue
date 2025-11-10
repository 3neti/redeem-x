/**
 * VoucherDetailsView - Display component for basic voucher information
 * 
 * Read-only component that displays voucher code, redemption link, and details
 * with copy-to-clipboard functionality.
 * 
 * @component
 * @example
 * <VoucherDetailsView :voucher="voucher" />
 */
<script setup lang="ts">
import { ref, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Copy, CheckCircle2, DollarSign, Calendar } from 'lucide-vue-next';

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

interface Props {
    voucher: VoucherData;
}

const props = defineProps<Props>();

const copied = ref(false);
const copiedLink = ref(false);

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(props.voucher.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy code:', err);
    }
};

const redeemLink = computed(() => {
    return `${window.location.origin}/redeem/${props.voucher.code}`;
});

const copyRedeemLink = async () => {
    try {
        await navigator.clipboard.writeText(redeemLink.value);
        copiedLink.value = true;
        setTimeout(() => {
            copiedLink.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy link:', err);
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Voucher Code Card -->
        <Card>
            <CardHeader>
                <CardTitle>Voucher Code</CardTitle>
                <CardDescription>Share this code or link to redeem the voucher</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center gap-2">
                    <code class="flex-1 rounded-md bg-muted px-4 py-3 font-mono text-lg font-semibold">
                        {{ voucher.code }}
                    </code>
                    <Button variant="outline" size="icon" @click="copyCode">
                        <CheckCircle2 v-if="copied" class="h-4 w-4 text-green-500" />
                        <Copy v-else class="h-4 w-4" />
                    </Button>
                </div>
                <div v-if="!voucher.is_redeemed && !voucher.is_expired" class="space-y-2">
                    <div class="text-sm font-medium">Redemption Link</div>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 truncate rounded-md bg-muted px-4 py-2 text-sm">
                            {{ redeemLink }}
                        </code>
                        <Button variant="outline" size="sm" @click="copyRedeemLink">
                            <CheckCircle2 v-if="copiedLink" class="mr-2 h-3 w-3 text-green-500" />
                            <Copy v-else class="mr-2 h-3 w-3" />
                            Copy
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Details Card -->
        <Card>
            <CardHeader>
                <CardTitle>Voucher Details</CardTitle>
                <CardDescription>Additional information about this voucher</CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="flex items-center text-sm font-medium text-muted-foreground">
                            <DollarSign class="mr-2 h-4 w-4" />
                            Amount
                        </dt>
                        <dd class="mt-1 text-sm font-semibold">
                            {{ formatAmount(voucher.amount, voucher.currency) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="flex items-center text-sm font-medium text-muted-foreground">
                            <Calendar class="mr-2 h-4 w-4" />
                            Created
                        </dt>
                        <dd class="mt-1 text-sm">{{ formatDate(voucher.created_at) }}</dd>
                    </div>
                    <div v-if="voucher.starts_at">
                        <dt class="text-sm font-medium text-muted-foreground">Valid From</dt>
                        <dd class="mt-1 text-sm">{{ formatDate(voucher.starts_at) }}</dd>
                    </div>
                    <div v-if="voucher.expires_at">
                        <dt class="text-sm font-medium text-muted-foreground">Expires</dt>
                        <dd class="mt-1 text-sm">{{ formatDate(voucher.expires_at) }}</dd>
                    </div>
                    <div v-else>
                        <dt class="text-sm font-medium text-muted-foreground">Expires</dt>
                        <dd class="mt-1 text-sm">Never</dd>
                    </div>
                    <div v-if="voucher.redeemed_at">
                        <dt class="text-sm font-medium text-muted-foreground">Redeemed At</dt>
                        <dd class="mt-1 text-sm">{{ formatDate(voucher.redeemed_at) }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
