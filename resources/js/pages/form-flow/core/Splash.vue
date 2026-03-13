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
    is_default_splash?: boolean;
    voucher_code?: string;
    app_name?: string;
    app_logo?: string;
    app_author?: string;
    copyright_text?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: undefined,
    timeout: 5,
    button_label: 'Continue Now',
    is_default_splash: false,
    voucher_code: undefined,
    app_name: undefined,
    app_logo: undefined,
    app_author: undefined,
    copyright_text: undefined,
});

// Coerce timeout to number (env values may arrive as strings)
const timeoutSeconds = computed(() => {
    const val = Number(props.timeout);
    return isNaN(val) ? 5 : val;
});

const remainingSeconds = ref(timeoutSeconds.value);
const submitting = ref(false);
let intervalId: ReturnType<typeof setInterval> | null = null;

// Detect disburse flow from voucher_code presence
const isDisburseFlow = computed(() => !!props.voucher_code);

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
    if (timeoutSeconds.value === 0) return 0;
    return ((timeoutSeconds.value - remainingSeconds.value) / timeoutSeconds.value) * 100;
});

// Start countdown
onMounted(() => {
    if (timeoutSeconds.value > 0) {
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

    <!-- ============================================================ -->
    <!-- Default Splash: full-screen, logo-centric launch screen      -->
    <!-- ============================================================ -->
    <div
        v-if="is_default_splash"
        class="default-splash min-h-screen relative flex flex-col items-center justify-center bg-gradient-to-b from-amber-50/80 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-6 select-none"
    >
        <!-- Hero logo -->
        <img
            v-if="app_logo"
            :src="app_logo"
            :alt="app_name ?? 'Logo'"
            class="w-36 h-36 sm:w-40 sm:h-40 object-contain drop-shadow-lg mb-6 animate-fade-in"
        />

        <!-- App name -->
        <p class="text-lg sm:text-xl font-medium tracking-wide text-gray-400 dark:text-gray-500 mb-10 animate-fade-in-delay">
            {{ app_name }}
        </p>

        <!-- Voucher code badge (Pay Code Framing Convention: || CODE ||) -->
        <div v-if="voucher_code" class="text-center mb-14 animate-fade-in-delay">
            <p class="text-[11px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600 mb-2">
                Redeeming
            </p>
            <span class="inline-flex items-center gap-2 px-5 py-1.5 text-lg sm:text-xl font-mono font-semibold tracking-widest text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200/80 dark:border-amber-700/30 rounded-full">
                <span class="text-amber-400 dark:text-amber-600" aria-hidden="true">||</span>
                {{ voucher_code }}
                <span class="text-amber-400 dark:text-amber-600" aria-hidden="true">||</span>
            </span>
        </div>

        <!-- Continue button + progress -->
        <div class="w-full max-w-xs space-y-3">
            <button
                @click="handleContinue"
                :disabled="submitting"
                class="inline-flex items-center justify-center w-full h-10 px-6 rounded-full text-sm font-medium transition-all bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 text-white shadow-lg shadow-amber-600/20 dark:shadow-amber-500/10 disabled:pointer-events-none disabled:opacity-50"
            >
                <span v-if="submitting">Please wait…</span>
                <span v-else>{{ button_label }}</span>
            </button>

            <div v-if="timeoutSeconds > 0" class="space-y-1">
                <div class="w-full bg-gray-200/60 dark:bg-gray-800 rounded-full h-1 overflow-hidden">
                    <div
                        class="h-full rounded-full bg-amber-400/70 dark:bg-amber-500/50 transition-all duration-1000 ease-linear"
                        :style="{ width: `${progressPercentage}%` }"
                    />
                </div>
                <p class="text-center text-[11px] text-gray-400 dark:text-gray-600">
                    {{ remainingSeconds }}s
                </p>
            </div>
        </div>

        <!-- Footer -->
        <footer class="absolute bottom-5 inset-x-0 text-center space-y-0.5">
            <p v-if="app_author" class="text-[10px] text-gray-300 dark:text-gray-700">
                {{ app_author }}
            </p>
            <p v-if="copyright_text" class="text-[10px] text-gray-300 dark:text-gray-700">
                {{ copyright_text }}
            </p>
        </footer>
    </div>

    <!-- ============================================================ -->
    <!-- Custom Splash: Card-based layout for rider->splash content   -->
    <!-- ============================================================ -->
    <div
        v-else
        :class="isDisburseFlow
            ? 'min-h-screen flex items-center justify-center bg-gradient-to-b from-amber-50/80 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 p-4'
            : 'min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 p-4'"
    >
        <Card :class="isDisburseFlow ? 'w-full max-w-2xl overflow-visible border-0 shadow-sm bg-white/80 dark:bg-gray-900/80' : 'w-full max-w-2xl overflow-visible'">
            <CardHeader v-if="title">
                <CardTitle class="text-center text-2xl">{{ title }}</CardTitle>
            </CardHeader>

            <CardContent class="space-y-6 overflow-visible">
                <!-- Rendered content -->
                <div
                    v-if="contentType !== 'text'"
                    v-html="renderedContent"
                    class="prose prose-base max-w-none dark:prose-invert text-center overflow-visible"
                />
                <div
                    v-else
                    class="text-center text-lg text-gray-700 dark:text-gray-300 whitespace-pre-wrap"
                >
                    {{ content }}
                </div>

                <!-- Countdown progress -->
                <div v-if="timeoutSeconds > 0" class="space-y-2">
                    <div :class="isDisburseFlow
                        ? 'w-full bg-gray-200/60 dark:bg-gray-800 rounded-full h-1 overflow-hidden'
                        : 'w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden'"
                    >
                        <div
                            :class="isDisburseFlow
                                ? 'h-full rounded-full bg-amber-400/70 dark:bg-amber-500/50 transition-all duration-1000 ease-linear'
                                : 'bg-primary h-full transition-all duration-1000 ease-linear'"
                            :style="{ width: `${progressPercentage}%` }"
                        />
                    </div>
                    <p :class="isDisburseFlow
                        ? 'text-center text-[11px] text-gray-400 dark:text-gray-600'
                        : 'text-center text-sm text-gray-500 dark:text-gray-400'"
                    >
                        <template v-if="isDisburseFlow">{{ remainingSeconds }}s</template>
                        <template v-else>Continuing in {{ remainingSeconds }} second{{ remainingSeconds !== 1 ? 's' : '' }}…</template>
                    </p>
                </div>

                <!-- Continue button -->
                <div class="flex justify-center">
                    <button
                        v-if="isDisburseFlow"
                        @click="handleContinue"
                        :disabled="submitting"
                        class="inline-flex items-center justify-center w-full h-10 px-6 rounded-full text-sm font-medium transition-all bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 text-white shadow-lg shadow-amber-600/20 dark:shadow-amber-500/10 disabled:pointer-events-none disabled:opacity-50"
                    >
                        <span v-if="submitting">Please wait…</span>
                        <span v-else>{{ button_label }}</span>
                    </button>
                    <Button
                        v-else
                        @click="handleContinue"
                        :disabled="submitting"
                        size="lg"
                        class="min-w-[200px]"
                    >
                        <span v-if="submitting">Please wait…</span>
                        <span v-else>{{ button_label }}</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Default splash entrance animations */
@keyframes fade-in {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fade-in 0.6s ease-out both;
}

.animate-fade-in-delay {
    animation: fade-in 0.6s ease-out 0.15s both;
}

/* Prose overrides for custom splash content */
.prose :deep(img) {
    max-width: 100% !important;
    height: auto !important;
    display: block;
}

.prose {
    overflow: visible !important;
}

.prose :deep(*) {
    max-width: 100%;
}

:deep(.card) {
    overflow: visible !important;
}

:deep([class*="card"]) {
    overflow: visible !important;
}
</style>
