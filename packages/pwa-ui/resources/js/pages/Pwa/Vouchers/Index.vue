<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import PwaLayout from '../../../layouts/PwaLayout.vue';
import VoucherCard from '../../../components/VoucherCard.vue';
import { Button } from '../../../components/ui/button';
import { Ticket, Plus } from 'lucide-vue-next';

interface Voucher {
    code: string;
    amount: number;
    target_amount: number | null;
    voucher_type: 'redeemable' | 'payable' | 'settlement';
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

// All formatting logic now in VoucherCard component

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
        <div class="sticky top-[52px] z-30 bg-background border-b">
            <!-- Status Filters -->
            <div class="px-4 py-2 border-b">
                <div class="text-xs font-medium text-muted-foreground mb-2">Status</div>
                <div class="flex gap-2 overflow-x-auto pb-1">
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
                        :class="{ 'bg-primary text-primary-foreground': filter === 'active' }"
                        @click="setFilter('active')"
                    >
                        Active
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': filter === 'redeemed' }"
                        @click="setFilter('redeemed')"
                    >
                        Redeemed
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': filter === 'expired' }"
                        @click="setFilter('expired')"
                    >
                        Expired
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': filter === 'locked' }"
                        @click="setFilter('locked')"
                    >
                        Locked
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': filter === 'cancelled' }"
                        @click="setFilter('cancelled')"
                    >
                        Cancelled
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': filter === 'closed' }"
                        @click="setFilter('closed')"
                    >
                        Closed
                    </Button>
                </div>
            </div>
            
            <!-- Type Filters -->
            <div class="px-4 py-2">
                <div class="text-xs font-medium text-muted-foreground mb-2">Type</div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-secondary text-secondary-foreground': filter === 'type-redeemable' }"
                        @click="setFilter('type-redeemable')"
                    >
                        Redeemable
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-secondary text-secondary-foreground': filter === 'type-payable' }"
                        @click="setFilter('type-payable')"
                    >
                        Payable
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-secondary text-secondary-foreground': filter === 'type-settlement' }"
                        @click="setFilter('type-settlement')"
                    >
                        Settlement
                    </Button>
                </div>
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
                    <VoucherCard :voucher="voucher" />
                </Link>
            </div>
        </div>
    </PwaLayout>
</template>
