<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQrGeneration } from '@/composables/useQrGeneration';
import QrDisplay from '@/components/shared/QrDisplay.vue';
import AppLogo from '@/components/AppLogo.vue';

interface Props {
    merchantUuid: string;
    merchantName: string;
    merchantCity?: string;
    config?: {
        show_logo?: boolean;
        title_prefix?: string;
        show_merchant_name?: boolean;
        show_merchant_city?: boolean;
        instruction_title?: string;
        instruction_description?: string;
        show_footer?: boolean;
        footer_text?: string | null;
    };
}

const props = withDefaults(defineProps<Props>(), {
    config: () => ({}),
});

// Generate QR code (no amount specified for dynamic QR)
const { qrData, loading, error } = useQrGeneration(true, 0);
</script>

<template>
    <Head :title="`${config.title_prefix || 'Pay'} ${merchantName}`" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <!-- Logo -->
                <div v-if="config.show_logo !== false" class="flex justify-center mb-6">
                    <AppLogo class="h-12 w-auto" />
                </div>

                <!-- Merchant Info -->
                <div v-if="config.show_merchant_name !== false" class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">
                        {{ merchantName }}
                    </h1>
                    <p v-if="config.show_merchant_city !== false && merchantCity" class="text-sm text-gray-600">
                        {{ merchantCity }}
                    </p>
                </div>

                <!-- QR Code -->
                <div class="bg-gray-50 rounded-xl p-6 mb-6">
                    <QrDisplay
                        :qr-code="qrData?.qr_code ?? null"
                        :loading="loading"
                        :error="error"
                    />
                </div>

                <!-- Instructions -->
                <div class="text-center space-y-2">
                    <p class="text-sm font-medium text-gray-900">
                        {{ config.instruction_title || 'Scan to Pay' }}
                    </p>
                    <p class="text-xs text-gray-600">
                        {{ config.instruction_description || 'Use your GCash, PayMaya, or any QR Ph compatible app' }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div v-if="config.show_footer !== false" class="text-center mt-6">
                <p class="text-xs text-gray-600">
                    {{ config.footer_text || `Powered by ${$page.props.appName || 'redeem-x'}` }}
                </p>
            </div>
        </div>
    </div>
</template>
