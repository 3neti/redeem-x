<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { update, toggleFeature } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { useForm, Head, usePage, router } from '@inertiajs/vue3';
import axios from '@/lib/axios';

import BankAccountsSection from '@/components/settings/BankAccountsSection.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import MerchantAmountSettings from '@/components/MerchantAmountSettings.vue';
import MerchantNameTemplateComposer from '@/components/MerchantNameTemplateComposer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/components/ui/toast/use-toast';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Loader2, Lock, Unlock, AlertCircle } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Feature {
    key: string;
    name: string;
    description: string;
    locked: boolean;
    active: boolean;
}

interface Props {
    status?: string;
    available_features?: Feature[];
    reason?: string;
    return_to?: string;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

const { toast } = useToast();

// Check if user was redirected here for onboarding
const isMobileRequired = computed(() => props.reason === 'mobile_required');
const mobileInputRef = ref<HTMLInputElement | null>(null);

// Profile form
const profileForm = useForm({
    name: user.name,
    email: user.email,
    mobile: user.mobile,
    webhook: user.webhook,
});

const saveProfile = () => {
    // Build URL with return_to parameter if it exists
    let url = update.url();
    if (props.return_to) {
        url += `?return_to=${encodeURIComponent(props.return_to)}`;
    }
    
    profileForm.patch(url, {
        preserveScroll: true,
        onSuccess: () => {
            toast({
                title: 'Saved',
                description: 'Profile updated successfully',
            });
            
            // Backend will handle redirect via return_to parameter
        },
    });
};

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
    merchant_name_template: '{name} - {city}',
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
                merchant_name_template: merchant.value.merchant_name_template || '{name} - {city}',
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

// Feature flags
const isSuperAdmin = computed(() => {
    const roles = page.props.auth?.roles || [];
    return roles.includes('super-admin');
});

const togglingFeature = ref<string | null>(null);

const toggleFeatureFlag = async (feature: Feature) => {
    if (feature.locked) {
        toast({
            title: 'Feature Locked',
            description: 'This feature is role-based and cannot be manually toggled.',
            variant: 'destructive',
        });
        return;
    }
    
    togglingFeature.value = feature.key;
    
    router.post(
        toggleFeature.url(),
        {
            feature: feature.key,
            enabled: !feature.active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Feature Updated',
                    description: `${feature.name} ${!feature.active ? 'enabled' : 'disabled'} successfully.`,
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: errors.feature || 'Failed to toggle feature.',
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                togglingFeature.value = null;
            },
        }
    );
};

