<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowLeft, 
    TicketCheck, 
    Clock, 
    XCircle, 
    Calendar,
    DollarSign,
    User,
    Copy,
    CheckCircle2
} from 'lucide-vue-next';
import ErrorBoundary from '@/components/ErrorBoundary.vue';
import type { BreadcrumbItem } from '@/types';

interface VoucherOwner {
    name: string;
    email: string;
}

interface VoucherProp {
    code: string;
    status: string;
    amount: number;
    currency: string;
    created_at: string;
    expires_at?: string;
    redeemed_at?: string;
    starts_at?: string;
    is_expired: boolean;
    is_redeemed: boolean;
    can_redeem: boolean;
    owner?: VoucherOwner;
}

interface Props {
    voucher: VoucherProp;
}

const props = defineProps<Props>();

const copied = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '/vouchers' },
    { title: props.voucher.code, href: '#' },
];

const statusInfo = computed(() => {
    if (props.voucher.is_redeemed) {
        return { 
            variant: 'default' as const, 
            label: 'Redeemed', 
            icon: TicketCheck,
            description: 'This voucher has been successfully redeemed'
        };
    }
    if (props.voucher.is_expired) {
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

const goBack = () => {
    router.visit('/vouchers');
};

const redeemLink = computed(() => {
    return `${window.location.origin}/redeem/${props.voucher.code}`;
});

const copyRedeemLink = async () => {
    try {
        await navigator.clipboard.writeText(redeemLink.value);
        // Could add a toast notification here
    } catch (err) {
        console.error('Failed to copy link:', err);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <ErrorBoundary>
            <div class="mx-auto max-w-4xl space-y-6 p-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading
                        :title="voucher.code"
                        description="Voucher details and status"
                    />
                    <Button variant="outline" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Vouchers
                    </Button>
                </div>

                <!-- Status Card -->
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
                                    {{ formatAmount(voucher.amount, voucher.currency) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

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
                                    <Copy class="mr-2 h-3 w-3" />
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

                <!-- Owner Information (if available) -->
                <Card v-if="voucher.owner">
                    <CardHeader>
                        <CardTitle>Owner Information</CardTitle>
                        <CardDescription>Details about the voucher owner</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <dt class="flex items-center text-sm font-medium text-muted-foreground">
                                    <User class="mr-2 h-4 w-4" />
                                    Name
                                </dt>
                                <dd class="mt-1 text-sm">{{ voucher.owner.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Email</dt>
                                <dd class="mt-1 text-sm">{{ voucher.owner.email }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </ErrorBoundary>
    </AppLayout>
</template>
