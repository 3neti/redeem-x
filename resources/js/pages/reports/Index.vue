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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import VoucherTypeBadge from '@/components/settlement/VoucherTypeBadge.vue';
import VoucherStateBadge from '@/components/settlement/VoucherStateBadge.vue';
import { ref, computed } from 'vue';
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

interface SettlementVoucher {
    id: number;
    code: string;
    type: 'payable' | 'settlement';
    state: string;
    target_amount: number;
    paid_total: number;
    redeemed_total: number;
    remaining: number;
    currency: string;
    created_at: string;
    closed_at?: string;
}

interface DisbursementSummary {
    total_count: number;
    success_count: number;
    failed_count: number;
    total_amount: number;
    success_amount: number;
    success_rate: number;
}

interface SettlementSummary {
    total_count: number;
    active_count: number;
    closed_count: number;
    total_target: number;
    total_collected: number;
    collection_rate: number;
}

interface Props {
    report_type: 'disbursements' | 'settlements';
    disbursements?: {
        data: DisbursementAttempt[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    settlements?: {
        data: SettlementVoucher[];
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
    summary: DisbursementSummary | SettlementSummary;
}

const props = defineProps<Props>();

const activeTab = ref(props.report_type);
const fromDate = ref(props.filters.from_date);
const toDate = ref(props.filters.to_date);
const status = ref(props.filters.status || '');
const rail = ref(props.filters.rail || '');

const switchTab = (newTab: string) => {
    router.get('/reports', { type: newTab });
};

const applyFilters = () => {
    const params: Record<string, any> = {
        type: activeTab.value,
        from_date: fromDate.value,
        to_date: toDate.value,
        status: status.value || undefined,
    };
    
    // Only add rail for disbursement reports
    if (activeTab.value === 'disbursements') {
        params.rail = rail.value || undefined;
    }
    
    router.get('/reports', params);
};

const isDisbursementSummary = (summary: any): summary is DisbursementSummary => {
    return 'success_rate' in summary;
};

const isSettlementSummary = (summary: any): summary is SettlementSummary => {
    return 'collection_rate' in summary;
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
                title="Reports"
                description="View and analyze transactions and settlements"
            />
            
            <Tabs :model-value="activeTab" @update:model-value="switchTab" class="w-full">
                <TabsList class="grid w-full max-w-md grid-cols-2">
                    <TabsTrigger value="disbursements">Disbursements</TabsTrigger>
                    <TabsTrigger value="settlements">Settlements</TabsTrigger>
                </TabsList>
                
                <TabsContent value="disbursements" class="space-y-6">
                    <!-- Disbursement Report Content -->

                    <!-- Summary Cards -->
                    <div v-if="isDisbursementSummary(summary)" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
                    <Card>
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
                    <Card v-if="disbursements">
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
                </TabsContent>
                
                <TabsContent value="settlements" class="space-y-6">
                    <!-- Settlement Report Content -->
                    
                    <!-- Summary Cards -->
                    <div v-if="isSettlementSummary(summary)" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Total Vouchers</CardDescription>
                                <CardTitle class="text-3xl">{{ summary.total_count }}</CardTitle>
                            </CardHeader>
                        </Card>
                        
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Collection Rate</CardDescription>
                                <CardTitle class="text-3xl">{{ summary.collection_rate }}%</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-sm text-muted-foreground">
                                    {{ summary.active_count }} active, {{ summary.closed_count }} closed
                                </p>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Collected</CardDescription>
                                <CardTitle class="text-3xl">{{ formatCurrency(summary.total_collected) }}</CardTitle>
                            </CardHeader>
                        </Card>
                        
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Target</CardDescription>
                                <CardTitle class="text-3xl">{{ formatCurrency(summary.total_target) }}</CardTitle>
                            </CardHeader>
                        </Card>
                    </div>
                    
                    <!-- Filters -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Filters</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-3">
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
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="closed">Closed</SelectItem>
                                            <SelectItem value="locked">Locked</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="mt-4">
                                <Button @click="applyFilters">Apply Filters</Button>
                            </div>
                        </CardContent>
                    </Card>
                    
                    <!-- Settlements Table -->
                    <Card v-if="settlements">
                        <CardHeader>
                            <CardTitle>Settlement Vouchers</CardTitle>
                            <CardDescription>
                                Showing {{ settlements.data.length }} of {{ settlements.total }} records
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Voucher Code</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>Target Amount</TableHead>
                                        <TableHead>Collected</TableHead>
                                        <TableHead>Remaining</TableHead>
                                        <TableHead>Created</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in settlements.data" :key="item.id">
                                        <TableCell class="font-mono">{{ item.code }}</TableCell>
                                        <TableCell>
                                            <VoucherTypeBadge :type="item.type" size="sm" />
                                        </TableCell>
                                        <TableCell>
                                            <VoucherStateBadge :state="item.state" size="sm" />
                                        </TableCell>
                                        <TableCell>{{ formatCurrency(item.target_amount) }}</TableCell>
                                        <TableCell class="text-green-600 dark:text-green-400">
                                            {{ formatCurrency(item.paid_total) }}
                                        </TableCell>
                                        <TableCell>{{ formatCurrency(item.remaining) }}</TableCell>
                                        <TableCell class="text-sm">{{ formatDate(item.created_at) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            
                            <!-- Pagination info -->
                            <div v-if="settlements.last_page > 1" class="mt-4 text-sm text-muted-foreground">
                                Page {{ settlements.current_page }} of {{ settlements.last_page }}
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
