<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { CheckCircle2, ExternalLink } from 'lucide-vue-next';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

interface Props {
    voucher: {
        code: string;
        amount: number;
        formatted_amount: string;
        currency: string;
    };
    rider?: {
        message?: string;
        processed_content?: {
            type: 'html' | 'markdown' | 'svg' | 'url' | 'text';
            content: string;
            raw: string;
        };
        url?: string;
    };
    redirect_timeout?: number;
    config?: {
        button_labels?: {
            continue?: string;
            dashboard?: string;
            redeem_another?: string;
        };
    };
}

const props = defineProps<Props>();

const countdown = ref(0);
const isRedirecting = ref(false);

const hasRiderUrl = computed(() => !!props.rider?.url);
const hasCustomContent = computed(() => !!props.rider?.processed_content);

const renderedContent = computed(() => {
    if (!hasCustomContent.value) return null;

    const { type, content } = props.rider!.processed_content!;

    switch (type) {
        case 'markdown':
            return DOMPurify.sanitize(marked.parse(content) as string);
        case 'html':
        case 'svg':
            return DOMPurify.sanitize(content);
        case 'url':
            return `<iframe src="${content}" class="w-full h-96 border-0" />`;
        case 'text':
        default:
            return content.replace(/\n/g, '<br>');
    }
});

const displayMessage = computed(() => {
    return props.rider?.message || 'The funds will be disbursed to your account shortly. You will receive a confirmation via SMS and email.';
});

const handleRedirect = () => {
    if (!hasRiderUrl.value) return;
    isRedirecting.value = true;
    window.location.href = `/disburse/${props.voucher.code}/redirect`;
};

onMounted(() => {
    if (hasRiderUrl.value && props.rider?.url) {
        const timeout = (props.redirect_timeout ?? 10) * 1000;
        countdown.value = Math.ceil(timeout / 1000);

        const interval = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) clearInterval(interval);
        }, 1000);

        setTimeout(() => {
            handleRedirect();
        }, timeout);
    }
});
</script>

<template>
    <Head title="Redemption Successful" />

    <div class="min-h-screen bg-gradient-to-b from-amber-50/80 via-white to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-5 py-8">
        <div class="mx-auto max-w-md space-y-8">

            <!-- Hero: success + amount + voucher code -->
            <div class="text-center pt-4 space-y-3">
                <CheckCircle2 class="h-8 w-8 text-green-500 mx-auto" />
                <p class="text-4xl font-bold tracking-tight text-foreground">
                    {{ voucher.formatted_amount }}
                </p>
                <div class="inline-flex items-center gap-1.5 px-4 py-1 text-sm font-mono font-semibold tracking-widest text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200/80 dark:border-amber-700/30 rounded-full">
                    <span class="text-amber-400 dark:text-amber-600" aria-hidden="true">||</span>
                    {{ voucher.code }}
                    <span class="text-amber-400 dark:text-amber-600" aria-hidden="true">||</span>
                </div>
            </div>

            <!-- Rider content (markdown/HTML/SVG/URL/text) -->
            <div v-if="hasCustomContent" class="overflow-visible">
                <div
                    v-html="renderedContent"
                    class="prose prose-sm max-w-none dark:prose-invert text-center overflow-visible"
                />
            </div>
            <p v-else class="text-sm text-muted-foreground text-center">
                {{ displayMessage }}
            </p>

            <!-- Redirect with countdown -->
            <div v-if="hasRiderUrl && !isRedirecting" class="space-y-3">
                <Button
                    @click="handleRedirect"
                    size="lg"
                    class="w-full rounded-full bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 text-white shadow-lg shadow-amber-600/20 dark:shadow-amber-500/10"
                >
                    {{ config?.button_labels?.continue || 'Continue Now' }}
                    <ExternalLink :size="14" class="ml-1.5" />
                </Button>
                <p class="text-center text-[11px] text-gray-400 dark:text-gray-600">
                    Redirecting in {{ countdown }}s
                </p>
            </div>

            <!-- Redirecting -->
            <p v-else-if="hasRiderUrl && isRedirecting" class="text-center text-sm text-muted-foreground">
                Redirecting…
            </p>

            <!-- Default actions (no rider URL) -->
            <div v-else class="space-y-3">
                <Button
                    size="lg"
                    class="w-full rounded-full bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 text-white shadow-lg shadow-amber-600/20 dark:shadow-amber-500/10"
                    @click="router.visit('/disburse')"
                >
                    {{ config?.button_labels?.redeem_another || 'Redeem Another' }}
                </Button>
                <Button
                    variant="ghost"
                    size="lg"
                    class="w-full rounded-full"
                    @click="router.visit('/')"
                >
                    {{ config?.button_labels?.dashboard || 'Go to Dashboard' }}
                </Button>
            </div>
        </div>
    </div>
</template>
