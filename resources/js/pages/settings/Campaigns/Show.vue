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
    instructions: any;
    created_at: string;
}

interface Props {
    campaign: Campaign;
    stats: {
        total_vouchers: number;
        redeemed: number;
        pending: number;
    };
}

defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head :title="`Campaign: ${campaign.name}`" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        :title="campaign.name"
                        :description="campaign.description || 'Campaign details'"
                    />
                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <a :href="`/settings/campaigns/${campaign.id}/edit`"
                                >Edit</a
                            >
                        </Button>
                        <Button variant="outline" as-child>
                            <a href="/settings/campaigns">Back</a>
                        </Button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="border rounded-lg p-6 space-y-4">
                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">
                                Status
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

                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground">
                                Statistics
                            </h3>
                            <div class="grid grid-cols-3 gap-4 mt-2">
                                <div>
                                    <p class="text-2xl font-bold">
                                        {{ stats.total_vouchers }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Total Vouchers
                                    </p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">
                                        {{ stats.redeemed }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Redeemed
                                    </p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">
                                        {{ stats.pending }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Pending
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-muted-foreground mb-2">
                                Campaign Instructions
                            </h3>
                            <pre class="text-xs bg-muted p-4 rounded overflow-auto">{{ JSON.stringify(campaign.instructions, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
