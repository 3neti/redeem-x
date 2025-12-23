<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

const countdownMessage = computed(() => {
    return `You will be redirected in ${countdown.value} second${countdown.value !== 1 ? 's' : ''}...`;
});

const handleRedirect = () => {
    if (!props.rider?.url) return;
    isRedirecting.value = true;
    window.location.href = props.rider.url;
};

onMounted(() => {
    // Start countdown if rider URL exists
    if (hasRiderUrl.value && props.rider?.url) {
        const timeout = (props.redirect_timeout ?? 10) * 1000; // Convert to milliseconds
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
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <Head title="Redemption Successful" />
        <div class="w-full max-w-md">
            <Card>
                <CardHeader class="text-center">
                    <div class="flex justify-center mb-4">
                        <CheckCircle2 class="h-16 w-16 text-green-600" />
                    </div>
                    <CardTitle class="text-2xl">Redemption Successful!</CardTitle>
                    <CardDescription>
                        Your voucher has been redeemed successfully
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="border rounded-lg p-4 space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Voucher Code:</span>
                            <span class="font-mono font-semibold">{{ voucher.code }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Amount:</span>
                            <span class="text-lg font-semibold text-green-600">{{ voucher.formatted_amount }}</span>
                        </div>
                    </div>
                    
                    <!-- Message section -->
                    <div v-if="hasCustomContent">
                        <div 
                            v-html="renderedContent"
                            class="prose prose-sm max-w-none dark:prose-invert text-center"
                        />
                    </div>
                    <p v-else class="text-sm text-muted-foreground text-center">
                        {{ displayMessage }}
                    </p>
                    
                    <!-- Redirect Section (with countdown) -->
                    <div v-if="hasRiderUrl && !isRedirecting" class="space-y-3 pt-2">
                        <div class="text-center">
                            <p class="text-xs text-muted-foreground">
                                {{ countdownMessage }}
                            </p>
                        </div>
                        <Button 
                            class="w-full" 
                            @click="handleRedirect">
                            {{ config?.button_labels?.continue || 'Continue Now' }}
                            <ExternalLink :size="16" class="ml-2" />
                        </Button>
                    </div>

                    <!-- Redirecting State -->
                    <div v-else-if="hasRiderUrl && isRedirecting" class="text-center pt-2">
                        <p class="text-sm text-muted-foreground">
                            Redirecting...
                        </p>
                    </div>

                    <!-- Default Actions (no rider URL) -->
                    <div v-else class="flex gap-3">
                        <Button variant="outline" class="flex-1" @click="router.visit('/')">
                            {{ config?.button_labels?.dashboard || 'Go to Dashboard' }}
                        </Button>
                        <Button class="flex-1" @click="router.visit('/disburse')">
                            {{ config?.button_labels?.redeem_another || 'Redeem Another' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
