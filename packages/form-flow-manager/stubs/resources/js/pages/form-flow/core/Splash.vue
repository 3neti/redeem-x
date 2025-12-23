<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

interface Props {
    flow_id: string;
    step_index: number;
    title?: string;
    content: string;
    timeout?: number;
    button_label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: undefined,
    timeout: 5,
    button_label: 'Continue Now',
});

const remainingSeconds = ref(props.timeout);
const submitting = ref(false);
let intervalId: ReturnType<typeof setInterval> | null = null;

// Detect content type and render appropriately
const contentType = computed(() => {
    const trimmed = props.content.trim();
    
    // SVG detection
    if (trimmed.startsWith('<svg')) {
        return 'svg';
    }
    
    // HTML detection
    if (/<[a-z][\s\S]*>/i.test(trimmed)) {
        return 'html';
    }
    
    // URL detection
    if (trimmed.match(/^https?:\/\//i)) {
        return 'url';
    }
    
    // Markdown detection (has # headers or ** bold or * list)
    if (trimmed.match(/^#+\s|^\*\*|\*\s/m)) {
        return 'markdown';
    }
    
    // Fallback to plain text
    return 'text';
});

const renderedContent = computed(() => {
    switch (contentType.value) {
        case 'markdown':
            // Parse markdown to HTML and sanitize
            return DOMPurify.sanitize(marked.parse(props.content) as string);
        
        case 'html':
        case 'svg':
            // Sanitize HTML/SVG
            return DOMPurify.sanitize(props.content);
        
        case 'url':
            // Embed as iframe
            return `<iframe src="${props.content}" class="w-full h-96 border-0" />`;
        
        case 'text':
        default:
            // Plain text with preserved line breaks
            return props.content.replace(/\n/g, '<br>');
    }
});

// Progress percentage (0-100)
const progressPercentage = computed(() => {
    if (props.timeout === 0) return 0;
    return ((props.timeout - remainingSeconds.value) / props.timeout) * 100;
});

// Start countdown
onMounted(() => {
    if (props.timeout > 0) {
        intervalId = setInterval(() => {
            remainingSeconds.value -= 1;
            
            if (remainingSeconds.value <= 0) {
                // Auto-submit when countdown reaches 0
                handleContinue();
            }
        }, 1000);
    }
});

// Cleanup interval on unmount
onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});

// Submit to next step
async function handleContinue() {
    if (submitting.value) return;
    
    submitting.value = true;
    
    // Clear countdown
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
    
    router.post(
        `/form-flow/${props.flow_id}/step/${props.step_index}`,
        {
            data: { confirmed: true },
        },
        {
            preserveState: false,
            preserveScroll: false,
            onFinish: () => {
                submitting.value = false;
            },
        }
    );
}
</script>

<template>
    <Head :title="title || 'Welcome'" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 p-4">
        <Card class="w-full max-w-2xl">
            <CardHeader v-if="title">
                <CardTitle class="text-center text-2xl">{{ title }}</CardTitle>
            </CardHeader>
            
            <CardContent class="space-y-6">
                <!-- Rendered content -->
                <div 
                    v-if="contentType !== 'text'"
                    v-html="renderedContent"
                    class="prose prose-base max-w-none dark:prose-invert"
                />
                <div 
                    v-else
                    class="text-center text-lg text-gray-700 dark:text-gray-300 whitespace-pre-wrap"
                >
                    {{ content }}
                </div>
                
                <!-- Countdown progress (only if timeout > 0) -->
                <div v-if="timeout > 0" class="space-y-2">
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div 
                            class="bg-primary h-full transition-all duration-1000 ease-linear"
                            :style="{ width: `${progressPercentage}%` }"
                        />
                    </div>
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        Continuing in {{ remainingSeconds }} second{{ remainingSeconds !== 1 ? 's' : '' }}...
                    </p>
                </div>
                
                <!-- Continue button -->
                <div class="flex justify-center">
                    <Button 
                        @click="handleContinue"
                        :disabled="submitting"
                        size="lg"
                        class="min-w-[200px]"
                    >
                        <span v-if="submitting">Please wait...</span>
                        <span v-else>{{ button_label }}</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
