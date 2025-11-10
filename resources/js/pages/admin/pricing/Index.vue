<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Edit, Settings } from 'lucide-vue-next';
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
    updated_at: string;
}

interface Props {
    items: InstructionItem[];
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
];

const groupedItems = props.items.reduce((acc, item) => {
    if (!acc[item.type]) {
        acc[item.type] = [];
    }
    acc[item.type].push(item);
    return acc;
}, {} as Record<string, InstructionItem[]>);

const editItem = (itemId: number) => {
    router.visit(`/admin/pricing/${itemId}/edit`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Pricing Management" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Pricing Management</h1>
                    <p class="text-muted-foreground">
                        Manage pricing for voucher customization features
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <Card v-for="(items, type) in groupedItems" :key="type">
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Settings class="h-5 w-5" />
                            <CardTitle class="capitalize">{{ type }}</CardTitle>
                        </div>
                        <CardDescription>
                            {{ items.length }} pricing {{ items.length === 1 ? 'item' : 'items' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Index</TableHead>
                                    <TableHead>Price</TableHead>
                                    <TableHead>Last Updated</TableHead>
                                    <TableHead class="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in items" :key="item.id">
                                    <TableCell class="font-medium">{{ item.name }}</TableCell>
                                    <TableCell>
                                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">{{ item.index }}</code>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">{{ item.price_formatted }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ new Date(item.updated_at).toLocaleDateString() }}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="editItem(item.id)"
                                        >
                                            <Edit class="h-4 w-4 mr-1" />
                                            Edit
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
