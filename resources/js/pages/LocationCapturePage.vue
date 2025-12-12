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
    // Transform data to match backend expectations
    const transformedData = {
        latitude: locationData.latitude,
        longitude: locationData.longitude,
        formatted_address: locationData.address?.formatted || null,
        address_components: locationData.address ? {
            city: locationData.address.city,
            state: locationData.address.state,
            country: locationData.address.country,
        } : null,
        snapshot: locationData.snapshot || null,
        accuracy: locationData.accuracy,
    };
    
    // Submit to FormFlowController
    router.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
        data: transformedData,
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
