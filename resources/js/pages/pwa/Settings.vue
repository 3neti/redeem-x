<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PwaLayout from '@/layouts/PwaLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Settings, User, Building2, ChevronRight, Smartphone, Mail, Palette } from 'lucide-vue-next';
import { usePhoneFormat } from '@/composables/usePhoneFormat';
import { useTheme } from '@/composables/useTheme';

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
        display_name: string | null;
    } | null;
    vendorAlias: {
        alias: string;
        status: string;
        assigned_at: string;
    } | null;
}

const props = defineProps<Props>();
const { formatForDisplay } = usePhoneFormat();
const { currentTheme, setTheme, availableThemes } = useTheme();

const formattedMobile = props.user.mobile ? formatForDisplay(props.user.mobile) : 'Not set';
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
            <!-- Vendor Alias Card (Prominent) -->
            <Card v-if="vendorAlias" class="bg-gradient-to-br from-primary/5 to-primary/10 border-primary/20">
                <CardContent class="pt-6">
                    <div class="text-center space-y-3">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/20 mb-2">
                            <Building2 class="h-8 w-8 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide mb-1">Your Vendor Alias</p>
                            <p class="text-3xl font-bold text-primary tracking-tight">{{ vendorAlias.alias }}</p>
                            <p class="text-xs text-muted-foreground mt-1">Use this alias for quick payments and voucher generation</p>
                        </div>
                        <div v-if="vendorAlias.status === 'active'" class="inline-flex items-center gap-1 text-xs text-green-600 bg-green-50 px-3 py-1 rounded-full">
                            <div class="w-1.5 h-1.5 rounded-full bg-green-600"></div>
                            Active
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Profile Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <User class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Profile</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- User Info -->
                    <div class="flex items-center gap-3">
                        <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center">
                            <User class="h-6 w-6 text-primary" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-base truncate">{{ user.name }}</div>
                            <div v-if="merchant?.display_name" class="text-xs text-muted-foreground truncate">
                                {{ merchant.display_name }}
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div class="border-t pt-3 space-y-3">
                        <div class="flex items-start gap-3">
                            <Mail class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-muted-foreground">Email</div>
                                <div class="text-sm font-medium truncate">{{ user.email }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Smartphone class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1">
                                <div class="text-xs text-muted-foreground">Mobile</div>
                                <div class="text-sm font-medium">{{ formattedMobile }}</div>
                            </div>
                        </div>
                    </div>
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
                    </div>
                    <div v-else class="py-4 text-center">
                        <Building2 class="mx-auto h-8 w-8 text-muted-foreground/50" />
                        <p class="mt-2 text-sm text-muted-foreground">No merchant profile yet</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Theme -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Palette class="h-5 w-5 text-primary" />
                        <CardTitle class="text-base">Theme</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="theme in availableThemes"
                            :key="theme.id"
                            @click="setTheme(theme.id)"
                            :class="[
                                'relative rounded-lg border-2 p-3 text-left transition-all',
                                currentTheme === theme.id
                                    ? 'border-primary ring-1 ring-primary/20'
                                    : 'border-border hover:border-primary/40',
                            ]"
                        >
                            <div class="mb-2 flex h-8 gap-1 rounded overflow-hidden">
                                <div :class="[theme.preview.bg, 'flex-1']" />
                                <div :class="[theme.preview.accent, 'w-3']" />
                            </div>
                            <div class="font-medium text-sm">{{ theme.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ theme.description }}</div>
                            <div
                                v-if="currentTheme === theme.id"
                                class="absolute top-1.5 right-1.5 h-4 w-4 rounded-full bg-primary flex items-center justify-center"
                            >
                                <span class="text-primary-foreground text-[10px]">✓</span>
                            </div>
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Additional Settings</CardTitle>
                </CardHeader>
                <CardContent class="py-4">
                    <p class="text-sm text-muted-foreground text-center">
                        For advanced settings, please use the desktop version
                    </p>
                </CardContent>
            </Card>
        </div>
    </PwaLayout>
</template>
