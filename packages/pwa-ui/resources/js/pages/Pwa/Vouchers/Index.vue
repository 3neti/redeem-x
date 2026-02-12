<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import PwaLayout from '../../../layouts/PwaLayout.vue';
import { Card, CardContent } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Ticket, Plus, Filter } from 'lucide-vue-next';

interface Voucher {
    code: string;
    amount: number;
    currency: string;
    status: string;
    redeemed_at: string | null;
    created_at: string;
}

interface Props {
    vouchers: {
        data: Voucher[];
        links: any;
        meta: any;
    };
    filter: string;
}

const props = defineProps<Props>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
};

const formatAmount = (amount: number | string | null | undefined) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    const validNum = typeof num === 'number' && !isNaN(num) ? num : 0;
    return validNum.toFixed(2);
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

const setFilter = (filter: string) => {
    router.visit(`/pwa/vouchers?filter=${filter}`, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <PwaLayout title="Vouchers">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    <Ticket class="h-6 w-6 text-primary" />
                    <h1 class="text-lg font-semibold">Vouchers</h1>
                </div>
                <Button as-child size="sm">
                    <Link href="/pwa/vouchers/generate">
                        <Plus class="h-4 w-4" />
                    </Link>
                </Button>
            </div>
        </header>

        <!-- Filters -->
        <div class="sticky top-[52px] z-30 bg-background border-b px-4 py-2">
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-primary text-primary-foreground': filter === 'all' }"
                    @click="setFilter('all')"
                >
                    All
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-primary text-primary-foreground': filter === 'redeemable' }"
                    @click="setFilter('redeemable')"
                >
                    Redeemable
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-primary text-primary-foreground': filter === 'redeemed' }"
                    @click="setFilter('redeemed')"
                >
                    Redeemed
                </Button>
            </div>
        </div>

        <!-- Content -->
        <div class="p-4">
            <div v-if="vouchers.data.length === 0" class="py-12 text-center">
                <Ticket class="mx-auto h-12 w-12 text-muted-foreground/50" />
                <h3 class="mt-4 text-sm font-medium">No vouchers found</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ filter === 'all' ? 'Generate your first voucher to get started.' : `No ${filter} vouchers.` }}
                </p>
                <Button as-child class="mt-4">
                    <Link href="/pwa/vouchers/generate">
                        <Plus class="mr-2 h-4 w-4" />
                        Generate Voucher
                    </Link>
                </Button>
            </div>

            <div v-else class="space-y-3">
                <Link
                    v-for="voucher in vouchers.data"
                    :key="voucher.code"
                    :href="`/pwa/vouchers/${voucher.code}`"
                    class="block"
                >
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
                                        {{ voucher.currency }} {{ formatAmount(voucher.amount) }}
                                    </div>
                                    <Badge :variant="getStatusColor(voucher.status)" class="text-xs">
                                        {{ voucher.status }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </PwaLayout>
</template>
