<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import VoucherInstructionsDisplay from '@/components/voucher/VoucherInstructionsDisplay.vue';
import VoucherMetadataDisplay from '@/components/voucher/VoucherMetadataDisplay.vue';
import { AlertCircle } from 'lucide-vue-next';
import { useVoucherPreview } from '@/composables/useVoucherPreview';
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
    routePrefix?: 'redeem' | 'disburse'; // Support both /redeem and /disburse
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

// Voucher preview (destructure refs so v-model gets a plain ref)
const {
    code,
    loading,
    error,
    voucherData,
    showPreview,
    reset: resetPreview,
    hidePreview,
} = useVoucherPreview({ debounceMs: 500, minCodeLength: 4 });

// Initialize preview code with initial code if provided
if (props.initialCode) {
    code.value = props.initialCode;
}

// Computed property for checking if code is valid
const hasValidCode = computed(() => code.value.trim().length > 0);

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
    // Use preview code if available, otherwise fall back to form code
    const entered = code.value || form.code;
    form.code = (entered || '').trim().toUpperCase();
    
    // Determine route based on routePrefix prop
    const prefix = props.routePrefix || 'redeem';
    const submitUrl = prefix === 'disburse' ? '/disburse' : start.url();
    
    // Submit to start route which will validate and redirect
    form.get(submitUrl, {
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
                <AppLogoIcon class="h-20 w-auto" />
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
                    ref="voucherInput"
                    class="text-center text-lg tracking-wider"
                />
                <InputError :message="errors.code" class="mt-1" />
            </div>

            <!-- Submit Button -->
            <Button 
                ref="submitButton"
                type="submit"
                class="w-full"
                :disabled="form.processing || !hasValidCode"
            >
                {{ form.processing ? config.buttonProcessingText : config.buttonText }}
            </Button>
        </form>

        <!-- Voucher Preview -->
        <div v-if="showPreview" class="mt-6">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center gap-2 py-8 text-muted-foreground">
                <Spinner class="h-5 w-5" />
                <span>Checking voucher...</span>
            </div>

            <!-- Error State -->
            <Alert v-else-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ error }}
                </AlertDescription>
            </Alert>

            <!-- Preview disabled notice -->
            <Alert v-else-if="voucherData && voucherData.preview && voucherData.preview.enabled === false">
                <AlertDescription>
                    {{ voucherData.preview.message || 'Preview disabled by issuer.' }}
                </AlertDescription>
            </Alert>

            <!-- Preview Tabs -->
            <div v-else-if="voucherData">
                <!-- Preview Message (if provided by issuer) -->
                <Alert v-if="voucherData.preview && voucherData.preview.message" class="mb-4" variant="default">
                    <AlertDescription>
                        <strong class="font-semibold">Note from issuer:</strong> {{ voucherData.preview.message }}
                    </AlertDescription>
                </Alert>
                
                <Tabs default-value="instructions">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="instructions">Instructions</TabsTrigger>
                        <TabsTrigger value="system-info">System Info</TabsTrigger>
                    </TabsList>
                    
                    <TabsContent value="instructions" class="mt-4">
                        <VoucherInstructionsDisplay
                            v-if="voucherData.instructions"
                            :instructions="voucherData.instructions"
                            :voucher-status="voucherData.status"
                        />
                        <Alert v-else>
                            <AlertCircle class="h-4 w-4" />
                            <AlertDescription>
                                This voucher was created before detailed instructions were tracked.
                            </AlertDescription>
                        </Alert>
                    </TabsContent>
                    
                    <TabsContent value="system-info" class="mt-4">
                        <VoucherMetadataDisplay 
                            :metadata="voucherData.metadata"
                            :show-all-fields="true"
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </div>
</template>
