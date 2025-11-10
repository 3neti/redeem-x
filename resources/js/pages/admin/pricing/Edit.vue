<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ArrowLeft, DollarSign, History } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface InstructionItem {
    id: number;
    name: string;
    index: string;
    type: string;
    price: number;
    price_formatted: string;
    currency: string;
    meta: Record<string, any>;
    label: string;
    description: string;
}

interface PriceHistory {
    id: number;
    old_price: string;
    new_price: string;
    changed_by: string;
    reason: string;
    effective_at: string;
}

interface Props {
    item: InstructionItem;
    history: PriceHistory[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/admin/pricing',
    },
    {
        title: 'Pricing Management',
        href: '/admin/pricing',
    },
    {
        title: props.item.name,
        href: `/admin/pricing/${props.item.id}/edit`,
    },
];

const form = useForm({
    price: props.item.price_formatted,
    reason: '',
    label: props.item.label || '',
    description: props.item.description || '',
});

const submit = () => {
    form.patch(`/admin/pricing/${props.item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('reason');
        },
    });
};

const goBack = () => {
    router.visit('/admin/pricing');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit Pricing - ${item.name}`" />

        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" @click="goBack">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold">Edit Pricing</h1>
                    <p class="text-muted-foreground">
                        {{ item.name }} ({{ item.index }})
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Edit Form -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <DollarSign class="h-5 w-5" />
                            <CardTitle>Update Pricing</CardTitle>
                        </div>
                        <CardDescription>
                            Change price with a reason for audit trail
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="price">Price (₱)</Label>
                                <Input
                                    id="price"
                                    v-model="form.price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    placeholder="20.00"
                                />
                                <InputError :message="form.errors.price" />
                                <p class="text-xs text-muted-foreground">
                                    Current price: {{ item.price_formatted }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="reason">Reason for Change <span class="text-destructive">*</span></Label>
                                <Textarea
                                    id="reason"
                                    v-model="form.reason"
                                    required
                                    placeholder="e.g., Increased due to operational costs"
                                    rows="3"
                                />
                                <InputError :message="form.errors.reason" />
                                <p class="text-xs text-muted-foreground">
                                    Required for audit trail (min 3 characters)
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="label">Label (Optional)</Label>
                                <Input
                                    id="label"
                                    v-model="form.label"
                                    placeholder="e.g., Email Address"
                                />
                                <InputError :message="form.errors.label" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="e.g., Email notification channel"
                                    rows="2"
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                                </Button>
                                <Button type="button" variant="outline" @click="goBack">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Price History -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <History class="h-5 w-5" />
                            <CardTitle>Price History</CardTitle>
                        </div>
                        <CardDescription>
                            Recent price changes with audit trail
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="history.length === 0" class="text-sm text-muted-foreground">
                            No price changes recorded yet
                        </div>
                        <div v-else class="space-y-4">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Change</TableHead>
                                        <TableHead>By</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="h in history" :key="h.id">
                                        <TableCell class="text-xs">
                                            {{ new Date(h.effective_at).toLocaleDateString() }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="text-xs">
                                                <span class="text-muted-foreground line-through">{{ h.old_price }}</span>
                                                →
                                                <span class="font-medium">{{ h.new_price }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-muted-foreground">
                                                {{ h.reason }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-xs">{{ h.changed_by }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
