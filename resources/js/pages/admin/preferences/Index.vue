<script setup lang="ts">
import PreferencesController from '@/actions/App/Http/Controllers/Admin/PreferencesController';
import { Form, Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    preferences: {
        default_amount: number;
        default_expiry_days: number | null;
        default_rider_url: string;
        default_success_message: string;
        default_redemption_endpoint: string;
        default_settlement_endpoint: string;
    };
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/admin/billing',
    },
    {
        title: 'Preferences',
        href: '/admin/preferences',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Admin - Preferences" />

        <div class="px-4 py-6">
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Voucher Defaults"
                    description="Configure default settings for new vouchers"
                />

                <Form
                    v-bind="PreferencesController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="default_amount">Default Amount (PHP)</Label>
                        <Input
                            id="default_amount"
                            type="number"
                            class="mt-1 block w-full"
                            name="default_amount"
                            :default-value="preferences.default_amount"
                            required
                            min="1"
                            max="100000"
                            step="0.01"
                            placeholder="50.00"
                        />
                        <p class="text-sm text-muted-foreground">
                            Default voucher amount when generating new vouchers
                        </p>
                        <InputError class="mt-2" :message="errors.default_amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="default_expiry_days">Default Expiry (Days)</Label>
                        <Input
                            id="default_expiry_days"
                            type="number"
                            class="mt-1 block w-full"
                            name="default_expiry_days"
                            :default-value="preferences.default_expiry_days"
                            min="1"
                            max="365"
                            placeholder="Leave empty for no expiry"
                        />
                        <p class="text-sm text-muted-foreground">
                            Number of days until voucher expires (leave empty for no expiry)
                        </p>
                        <InputError class="mt-2" :message="errors.default_expiry_days" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="default_rider_url">Default Rider URL</Label>
                        <Input
                            id="default_rider_url"
                            type="url"
                            class="mt-1 block w-full"
                            name="default_rider_url"
                            :default-value="preferences.default_rider_url"
                            placeholder="https://example.com"
                        />
                        <p class="text-sm text-muted-foreground">
                            URL to redirect users after successful redemption
                        </p>
                        <InputError class="mt-2" :message="errors.default_rider_url" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="default_success_message">Default Success Message</Label>
                        <Textarea
                            id="default_success_message"
                            class="mt-1 block w-full"
                            name="default_success_message"
                            :default-value="preferences.default_success_message"
                            rows="4"
                            placeholder="Thank you for redeeming your voucher!"
                        />
                        <p class="text-sm text-muted-foreground">
                            Message shown to users after successful redemption
                        </p>
                        <InputError class="mt-2" :message="errors.default_success_message" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="default_redemption_endpoint">Redemption Endpoint</Label>
                        <Input
                            id="default_redemption_endpoint"
                            type="text"
                            class="mt-1 block w-full"
                            name="default_redemption_endpoint"
                            :default-value="preferences.default_redemption_endpoint"
                            required
                            pattern="^\/[a-z-]+$"
                            placeholder="/disburse"
                        />
                        <p class="text-sm text-muted-foreground">
                            Path where users redeem vouchers (e.g., /disburse, /redeem). Must start with / and contain only lowercase letters and hyphens.
                        </p>
                        <InputError class="mt-2" :message="errors.default_redemption_endpoint" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="default_settlement_endpoint">Settlement Endpoint</Label>
                        <Input
                            id="default_settlement_endpoint"
                            type="text"
                            class="mt-1 block w-full"
                            name="default_settlement_endpoint"
                            :default-value="preferences.default_settlement_endpoint"
                            required
                            pattern="^\/[a-z-]+$"
                            placeholder="/pay"
                        />
                        <p class="text-sm text-muted-foreground">
                            Path where users settle/pay vouchers (e.g., /pay, /settle). Must start with / and contain only lowercase letters and hyphens.
                        </p>
                        <InputError class="mt-2" :message="errors.default_settlement_endpoint" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            type="submit"
                        >
                            Save Preferences
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful || status"
                                class="text-sm text-green-600"
                            >
                                {{ status || 'Saved.' }}
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
