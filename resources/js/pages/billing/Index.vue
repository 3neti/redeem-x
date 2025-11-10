<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Receipt, Wallet, FileText } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Charge {
    id: number;
    campaign: { id: number; name: string } | null;
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
    summary: {
        total_vouchers: number;
        total_charges: number;
        current_month_charges: number;
    };
    filters: {
        from?: string;
        to?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Billing', href: '/billing' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="My Billing" />

        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Billing & Usage</h1>
                <p class="text-muted-foreground">View your voucher generation charges</p>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Vouchers</CardTitle>
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ summary.total_vouchers.toLocaleString() }}</div>
                        <p class="text-xs text-muted-foreground">All time</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Charges</CardTitle>
                        <Wallet class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">₱{{ summary.total_charges.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground">All time</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Month</CardTitle>
                        <Receipt class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">₱{{ summary.current_month_charges.toFixed(2) }}</div>
                        <p class="text-xs text-muted-foreground">Current billing period</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Charges Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Charges</CardTitle>
                    <CardDescription>Your voucher generation history</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Campaign</TableHead>
                                <TableHead>Vouchers</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Per Voucher</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="charge in charges.data" :key="charge.id">
                                <TableCell class="text-sm">
                                    {{ new Date(charge.generated_at).toLocaleString() }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ charge.campaign?.name || 'Direct' }}
                                </TableCell>
                                <TableCell class="text-sm">{{ charge.voucher_count }}</TableCell>
                                <TableCell class="font-medium">{{ charge.total_charge }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ charge.charge_per_voucher }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
