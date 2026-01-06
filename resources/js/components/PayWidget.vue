<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface Props {
    showLogo?: boolean;
    showAppName?: boolean;
    showLabel?: boolean;
    showTitle?: boolean;
    showDescription?: boolean;
    title?: string;
    description?: string;
    label?: string;
    placeholder?: string;
    buttonText?: string;
    buttonProcessingText?: string;
    initialCode?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    showLogo: true,
    showAppName: true,
    showLabel: true,
    showTitle: true,
    showDescription: false,
    title: 'Pay Voucher',
    description: undefined,
    label: 'Voucher Code',
    placeholder: 'Enter voucher code',
    buttonText: 'Continue to Payment',
    buttonProcessingText: 'Checking...',
    initialCode: null,
});

const page = usePage();
const appName = (page.props.name as string) || 'Redeem-X';

// Get config from backend or fallback to props (like RedeemWidget)
const config = computed(() => {
    const widgetConfig = (page.props.pay as any)?.widget || {};
    
    return {
        showLogo: widgetConfig.show_logo ?? props.showLogo,
        showAppName: widgetConfig.show_app_name ?? props.showAppName,
        showLabel: widgetConfig.show_label ?? props.showLabel,
        showTitle: widgetConfig.show_title ?? props.showTitle,
        showDescription: widgetConfig.show_description ?? props.showDescription,
        title: widgetConfig.title ?? props.title,
        description: widgetConfig.description ?? props.description,
        label: widgetConfig.label ?? props.label,
        placeholder: widgetConfig.placeholder ?? props.placeholder,
        buttonText: widgetConfig.button_text ?? props.buttonText,
        buttonProcessingText: widgetConfig.button_processing_text ?? props.buttonProcessingText,
    };
});

const code = ref(props.initialCode || '');
const loading = ref(false);
const error = ref('');

// Computed property for checking if code is valid
const hasValidCode = computed(() => code.value.trim().length > 0);

async function submit() {
    if (!code.value.trim()) {
        error.value = 'Please enter a voucher code';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        // Get CSRF token from Inertia page props
        const csrfToken = (page.props as any).csrf_token || 
                          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const response = await fetch('/pay/quote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ code: code.value.trim().toUpperCase() }),
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.error || 'Failed to validate voucher';
            return;
        }

        // Emit event to parent with quote data
        emit('quote-loaded', data);
    } catch (err: any) {
        error.value = err.message || 'Network error';
    } finally {
        loading.value = false;
    }
}

const emit = defineEmits<{
    'quote-loaded': [quote: any]
}>();
</script>

<template>
{{ config }}
    <div class="flex flex-col gap-6">
        <!-- Logo and App Name -->
        <div v-if="config.showLogo || config.showAppName" class="flex flex-col items-center gap-2">
            <!-- Logo only (icon) -->
            <div v-if="config.showLogo && !config.showAppName" class="flex items-center justify-center">
                <div class="flex aspect-square size-12 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <AppLogoIcon class="size-7 fill-current text-white dark:text-black" />
                </div>
            </div>
            
            <!-- Logo with App Name -->
            <div v-else-if="config.showLogo && config.showAppName" class="flex items-center gap-2">
                <AppLogo />
            </div>
            
            <!-- App Name only (no logo) -->
            <div v-else-if="config.showAppName">
                <span class="text-lg font-semibold">{{ appName }}</span>
            </div>
        </div>

        <!-- Title and Description -->
        <div v-if="config.showTitle || config.showDescription" class="space-y-2 text-center">
            <h1 v-if="config.showTitle" class="text-xl font-medium">{{ config.title }}</h1>
            <p v-if="config.showDescription && config.description" class="text-center text-sm text-muted-foreground">
                {{ config.description }}
            </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Voucher Code -->
            <div class="flex flex-col gap-2">
                <Label v-if="config.showLabel" for="code">{{ config.label }}</Label>
                <Input
                    id="code"
                    v-model="code"
                    :placeholder="config.placeholder"
                    required
                    autofocus
                    class="text-center text-lg tracking-wider"
                    :disabled="loading"
                />
                <InputError v-if="error" :message="error" class="mt-1" />
            </div>

            <!-- Submit Button -->
            <Button 
                type="submit"
                class="w-full"
                :disabled="loading || !hasValidCode"
            >
                {{ loading ? config.buttonProcessingText : config.buttonText }}
            </Button>
        </form>
    </div>
</template>
