<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Settings, User, Building2, ChevronRight, Smartphone, Mail } from 'lucide-vue-next';

interface Props {
    user: {
        name: string;
        email: string;
        mobile: string | null;
        avatar: string | null;
    };
    merchant: {
        name: string;
        description: string | null;
    } | null;
}

const props = defineProps<Props>();
</script>

<template>
    <PwaLayout title="Settings">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    <Settings class="h-6 w-6 text-primary" />
                    <h1 class="text-lg font-semibold">Settings</h1>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 space-y-4">
            <!-- Profile Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <User class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Profile</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <User class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <div class="font-medium text-sm">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-3 space-y-2">
                        <div class="flex items-center gap-3 text-sm">
                            <Mail class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Email:</span>
                            <span class="font-medium">{{ user.email }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <Smartphone class="h-4 w-4 text-muted-foreground" />
                            <span class="text-muted-foreground">Mobile:</span>
                            <span class="font-medium">{{ user.mobile || 'Not set' }}</span>
                        </div>
                    </div>

                    <Link
                        href="/settings/profile"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors"
                    >
                        <span class="text-sm">Edit Profile</span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground" />
                    </Link>
                </CardContent>
            </Card>

            <!-- Merchant Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Merchant Information</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="merchant" class="space-y-3">
                        <div>
                            <div class="font-medium text-sm">{{ merchant.name }}</div>
                            <div v-if="merchant.description" class="text-xs text-muted-foreground mt-1">
                                {{ merchant.description }}
                            </div>
                        </div>
                    <Link
                        href="/settings/profile"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors"
                    >
                        <span class="text-sm">Edit in Desktop Settings</span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground" />
                    </Link>
                    </div>
                    <div v-else class="py-4 text-center">
                        <Building2 class="mx-auto h-8 w-8 text-muted-foreground/50" />
                        <p class="mt-2 text-sm text-muted-foreground">No merchant profile yet</p>
                        <Link
                            href="/settings/profile"
                            class="inline-flex items-center justify-center mt-3 text-sm text-primary hover:underline"
                        >
                            Setup in Desktop Settings
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Additional Settings</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1">
                    <Link
                        href="/settings/appearance"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors"
                    >
                        <span class="text-sm">Appearance</span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground" />
                    </Link>
                    <Link
                        href="/settings/api-tokens"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors"
                    >
                        <span class="text-sm">API Tokens</span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground" />
                    </Link>
                    <Link
                        href="/settings/security"
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors"
                    >
                        <span class="text-sm">Security</span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground" />
                    </Link>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
