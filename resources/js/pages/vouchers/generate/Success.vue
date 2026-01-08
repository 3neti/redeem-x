<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Copy, Download, Home } from 'lucide-vue-next';
import { useClipboard } from '@/composables/useClipboard';

interface Voucher {
    id: number;
    code: string;
    amount: number;
    currency: string;
    status: string;
    expires_at?: string;
    created_at: string;
}

interface Props {
    vouchers: Voucher[];
    batch_id: string;
    count: number;
    total_value: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vouchers', href: '#' },
    { title: 'Generate', href: '#' },
    { title: 'Success', href: '#' },
];

const { copy, isCopied } = useClipboard();

const downloadCsv = () => {
    const headers = ['Code', 'Amount', 'Currency', 'Status', 'Expires At', 'Created At'];
    const rows = props.vouchers.map((v) => [
        v.code,
        v.amount,
        v.currency,
        v.status,
        v.expires_at || 'N/A',
        v.created_at,
    ]);

    const csv = [headers, ...rows].map((row) => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `vouchers-${props.batch_id}.csv`;
    link.click();
    URL.revokeObjectURL(url);
};

const formatDate = (dateString?: string) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Vouchers Generated Successfully" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <!-- Success Header -->
            <div class="flex items-start gap-4">
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/20"
                >
                    <CheckCircle2 class="size-6 text-green-600 dark:text-green-400" />
                </div>
                <div class="flex-1">
                    <Heading
                        title="Vouchers Generated Successfully!"
                        description="Your vouchers have been created and are ready to use."
                    />
                </div>
            </div>

            <!-- Summary Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Batch Summary</CardTitle>
                    <CardDescription>
                        Batch ID: <span class="font-mono">{{ batch_id }}</span>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Total Vouchers</p>
                            <p class="text-2xl font-bold">{{ count }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Total Value</p>
                            <p class="text-2xl font-bold">
                                ₱{{ total_value.toLocaleString() }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Status</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                Active
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Button @click="downloadCsv" variant="outline">
                            <Download class="mr-2 h-4 w-4" />
                            Download CSV
                        </Button>
                        <Link href="/dashboard">
                            <Button variant="outline">
                                <Home class="mr-2 h-4 w-4" />
                                Back to Dashboard
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Vouchers Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Voucher Codes</CardTitle>
                    <CardDescription>
                        Click any code to copy it to your clipboard
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full">
                            <thead class="border-b bg-muted/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Code</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium">Expires</th>
                                    <th class="w-[50px] px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="voucher in vouchers" :key="voucher.id" class="border-b last:border-0 hover:bg-muted/50">
                                    <td class="px-4 py-3">
                                        <code
                                            class="rounded bg-muted px-2 py-1 font-mono text-sm"
                                        >
                                            {{ voucher.code }}
                                        </code>
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        ₱{{ voucher.amount.toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/20 dark:text-green-400"
                                        >
                                            {{ voucher.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(voucher.expires_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Button
                                            @click="copy(voucher.code)"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8"
                                        >
                                            <CheckCircle2
                                                v-if="isCopied(voucher.code)"
                                                class="h-4 w-4 text-green-600"
                                            />
                                            <Copy v-else class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
