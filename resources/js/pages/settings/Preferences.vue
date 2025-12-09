<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Preferences',
        href: '/settings/preferences',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Preferences" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Preferences"
                    description="Manage your notification and application preferences"
                />
                <div class="rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                        Preferences settings coming soon.
                    </p>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
