<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as vouchersIndex } from '@/actions/App/Http/Controllers/Voucher/VoucherController';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, Eye, Download, TicketCheck, Clock, XCircle, ListFilter } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface VoucherData {
    id: number;
    code: string;
    status: string;
    amount: number;
    currency: string;
    created_at: string;
    expires_at?: string;
    redeemed_at?: string;
    is_expired: boolean;
    is_redeemed: boolean;
}

interface Props {
    vouchers: {
        data: VoucherData[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters?: {
        status?: string;
        search?: string;
    };
    stats?: {
        total: number;
        active: number;
        redeemed: number;
        expired: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(vouchersIndex.url(), {
        search: searchQuery.value || undefined,
        status: selectedStatus.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const getStatusBadge = (voucher: VoucherData) => {
    if (voucher.is_redeemed) {
        return { variant: 'default' as const, label: 'Redeemed', icon: TicketCheck };
    }
    if (voucher.is_expired) {
        return { variant: 'destructive' as const, label: 'Expired', icon: XCircle };
    }
    return { variant: 'secondary' as const, label: 'Active', icon: Clock };
};

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const viewVoucher = (id: number) => {
    router.visit(`/vouchers/${id}`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Vouchers"
                description="Manage and track all your vouchers"
            />

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Vouchers</CardTitle>
                        <ListFilter class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.total || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.active || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Redeemed</CardTitle>
                        <TicketCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.redeemed || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Expired</CardTitle>
                        <XCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.expired || 0 }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Vouchers</CardTitle>
                            <CardDescription>{{ vouchers?.total || 0 }} vouchers total</CardDescription>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-col gap-4 pt-4 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by code..."
                                class="pl-8"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <select
                            v-model="selectedStatus"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-40"
                            @change="applyFilters"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="redeemed">Redeemed</option>
                            <option value="expired">Expired</option>
                        </select>
                        <Button @click="applyFilters" variant="default">
                            Filter
                        </Button>
                        <Button @click="clearFilters" variant="outline">
                            Clear
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Table -->
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left">Code</th>
                                    <th class="px-4 py-3 text-left">Amount</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Created</th>
                                    <th class="px-4 py-3 text-left">Expires</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="voucher in vouchers.data"
                                    :key="voucher.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="px-4 py-3 font-mono font-semibold">
                                        {{ voucher.code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatAmount(voucher.amount, voucher.currency) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="getStatusBadge(voucher).variant">
                                            <component :is="getStatusBadge(voucher).icon" class="mr-1 h-3 w-3" />
                                            {{ getStatusBadge(voucher).label }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(voucher.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ voucher.expires_at ? formatDate(voucher.expires_at) : 'Never' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="viewVoucher(voucher.id)"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="vouchers.data.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                        No vouchers found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="vouchers.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (vouchers.current_page - 1) * vouchers.per_page + 1 }} to
                            {{ Math.min(vouchers.current_page * vouchers.per_page, vouchers.total) }}
                            of {{ vouchers.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in vouchers.links"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="!link.url"
                                @click="link.url && router.visit(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
