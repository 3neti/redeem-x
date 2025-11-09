<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useContactApi, type ContactData, type ContactVoucher } from '@/composables/useContactApi';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, User, Mail, Phone, Calendar, TicketCheck, Loader2, AlertCircle } from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import ErrorBoundary from '@/components/ErrorBoundary.vue';
import type { BreadcrumbItem } from '@/types';

interface Props {
    contact: ContactData;
}

const props = defineProps<Props>();

const { loading, showContact, getContactVouchers } = useContactApi();
const contactData = ref<ContactData>(props.contact);
const vouchers = ref<ContactVoucher[]>([]);
const error = ref<string | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contacts', href: '/contacts' },
    { title: contactData.value.mobile, href: '#' },
];

const loadContactData = async () => {
    try {
        error.value = null;
        
        // Load fresh contact data and vouchers in parallel
        const [freshContact, contactVouchers] = await Promise.all([
            showContact(contactData.value.id),
            getContactVouchers(contactData.value.id),
        ]);
        
        contactData.value = freshContact;
        vouchers.value = contactVouchers;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load contact data';
        console.error('Failed to load contact:', err);
    }
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

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency || 'PHP',
    }).format(amount);
};

const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' => {
    if (status === 'redeemed') return 'default';
    if (status === 'expired') return 'destructive';
    return 'secondary';
};

const goBack = () => {
    router.visit('/contacts');
};

const viewVoucher = (code: string) => {
    router.visit(`/vouchers/${code}`);
};

onMounted(() => {
    loadContactData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <ErrorBoundary>
            <div class="mx-auto max-w-7xl space-y-6 p-6">
                <!-- Error Alert -->
                <Alert v-if="error" variant="destructive" class="mb-4">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>

                <!-- Header -->
                <div class="flex items-center justify-between">
                    <Heading
                        :title="contactData.name || contactData.mobile"
                        description="Contact details and voucher history"
                    />
                    <Button variant="outline" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Contacts
                    </Button>
                </div>

                <!-- Contact Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Contact Information</CardTitle>
                        <CardDescription>Basic details about this contact</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="flex justify-center py-8">
                            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                        </div>
                        <dl v-else class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <dt class="flex items-center text-sm font-medium text-muted-foreground">
                                    <Phone class="mr-2 h-4 w-4" />
                                    Mobile Number
                                </dt>
                                <dd class="mt-1 text-sm font-mono">{{ contactData.mobile }}</dd>
                            </div>
                            <div v-if="contactData.name">
                                <dt class="flex items-center text-sm font-medium text-muted-foreground">
                                    <User class="mr-2 h-4 w-4" />
                                    Name
                                </dt>
                                <dd class="mt-1 text-sm">{{ contactData.name }}</dd>
                            </div>
                            <div v-if="contactData.email">
                                <dt class="flex items-center text-sm font-medium text-muted-foreground">
                                    <Mail class="mr-2 h-4 w-4" />
                                    Email
                                </dt>
                                <dd class="mt-1 text-sm">{{ contactData.email }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center text-sm font-medium text-muted-foreground">
                                    <Calendar class="mr-2 h-4 w-4" />
                                    Last Updated
                                </dt>
                                <dd class="mt-1 text-sm">{{ formatDate(contactData.updated_at) }}</dd>
                            </div>
                            <div v-if="contactData.country">
                                <dt class="text-sm font-medium text-muted-foreground">Country</dt>
                                <dd class="mt-1 text-sm">{{ contactData.country }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <!-- Voucher History Card -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Voucher History</CardTitle>
                                <CardDescription>
                                    All vouchers redeemed by this contact
                                </CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <TicketCheck class="h-5 w-5 text-muted-foreground" />
                                <span class="text-2xl font-bold">{{ vouchers.length }}</span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loading" class="flex justify-center py-8">
                            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                        </div>
                        
                        <!-- Vouchers Table -->
                        <div v-else-if="vouchers.length > 0" class="relative overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Code</th>
                                        <th class="px-4 py-3 text-left">Amount</th>
                                        <th class="px-4 py-3 text-left">Status</th>
                                        <th class="px-4 py-3 text-left">Redeemed At</th>
                                        <th class="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="voucher in vouchers"
                                        :key="voucher.code"
                                        class="border-b hover:bg-muted/50"
                                    >
                                        <td class="px-4 py-3 font-mono font-semibold">
                                            {{ voucher.code }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ formatAmount(voucher.amount, voucher.currency) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge :variant="getStatusVariant(voucher.status)">
                                                {{ voucher.status }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ voucher.redeemed_at ? formatDate(voucher.redeemed_at) : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                @click="viewVoucher(voucher.code)"
                                            >
                                                View
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State -->
                        <div v-else class="py-12 text-center text-muted-foreground">
                            <TicketCheck class="mx-auto mb-4 h-12 w-12 opacity-20" />
                            <p>No vouchers found for this contact</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </ErrorBoundary>
    </AppLayout>
</template>
