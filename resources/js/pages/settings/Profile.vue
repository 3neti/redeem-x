<script setup lang="ts">
import { ref, onMounted } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { Form, Head, usePage } from '@inertiajs/vue3';
import axios from '@/lib/axios';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import MerchantAmountSettings from '@/components/MerchantAmountSettings.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/components/ui/toast/use-toast';
import { Loader2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

const { toast } = useToast();

// Merchant profile data
const loadingMerchant = ref(true);
const savingMerchant = ref(false);
const merchant = ref<any>(null);
const categories = ref<Record<string, string>>({});

const merchantForm = ref({
    name: '',
    city: '',
    description: '',
    merchant_category_code: '0000',
    is_dynamic: false,
    default_amount: null as number | null,
    min_amount: null as number | null,
    max_amount: null as number | null,
    allow_tip: false,
});

// Load merchant profile
const loadMerchantProfile = async () => {
    loadingMerchant.value = true;
    try {
        const { data } = await axios.get('/api/v1/merchant/profile');
        
        if (data.success) {
            merchant.value = data.data.merchant;
            categories.value = data.data.categories;
            
            merchantForm.value = {
                name: merchant.value.name || '',
                city: merchant.value.city || '',
                description: merchant.value.description || '',
                merchant_category_code: merchant.value.merchant_category_code || '0000',
                is_dynamic: merchant.value.is_dynamic || false,
                default_amount: merchant.value.default_amount ? parseFloat(merchant.value.default_amount) : null,
                min_amount: merchant.value.min_amount ? parseFloat(merchant.value.min_amount) : null,
                max_amount: merchant.value.max_amount ? parseFloat(merchant.value.max_amount) : null,
                allow_tip: merchant.value.allow_tip || false,
            };
        }
    } catch (error: any) {
        console.error('Failed to load merchant profile:', error);
    } finally {
        loadingMerchant.value = false;
    }
};

// Save merchant profile
const saveMerchantProfile = async () => {
    savingMerchant.value = true;
    try {
        const { data } = await axios.put('/api/v1/merchant/profile', merchantForm.value);
        
        if (data.success) {
            merchant.value = data.data;
            toast({
                title: 'Saved',
                description: 'Merchant profile updated successfully',
            });
        }
    } catch (error: any) {
        console.error('Failed to save merchant profile:', error);
        toast({
            title: 'Error',
            description: error.response?.data?.message || 'Failed to save merchant profile',
            variant: 'destructive',
        });
    } finally {
        savingMerchant.value = false;
    }
};

onMounted(() => {
    loadMerchantProfile();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                            disabled
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <!-- Merchant Profile Section -->
            <div class="flex flex-col space-y-6 border-t pt-6">
                <HeadingSmall
                    title="Merchant Profile"
                    description="Customize how your name appears when others scan your QR code"
                />

                <!-- Loading State -->
                <div v-if="loadingMerchant" class="flex items-center justify-center py-12">
                    <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                </div>

                <!-- Merchant Form -->
                <form v-else @submit.prevent="saveMerchantProfile" class="space-y-6">
                    <!-- Display Name -->
                    <div class="grid gap-2">
                        <Label for="merchant_name">Display Name *</Label>
                        <Input
                            id="merchant_name"
                            v-model="merchantForm.name"
                            placeholder="Your name or business name"
                            required
                        />
                        <p class="text-sm text-muted-foreground">
                            This is what people see when they scan your QR code
                        </p>
                    </div>

                    <!-- City -->
                    <div class="grid gap-2">
                        <Label for="merchant_city">City</Label>
                        <Input
                            id="merchant_city"
                            v-model="merchantForm.city"
                            placeholder="e.g., Manila, Cebu, Davao"
                        />
                        <p class="text-sm text-muted-foreground">
                            Optional: Displayed alongside your name
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-2">
                        <Label for="merchant_description">Description</Label>
                        <Textarea
                            id="merchant_description"
                            v-model="merchantForm.description"
                            placeholder="Brief description of your business or purpose"
                            rows="3"
                        />
                    </div>

                    <!-- Business Category -->
                    <div class="grid gap-2">
                        <Label for="merchant_category">Business Category</Label>
                        <Select v-model="merchantForm.merchant_category_code">
                            <SelectTrigger>
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, code) in categories"
                                    :key="code"
                                    :value="code"
                                >
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Amount Settings -->
                    <div class="border-t pt-4">
                        <MerchantAmountSettings
                            :is-dynamic="merchantForm.is_dynamic"
                            :default-amount="merchantForm.default_amount"
                            :min-amount="merchantForm.min_amount"
                            :max-amount="merchantForm.max_amount"
                            :allow-tip="merchantForm.allow_tip"
                            @update:isDynamic="(v) => (merchantForm.is_dynamic = v)"
                            @update:defaultAmount="(v) => (merchantForm.default_amount = v)"
                            @update:minAmount="(v) => (merchantForm.min_amount = v)"
                            @update:maxAmount="(v) => (merchantForm.max_amount = v)"
                            @update:allowTip="(v) => (merchantForm.allow_tip = v)"
                        />
                    </div>

                    <!-- Save Button -->
                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="savingMerchant">
                            <Loader2 v-if="savingMerchant" class="mr-2 h-4 w-4 animate-spin" />
                            Save Merchant Profile
                        </Button>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
