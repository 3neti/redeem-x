<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Plus,
    Wallet,
    Receipt,
    Users,
    TicketX,
    Download,
    CircleDollarSign,
    Smartphone,
} from 'lucide-vue-next';

const page = usePage();
const redemptionEndpoint = computed(() => page.props.redemption_endpoint || '/disburse');
const settlementEndpoint = computed(() => page.props.settlement_endpoint || '/pay');
const settlementEnabled = computed(() => page.props.settlement_enabled ?? false);

const actions = computed(() => {
    const items = [
        {
            label: 'Generate Vouchers',
            href: '/vouchers/generate',
            icon: Plus,
            variant: 'default' as const,
            external: false,
        },
        {
            label: 'Mobile App',
            href: '/pwa/portal',
            icon: Smartphone,
            variant: 'outline' as const,
            external: false,
        },
        {
            label: 'Top Up Wallet',
            href: '/topup',
            icon: Wallet,
            variant: 'outline' as const,
            external: false,
        },
        {
            label: 'View Transactions',
            href: '/transactions',
            icon: Receipt,
            variant: 'outline' as const,
            external: false,
        },
        {
            label: 'View Contacts',
            href: '/contacts',
            icon: Users,
            variant: 'outline' as const,
            external: false,
        },
        {
            label: 'Redeem Voucher',
            href: redemptionEndpoint.value,
            icon: TicketX,
            variant: 'outline' as const,
            external: false,
        },
    ];
    
    // Add Settle Voucher if feature enabled
    if (settlementEnabled.value) {
        items.push({
            label: 'Settle Voucher',
            href: settlementEndpoint.value,
            icon: CircleDollarSign,
            variant: 'outline' as const,
            external: false,
        });
    }
    
    items.push({
        label: 'Export Reports',
        href: '/transactions/export',
        icon: Download,
        variant: 'outline' as const,
        external: true, // Use native link for file download
    });
    
    return items;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <Button
                    v-for="action in actions"
                    :key="action.href"
                    :variant="action.variant"
                    as-child
                    class="h-auto flex-col gap-2 py-4"
                >
                    <!-- Use native anchor for external/download links -->
                    <a v-if="action.external" :href="action.href" class="flex flex-col items-center">
                        <component :is="action.icon" class="h-5 w-5" />
                        <span class="text-xs">{{ action.label }}</span>
                    </a>
                    <!-- Use Inertia Link for internal navigation -->
                    <Link v-else :href="action.href">
                        <component :is="action.icon" class="h-5 w-5" />
                        <span class="text-xs">{{ action.label }}</span>
                    </Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
