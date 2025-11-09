<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useContactApi } from '@/composables/useContactApi';
import type { ContactData, ContactStats, ContactListResponse } from '@/composables/useContactApi';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, Eye, User, Mail, UserCheck, Loader2 } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contacts', href: '#' },
];

const { loading, listContacts } = useContactApi();

const contacts = ref<ContactListResponse['data']>([]);
const pagination = ref({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
});
const stats = ref<ContactStats>({
    total: 0,
    withEmail: 0,
    withName: 0,
});

const searchQuery = ref('');

const fetchContacts = async (page: number = 1) => {
    try {
        const response = await listContacts({
            search: searchQuery.value || undefined,
            per_page: pagination.value.per_page,
            page,
        });
        
        contacts.value = response.data;
        pagination.value = response.pagination;
        stats.value = response.stats;
    } catch (error) {
        console.error('Failed to fetch contacts:', error);
    }
};

const applyFilters = async () => {
    await fetchContacts(1);
};

const clearFilters = async () => {
    searchQuery.value = '';
    await applyFilters();
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

// Pagination helpers
const paginationLinks = computed(() => {
    const links = [];
    
    // Previous
    links.push({
        label: '&laquo; Previous',
        page: pagination.value.current_page - 1,
        active: false,
        disabled: pagination.value.current_page === 1,
    });
    
    // Pages
    for (let i = 1; i <= pagination.value.last_page; i++) {
        links.push({
            label: i.toString(),
            page: i,
            active: i === pagination.value.current_page,
            disabled: false,
        });
    }
    
    // Next
    links.push({
        label: 'Next &raquo;',
        page: pagination.value.current_page + 1,
        active: false,
        disabled: pagination.value.current_page === pagination.value.last_page,
    });
    
    return links;
});

const goToPage = async (page: number) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        await fetchContacts(page);
    }
};

onMounted(async () => {
    await fetchContacts();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <Heading
                title="Contacts"
                description="Manage contacts who have redeemed vouchers"
            />

            <!-- Stats Cards -->
            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
            <div v-else class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Contacts</CardTitle>
                        <User class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">With Email</CardTitle>
                        <Mail class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.withEmail }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">With Name</CardTitle>
                        <UserCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.withName }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters and Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Contacts</CardTitle>
                            <CardDescription>{{ pagination.total }} contacts total</CardDescription>
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
                        <Button @click="applyFilters" variant="default" :disabled="loading">
                            <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                            Search
                        </Button>
                        <Button @click="clearFilters" variant="outline" :disabled="loading">
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
                                <tr v-if="loading">
                                    <td colspan="5" class="px-4 py-8 text-center">
                                        <Loader2 class="inline h-6 w-6 animate-spin text-muted-foreground" />
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="contact in contacts"
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
                                    <tr v-if="contacts.length === 0">
                                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                            No contacts found
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to
                            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
                            of {{ pagination.total }} results
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in paginationLinks"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="link.disabled || loading"
                                @click="goToPage(link.page)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
