<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { wallet } from '@/actions/App/Http/Controllers/Redeem/RedeemController';

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
}

const props = defineProps<Props>();

const page = usePage();
const appName = (page.props.name as string) || 'Redeem-X';

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
    voucher_code: '',
});

const voucherInput = ref<HTMLInputElement | null>(null);

function submit() {
    form.voucher_code = form.voucher_code.trim().toUpperCase();
    
    form.get(wallet.url(form.voucher_code), {
        preserveState: true,
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
                <Label v-if="config.showLabel" for="voucher_code">{{ config.label }}</Label>
                <Input
                    id="voucher_code"
                    v-model="form.voucher_code"
                    :placeholder="config.placeholder"
                    required
                    autofocus
                    ref="voucherInput"
                    class="text-center text-lg tracking-wider"
                    @input="form.voucher_code = form.voucher_code.toUpperCase()"
                />
                <InputError :message="form.errors.voucher_code" class="mt-1" />
            </div>

            <!-- Submit Button -->
            <Button 
                type="submit"
                class="w-full"
                :disabled="form.processing || !form.voucher_code.trim()"
            >
                {{ form.processing ? config.buttonProcessingText : config.buttonText }}
            </Button>
        </form>
    </div>
</template>
