<script setup lang="ts">
import { ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-vue-next';

const isVisible = ref(false);

function open() {
    isVisible.value = true;
}

function close() {
    isVisible.value = false;
}

defineExpose({ open, close });
</script>

<template>
    <Alert v-if="isVisible" variant="destructive" class="mb-6">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>Camera Access Required</AlertTitle>
        <AlertDescription class="space-y-3">
            <p>This feature requires access to your camera to capture a selfie.</p>
            
            <div class="text-sm">
                <p class="font-semibold mb-1">To enable camera access:</p>
                <ol class="list-decimal list-inside space-y-1 ml-2">
                    <li>Click the camera icon in your browser's address bar</li>
                    <li>Select "Always allow camera access"</li>
                    <li>Refresh this page</li>
                </ol>
            </div>
        </AlertDescription>
    </Alert>
</template>
