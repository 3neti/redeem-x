<script setup lang="ts">
import { WifiOff, RefreshCw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const retry = () => {
    window.location.reload();
};
</script>

<template>
    <div class="min-h-screen bg-background flex items-center justify-center p-4">
        <div class="max-w-md w-full text-center space-y-6">
            <div class="flex justify-center">
                <div class="rounded-full bg-muted p-6">
                    <WifiOff class="h-12 w-12 text-muted-foreground" />
                </div>
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-bold">You're Offline</h1>
                <p class="text-muted-foreground">
                    It looks like you've lost your internet connection. Please check your network and try again.
                </p>
            </div>

            <div class="space-y-3">
                <Button @click="retry" class="w-full">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Try Again
                </Button>

                <p class="text-sm text-muted-foreground">
                    Some features may still be available offline with cached data.
                </p>
            </div>
        </div>
    </div>
</template>
