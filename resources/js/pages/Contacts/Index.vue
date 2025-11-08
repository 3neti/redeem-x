<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/ContactController';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, Eye, User, Mail, UserCheck } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface ContactData {
    id: number;
    mobile: string;
    name?: string;
    email?: string;
    updated_at: string;
}

interface Props {
    contacts: {
        data: ContactData[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters?: {
        search?: string;
    };
    stats?: {
        total: number;
        withEmail: number;
        withName: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contacts', href: '#' },
];

const searchQuery = ref(props.filters?.search || '');

const applyFilters = () => {
    router.get(contactsIndex.url(), {
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    applyFilters();
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const viewContact = (id: number) => {
    router.visit(`/contacts/${id}`);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Contacts"
                description="Manage contacts who have redeemed vouchers"
            />

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Contacts</CardTitle>
                        <User class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.total || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">With Email</CardTitle>
                        <Mail class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.withEmail || 0 }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">With Name</CardTitle>
                        <UserCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats?.withName || 0 }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Contacts</CardTitle>
                            <CardDescription>{{ contacts?.total || 0 }} contacts total</CardDescription>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-col gap-4 pt-4 sm:flex-row">
                        <div class="relative flex-1">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by name, mobile, or email..."
                                class="pl-8"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <Button @click="applyFilters" variant="default">
                            Search
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
                                    <th class="px-4 py-3 text-left">Mobile</th>
                                    <th class="px-4 py-3 text-left">Name</th>
                                    <th class="px-4 py-3 text-left">Email</th>
                                    <th class="px-4 py-3 text-left">Last Updated</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="contact in contacts.data"
                                    :key="contact.id"
                                    class="border-b hover:bg-muted/50"
                                >
                                    <td class="px-4 py-3 font-mono">
                                        {{ contact.mobile }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ contact.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ contact.email || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(contact.updated_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="viewContact(contact.id)"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="contacts.data.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                        No contacts found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="contacts.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (contacts.current_page - 1) * contacts.per_page + 1 }} to
                            {{ Math.min(contacts.current_page * contacts.per_page, contacts.total) }}
                            of {{ contacts.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in contacts.links"
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
