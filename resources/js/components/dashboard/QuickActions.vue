<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import {
    Plus,
    Wallet,
    Receipt,
    Users,
    TicketX,
    Download,
} from 'lucide-vue-next';

const actions = [
    {
        label: 'Generate Vouchers',
        href: '/vouchers/generate',
        icon: Plus,
        variant: 'default' as const,
    },
    {
        label: 'Top Up Wallet',
        href: '/topup',
        icon: Wallet,
        variant: 'outline' as const,
    },
    {
        label: 'View Transactions',
        href: '/transactions',
        icon: Receipt,
        variant: 'outline' as const,
    },
    {
        label: 'View Contacts',
        href: '/contacts',
        icon: Users,
        variant: 'outline' as const,
    },
    {
        label: 'Redeem Voucher',
        href: '/redeem',
        icon: TicketX,
        variant: 'outline' as const,
    },
    {
        label: 'Export Reports',
        href: '/transactions/export',
        icon: Download,
        variant: 'outline' as const,
    },
];
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
                    <Link :href="action.href">
                        <component :is="action.icon" class="h-5 w-5" />
                        <span class="text-xs">{{ action.label }}</span>
                    </Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
