<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';

interface Campaign {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    status: 'draft' | 'active' | 'archived';
    instructions: any;
    created_at: string;
}

interface Props {
    campaign: Campaign;
}

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head :title="`Edit Campaign: ${campaign.name}`" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        :title="`Edit: ${campaign.name}`"
                        description="Update campaign settings"
                    />
                    <Button variant="outline" as-child>
                        <a href="/settings/campaigns">Back</a>
                    </Button>
                </div>

                <div class="border rounded-lg p-6">
                    <p class="text-muted-foreground">
                        Campaign edit form coming soon...
                    </p>
                    <p class="text-sm text-muted-foreground mt-2">
                        This will include a full form builder for
                        VoucherInstructionsData (cash, inputs, validations,
                        feedback, rider, etc.)
                    </p>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
