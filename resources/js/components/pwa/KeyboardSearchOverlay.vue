<script setup lang="ts">
import { Teleport, Transition } from 'vue';

interface Props {
    query: string;
    matchCount: number;
    show: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: []
}>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-background/80 backdrop-blur-sm"
                @click="emit('close')"
            >
                <div class="text-center space-y-4 p-8">
                    <!-- Search Query Display -->
                    <div class="font-mono text-6xl font-bold tracking-wider text-foreground">
                        {{ query }}<span class="animate-pulse">_</span>
                    </div>
                    
                    <!-- Match Counter -->
                    <div class="text-lg text-muted-foreground">
                        {{ matchCount }} match{{ matchCount !== 1 ? 'es' : '' }}
                    </div>
                    
                    <!-- ESC Hint -->
                    <div class="text-sm text-muted-foreground">
                        Press <kbd class="px-2 py-1 text-xs font-semibold border rounded bg-muted">ESC</kbd> to clear
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
