<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface Campaign {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    status: 'draft' | 'active' | 'archived';
    created_at: string;
}

interface Props {
    campaigns: Campaign[];
    filters?: {
        search?: string;
        status?: string;
    };
}

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head title="Campaigns" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="Campaigns"
                        description="Manage voucher campaign templates"
                    />
                    <Button as-child>
                        <a href="/settings/campaigns/create">New Campaign</a>
                    </Button>
                </div>

                <div class="space-y-4">
                    <div
                        v-if="campaigns.length === 0"
                        class="text-center py-12 text-muted-foreground"
                    >
                        No campaigns yet. Create your first campaign to get
                        started.
                    </div>

                    <div
                        v-for="campaign in campaigns"
                        :key="campaign.id"
                        class="border rounded-lg p-4 hover:bg-accent/50 transition-colors"
                    >
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-medium">
                                        {{ campaign.name }}
                                    </h3>
                                    <Badge
                                        :variant="
                                            campaign.status === 'active'
                                                ? 'default'
                                                : campaign.status === 'draft'
                                                  ? 'secondary'
                                                  : 'outline'
                                        "
                                    >
                                        {{ campaign.status }}
                                    </Badge>
                                </div>
                                <p
                                    v-if="campaign.description"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ campaign.description }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="ghost" size="sm" as-child>
                                    <a :href="`/settings/campaigns/${campaign.id}`"
                                        >View</a
                                    >
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <a
                                        :href="`/settings/campaigns/${campaign.id}/edit`"
                                        >Edit</a
                                    >
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