onMounted(() => {
    loadMerchantProfile();
    
    // Auto-focus mobile field if redirected for onboarding
    if (isMobileRequired.value && !profileForm.mobile) {
        // Use nextTick to ensure DOM is ready
        setTimeout(() => {
            const mobileInput = document.getElementById('mobile') as HTMLInputElement;
            if (mobileInput) {
                mobileInput.focus();
                mobileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <!-- Onboarding Alert -->
                <Alert v-if="isMobileRequired" variant="destructive" class="border-red-500">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>Mobile Number Required</AlertTitle>
                    <AlertDescription>
                        Please add your mobile number to continue. It's required for generating QR codes and receiving payments via InstaPay.
                    </AlertDescription>
                </Alert>
                
                <HeadingSmall
                    title="Profile information"
                    description="Update your name and email address"
                />

                <!-- Vendor Alias Badge (if assigned) -->
                <div v-if="user.primary_vendor_alias" class="rounded-lg border bg-muted/50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Vendor Alias</p>
                            <p class="text-2xl font-bold font-mono tracking-wider">{{ user.primary_vendor_alias.alias }}</p>
                            <p class="text-xs text-muted-foreground mt-1">
                                Assigned {{ new Date(user.primary_vendor_alias.assigned_at).toLocaleDateString() }}
                            </p>
                        </div>
                        <Badge variant="default">B2B Merchant</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground mt-3">
                        You can redeem vouchers restricted to this alias. Contact admin to update.
                    </p>
                </div>

                <form @submit.prevent="saveProfile" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            v-model="profileForm.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="profileForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            v-model="profileForm.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                            disabled
                        />
                        <InputError class="mt-2" :message="profileForm.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mobile" :class="{ 'text-red-600': isMobileRequired }">
                            Mobile Number *
                            <span v-if="isMobileRequired" class="text-red-600 font-semibold"> (Required to continue)</span>
                        </Label>
                        <Input
                            id="mobile"
                            ref="mobileInputRef"
                            type="tel"
                            :class="[
                                'mt-1 block w-full',
                                isMobileRequired && !profileForm.mobile ? 'border-red-500 ring-red-500 focus-visible:ring-red-500' : ''
                            ]"
                            name="mobile"
                            v-model="profileForm.mobile"
                            required
                            autocomplete="tel"
                            placeholder="09171234567"
                        />
                        <p class="text-sm" :class="isMobileRequired ? 'text-red-600 font-medium' : 'text-muted-foreground'">
                            Philippine mobile number (required for QR code generation)
                        </p>
                        <InputError class="mt-2" :message="profileForm.errors.mobile" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="webhook">Webhook URL</Label>
                        <Input
                            id="webhook"
                            type="url"
                            class="mt-1 block w-full"
                            name="webhook"
                            v-model="profileForm.webhook"
                            autocomplete="url"
                            placeholder="https://example.com/webhook"
                        />
                        <p class="text-sm text-muted-foreground">
                            Optional: Receive notifications when your QR code is scanned
                        </p>
                        <InputError class="mt-2" :message="profileForm.errors.webhook" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            type="submit"
                            :disabled="profileForm.processing"
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
                                v-show="profileForm.recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </form>
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

                    <!-- Template Composer -->
                    <div class="border-t pt-4">
                        <MerchantNameTemplateComposer
                            v-model="merchantForm.merchant_name_template"
                            :merchant-name="merchantForm.name || 'Sample Merchant'"
                            :merchant-city="merchantForm.city || 'Manila'"
                            :app-name="$page.props.appName || 'redeem-x'"
                            @preview="saveMerchantProfile"
                        />
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

            <!-- Bank Accounts Section -->
            <div class="flex flex-col space-y-6 border-t pt-6">
                <BankAccountsSection />
            </div>

            <!-- Feature Flags Section (Super Admin Only) -->
            <div v-if="isSuperAdmin && available_features && available_features.length > 0" class="flex flex-col space-y-6 border-t pt-6">
                <HeadingSmall
                    title="Feature Flags"
                    description="Enable or disable experimental features for your account"
                />

                <div class="space-y-4">
                    <div
                        v-for="feature in available_features"
                        :key="feature.key"
                        class="flex items-start justify-between rounded-lg border p-4 hover:bg-muted/50 transition-colors"
                    >
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium">{{ feature.name }}</h4>
                                <Badge v-if="feature.locked" variant="secondary" class="text-xs">
                                    <Lock class="mr-1 h-3 w-3" />
                                    Role-based
                                </Badge>
                                <Badge v-else variant="outline" class="text-xs">
                                    <Unlock class="mr-1 h-3 w-3" />
                                    Toggleable
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ feature.description }}
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-2 ml-4">
                            <Switch
                                :id="feature.key"
                                :checked="feature.active"
                                :disabled="feature.locked || togglingFeature === feature.key"
                                @update:checked="() => toggleFeatureFlag(feature)"
                            />
                            <Loader2
                                v-if="togglingFeature === feature.key"
                                class="h-4 w-4 animate-spin text-muted-foreground"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-muted p-4">
                    <p class="text-sm text-muted-foreground">
                        <strong>Note:</strong> Role-based features are automatically enabled based on your permissions and cannot be manually toggled. Changes to feature flags apply immediately.
                    </p>
                </div>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
