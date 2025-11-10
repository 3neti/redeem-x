<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Receipt } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Campaign {
    id: number;
    name: string;
}

interface Charge {
    id: number;
    user: User;
    campaign: Campaign | null;
    voucher_count: number;
    total_charge: string;
    charge_per_voucher: string;
    generated_at: string;
    charge_breakdown: any[];
}

interface Props {
    charges: {
        data: Charge[];
        links: any[];
        meta: any;
    };
    filters: {
        user_id?: number;
        from?: string;
        to?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/billing' },
    { title: 'Billing', href: '/admin/billing' },
];

const viewCharge = (chargeId: number) => {
    router.visit(`/admin/billing/${chargeId}`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Admin Billing" />

        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Billing Records</h1>
                <p class="text-muted-foreground">View all users' voucher generation charges</p>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Receipt class="h-5 w-5" />
                        <CardTitle>All Charges</CardTitle>
                    </div>
                    <CardDescription>{{ charges.meta.total }} total records</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Campaign</TableHead>
                                <TableHead>Vouchers</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="charge in charges.data" :key="charge.id">
                                <TableCell>
                                    <div class="text-sm">{{ charge.user.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ charge.user.email }}</div>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ new Date(charge.generated_at).toLocaleDateString() }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ charge.campaign?.name || 'Direct' }}
                                </TableCell>
                                <TableCell class="text-sm">{{ charge.voucher_count }}</TableCell>
                                <TableCell class="font-medium">{{ charge.total_charge }}</TableCell>
                                <TableCell>
                                    <Button variant="outline" size="sm" @click="viewCharge(charge.id)">
                                        View
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
