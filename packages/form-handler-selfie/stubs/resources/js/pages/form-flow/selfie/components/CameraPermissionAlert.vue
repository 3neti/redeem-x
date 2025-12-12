<script setup lang="ts">
import { ref } from 'vue';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

const isOpen = ref(false);

function open() {
    isOpen.value = true;
}

function close() {
    isOpen.value = false;
}

defineExpose({ open, close });
</script>

<template>
    <AlertDialog v-model:open="isOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Camera Access Required</AlertDialogTitle>
                <AlertDialogDescription class="space-y-4">
                    <p>This feature requires access to your camera to capture a selfie.</p>
                    
                    <div class="text-sm">
                        <p class="font-semibold mb-2">To enable camera access:</p>
                        <ol class="list-decimal list-inside space-y-1 ml-2">
                            <li>Click the camera icon in your browser's address bar</li>
                            <li>Select "Always allow camera access"</li>
                            <li>Refresh this page</li>
                        </ol>
                    </div>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="flex justify-end">
                <Button @click="close">Close</Button>
            </div>
        </AlertDialogContent>
    </AlertDialog>
</template>
