<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { wallet, start } from '@/actions/App/Http/Controllers/Redeem/RedeemController';

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

const props = defineProps<Props>();

const page = usePage();
const appName = (page.props.name as string) || 'Redeem-X';
const errors = computed(() => page.props.errors as Record<string, string>);

// Get config from props or fallback to shared config
const config = computed(() => {
    const widgetConfig = (page.props.redeem as any)?.widget || {};
    
    // Use widgetConfig directly, ignore props for now
    return {
        showLogo: widgetConfig.show_logo ?? true,
        showAppName: widgetConfig.show_app_name ?? true,
        showLabel: widgetConfig.show_label ?? true,
        showTitle: widgetConfig.show_title ?? true,
        showDescription: widgetConfig.show_description ?? false,
        title: widgetConfig.title ?? 'Redeem Voucher',
        description: widgetConfig.description ?? null,
        label: widgetConfig.label ?? 'Voucher Code',
        placeholder: widgetConfig.placeholder ?? 'Enter voucher code',
        buttonText: widgetConfig.button_text ?? 'Start Redemption',
        buttonProcessingText: widgetConfig.button_processing_text ?? 'Checking...',
    };
});

const form = useForm({
    code: props.initialCode || '',
});

// Debug logging
onMounted(() => {
    console.log('[RedeemWidget] onMounted called');
    console.log('[RedeemWidget] props.initialCode:', props.initialCode);
    console.log('[RedeemWidget] form.code:', form.code);
    
    // Focus button if code is pre-filled, otherwise focus input
    if (props.initialCode && submitButton.value) {
        // Access the underlying DOM element from the Button component
        const buttonEl = submitButton.value.$el as HTMLElement;
        buttonEl?.focus();
    }
});

const voucherInput = ref<HTMLInputElement | null>(null);
const submitButton = ref<HTMLButtonElement | null>(null);

function submit() {
    form.code = form.code.trim().toUpperCase();
    
    // Submit to start route which will validate and redirect
    form.get(start.url(), {
        preserveState: (page) => {
            // Preserve state only if there are no errors
            const hasErrors = Object.keys(page.props.errors || {}).length > 0;
            console.log('[RedeemWidget] Response errors:', page.props.errors);
            console.log('[RedeemWidget] Has errors:', hasErrors);
            console.log('[RedeemWidget] Preserve state:', !hasErrors);
            return !hasErrors;
        },
        preserveScroll: true,
        onError: (errors) => {
            console.log('[RedeemWidget] onError callback:', errors);
        },
    });
}
</script>

<template>
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
                    v-model="form.code"
                    :placeholder="config.placeholder"
                    required
                    autofocus
                    ref="voucherInput"
                    class="text-center text-lg tracking-wider"
                    @input="form.code = form.code.toUpperCase()"
                />
                <InputError :message="errors.code" class="mt-1" />
            </div>

            <!-- Submit Button -->
            <Button 
                ref="submitButton"
                type="submit"
                class="w-full"
                :disabled="form.processing || !form.code?.trim()"
            >
                {{ form.processing ? config.buttonProcessingText : config.buttonText }}
            </Button>
        </form>
    </div>
</template>
