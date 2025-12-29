<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { ref } from 'vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '#' },
];

interface DisbursementAttempt {
    id: number;
    voucher_code: string;
    amount: number;
    currency: string;
    mobile: string;
    bank_code: string;
    settlement_rail: string;
    status: string;
    attempted_at: string;
    reference_id: string;
    error_message?: string;
}

interface Props {
    disbursements: {
        data: DisbursementAttempt[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        from_date: string;
        to_date: string;
        status?: string;
        rail?: string;
    };
    summary: {
        total_count: number;
        success_count: number;
        failed_count: number;
        total_amount: number;
        success_amount: number;
        success_rate: number;
    };
}

const props = defineProps<Props>();

const fromDate = ref(props.filters.from_date);
const toDate = ref(props.filters.to_date);
const status = ref(props.filters.status || '');
const rail = ref(props.filters.rail || '');

const applyFilters = () => {
    router.get('/reports', {
        from_date: fromDate.value,
        to_date: toDate.value,
        status: status.value || undefined,
        rail: rail.value || undefined,
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadge = (status: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        success: 'default',
        failed: 'destructive',
        pending: 'secondary',
    };
    return variants[status] || 'outline';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Disbursement Reports"
                description="View and analyze disbursement transactions for reconciliation"
            />

            <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-6">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total Attempts</CardDescription>
                    <CardTitle class="text-3xl">{{ summary.total_count }}</CardTitle>
                </CardHeader>
            </Card>
            
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Success Rate</CardDescription>
                    <CardTitle class="text-3xl">{{ summary.success_rate }}%</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        {{ summary.success_count }} / {{ summary.total_count }}
                    </p>
                </CardContent>
            </Card>
            
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total Amount</CardDescription>
                    <CardTitle class="text-3xl">{{ formatCurrency(summary.total_amount) }}</CardTitle>
                </CardHeader>
            </Card>
            
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Failed</CardDescription>
                    <CardTitle class="text-3xl text-destructive">{{ summary.failed_count }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Filters -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="text-sm font-medium">From Date</label>
                        <Input v-model="fromDate" type="date" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">To Date</label>
                        <Input v-model="toDate" type="date" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Status</label>
                        <Select v-model="status">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">All Statuses</SelectItem>
                                <SelectItem value="success">Success</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Rail</label>
                        <Select v-model="rail">
                            <SelectTrigger>
                                <SelectValue placeholder="All Rails" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">All Rails</SelectItem>
                                <SelectItem value="INSTAPAY">INSTAPAY</SelectItem>
                                <SelectItem value="PESONET">PESONET</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="mt-4">
                    <Button @click="applyFilters">Apply Filters</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Disbursements Table -->
        <Card>
            <CardHeader>
                <CardTitle>Disbursement Attempts</CardTitle>
                <CardDescription>
                    Showing {{ disbursements.data.length }} of {{ disbursements.total }} records
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Reference</TableHead>
                            <TableHead>Voucher</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Mobile</TableHead>
                            <TableHead>Bank</TableHead>
                            <TableHead>Rail</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Date</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in disbursements.data" :key="item.id">
                            <TableCell class="font-mono text-xs">{{ item.reference_id }}</TableCell>
                            <TableCell class="font-mono">{{ item.voucher_code }}</TableCell>
                            <TableCell>{{ formatCurrency(item.amount) }}</TableCell>
                            <TableCell class="font-mono text-sm">{{ item.mobile }}</TableCell>
                            <TableCell class="text-sm">{{ item.bank_code }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ item.settlement_rail }}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getStatusBadge(item.status)">
                                    {{ item.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm">{{ formatDate(item.attempted_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                
                <!-- Pagination info -->
                <div v-if="disbursements.last_page > 1" class="mt-4 text-sm text-muted-foreground">
                    Page {{ disbursements.current_page }} of {{ disbursements.last_page }}
                </div>
            </CardContent>
        </Card>
        </div>
    </AppLayout>
</template>
