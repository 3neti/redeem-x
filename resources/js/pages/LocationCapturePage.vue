<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import LocationCapture, { type LocationData, type LocationCaptureConfig } from '@/FormHandlerLocation/components/LocationCapture.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface Props {
    flow_id: string;
    step: string;
    config?: LocationCaptureConfig;
}

const props = defineProps<Props>();

function handleSubmit(locationData: LocationData) {
    // Submit to FormFlowController
    router.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
        data: locationData,
    });
}

function handleCancel() {
    // Cancel the flow
    router.post(`/form-flow/${props.flow_id}/cancel`);
}
</script>

<template>
    <PublicLayout>
        <LocationCapture
            :config="config"
            @submit="handleSubmit"
            @cancel="handleCancel"
        />
    </PublicLayout>
</template>
