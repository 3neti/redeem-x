<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { AlertCircle } from 'lucide-vue-next';

const show = ref(false);

function open() {
    show.value = true;
}

function close() {
    show.value = false;
}

// expose the open method for parent components
defineExpose({ open });
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="close"
    >
        <div class="bg-background p-6 rounded-xl shadow-xl max-w-md w-full mx-4">
            <div class="flex items-start gap-3 mb-4">
                <AlertCircle class="h-6 w-6 text-destructive flex-shrink-0 mt-0.5" />
                <div>
                    <h2 class="text-lg font-semibold text-destructive mb-2">
                        Location Access Denied
                    </h2>
                    <p class="text-sm text-muted-foreground mb-4">
                        You need to allow location access to continue. Here's how to enable it:
                    </p>
                </div>
            </div>

            <ul class="list-disc text-sm text-foreground pl-5 space-y-2 mb-6">
                <li>Click the <strong>🔒 padlock</strong> icon near your browser's address bar</li>
                <li>Go to <strong>Site settings</strong></li>
                <li>Find <strong>Location</strong> and set it to <strong>"Allow"</strong></li>
                <li>Reload the page and try again</li>
            </ul>

            <div class="flex justify-end">
                <Button @click="close">
                    Got it
                </Button>
            </div>
        </div>
    </div>
</template>
