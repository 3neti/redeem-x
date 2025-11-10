<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Receipt } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Charge {
    id: number;
    user: { id: number; name: string; email: string };
    campaign: { id: number; name: string } | null;
    voucher_codes: string[];
    voucher_count: number;
    instructions_snapshot: any;
    charge_breakdown: Array<{ index: string; label: string; price: number }>;
    total_charge: string;
    charge_per_voucher: string;
    generated_at: string;
}

interface Props {
    charge: Charge;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/billing' },
    { title: 'Billing', href: '/admin/billing' },
    { title: `Charge #${props.charge.id}`, href: `/admin/billing/${props.charge.id}` },
];

const goBack = () => router.visit('/admin/billing');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Charge #${charge.id}`" />

        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" @click="goBack">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold">Charge Details</h1>
                    <p class="text-muted-foreground">{{ charge.user.name }} - {{ new Date(charge.generated_at).toLocaleDateString() }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Charge Breakdown</CardTitle>
                        <CardDescription>Itemized costs</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div v-for="item in charge.charge_breakdown" :key="item.index" class="flex justify-between text-sm">
                                <span>{{ item.label }}</span>
                                <span class="font-medium">₱{{ (item.price / 100).toFixed(2) }}</span>
                            </div>
                            <div class="border-t pt-2 flex justify-between font-semibold">
                                <span>Total</span>
                                <span class="text-primary">{{ charge.total_charge }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Voucher Codes</CardTitle>
                        <CardDescription>{{ charge.voucher_count }} codes generated</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="max-h-64 overflow-y-auto">
                            <code v-for="code in charge.voucher_codes" :key="code" class="block text-xs mb-1">{{ code }}</code>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
