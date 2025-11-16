<script setup lang="ts">
import { computed } from 'vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import { usePage } from '@inertiajs/vue3';
import { useSidebar } from '@/components/ui/sidebar';
import { Wallet, RefreshCw, Clock } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';

const page = usePage();
const config = computed(() => page.props.sidebar?.balance || {});
const { state } = useSidebar();

const {
    formattedBalance,
    status,
    message,
    updatedAt,
    fetchBalance,
} = useWalletBalance();

const isCollapsed = computed(() => state.value === 'collapsed');
const showRefreshButton = computed(() => config.value.show_refresh_button ?? false);
const showLastUpdated = computed(() => config.value.show_last_updated ?? false);
const showIcon = computed(() => config.value.show_icon ?? true);
const label = computed(() => config.value.label ?? 'Wallet Balance');
const style = computed(() => config.value.style ?? 'compact');

const isLoading = computed(() => status.value === 'loading');
const hasError = computed(() => status.value === 'error');

const lastCheckedFormatted = computed(() => {
    if (!updatedAt.value) return '';
    
    const date = new Date(updatedAt.value);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    
    return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
});

const handleRefresh = async () => {
    await fetchBalance();
};
</script>

<template>
    <div class="px-3 py-2">
        <!-- Collapsed State -->
        <div v-if="isCollapsed" class="flex items-center justify-center">
            <Wallet v-if="showIcon" class="h-5 w-5 text-muted-foreground" />
        </div>

        <!-- Expanded State -->
        <div v-else class="space-y-2">
            <!-- Compact Style -->
            <div v-if="style === 'compact'" class="space-y-1">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Wallet v-if="showIcon" class="h-4 w-4 text-muted-foreground" />
                        <span class="text-xs font-medium text-muted-foreground">
                            {{ label }}
                        </span>
                    </div>
                    <Button
                        v-if="showRefreshButton"
                        variant="ghost"
                        size="icon"
                        class="h-6 w-6"
                        @click="handleRefresh"
                        :disabled="isLoading"
                    >
                        <RefreshCw
                            :class="{ 'animate-spin': isLoading }"
                            class="h-3 w-3"
                        />
                    </Button>
                </div>

                <!-- Balance Display -->
                <div v-if="isLoading" class="space-y-1">
                    <Skeleton class="h-6 w-24" />
                </div>
                <div v-else-if="hasError" class="text-xs text-destructive">
                    {{ message || 'Failed to load' }}
                </div>
                <div v-else class="text-lg font-semibold">
                    {{ formattedBalance }}
                </div>

                <!-- Last Updated -->
                <div
                    v-if="showLastUpdated && !isLoading && !hasError"
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Clock class="h-3 w-3" />
                    <span>{{ lastCheckedFormatted }}</span>
                </div>
            </div>

            <!-- Full Style -->
            <div
                v-else
                class="rounded-lg border bg-card p-3 text-card-foreground shadow-sm"
            >
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Wallet v-if="showIcon" class="h-4 w-4" />
                            <span class="text-sm font-medium">{{ label }}</span>
                        </div>
                        <Button
                            v-if="showRefreshButton"
                            variant="ghost"
                            size="icon"
                            class="h-6 w-6"
                            @click="handleRefresh"
                            :disabled="isLoading"
                        >
                            <RefreshCw
                                :class="{ 'animate-spin': isLoading }"
                                class="h-3 w-3"
                            />
                        </Button>
                    </div>

                    <!-- Balance Display -->
                    <div v-if="isLoading" class="space-y-1">
                        <Skeleton class="h-7 w-28" />
                    </div>
                    <div v-else-if="hasError" class="text-sm text-destructive">
                        {{ message || 'Failed to load balance' }}
                    </div>
                    <div v-else class="text-2xl font-bold">
                        {{ formattedBalance }}
                    </div>

                    <!-- Last Updated -->
                    <div
                        v-if="showLastUpdated && !isLoading && !hasError"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Clock class="h-3 w-3" />
                        <span>Updated {{ lastCheckedFormatted }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
