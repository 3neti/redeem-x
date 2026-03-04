<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import PwaBottomNav from '@/components/pwa/PwaBottomNav.vue';
import { Toaster } from '@/components/ui/toast';
import { usePwa } from '@/composables/pwa/usePwa';
import { WifiOff } from 'lucide-vue-next';

interface Props {
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Redeem-X',
});

const { isOnline } = usePwa();

const pageTitle = computed(() => {
    return props.title ? `${props.title} - Redeem-X` : 'Redeem-X';
});

// Register service worker
onMounted(() => {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/pwa/sw.js').catch((error) => {
            console.error('Service Worker registration failed:', error);
        });
    }
});
</script>

<template>
    <div class="min-h-screen bg-background pb-20">
        <Head>
            <title>{{ pageTitle }}</title>
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
            <meta name="theme-color" content="#1e40af" />
            <link rel="manifest" href="/pwa/manifest.webmanifest" />
            <link rel="apple-touch-icon" href="/pwa/icons/icon-192x192.png" />
        </Head>

        <!-- Offline Banner -->
        <div
            v-if="!isOnline"
            class="fixed top-0 left-0 right-0 z-50 bg-destructive text-destructive-foreground px-4 py-2 text-sm flex items-center justify-center gap-2"
        >
            <WifiOff class="h-4 w-4" />
            <span>You are offline. Some features may be limited.</span>
        </div>

        <!-- Main Content -->
        <main class="min-h-screen" :class="{ 'pt-10': !isOnline }">
            <slot />
        </main>

        <!-- Bottom Navigation -->
        <PwaBottomNav />
        
        <!-- Toast Notifications -->
        <Toaster />
    </div>
</template>
