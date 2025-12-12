<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import SelfieCapture, { type SelfieData, type SelfieConfig } from './components/SelfieCapture.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface Props {
    flow_id: string;
    step: string;
    config?: SelfieConfig;
}

const props = defineProps<Props>();

function handleSubmit(selfieData: SelfieData) {
    // Submit to FormFlowController
    router.post(`/form-flow/${props.flow_id}/step/${props.step}`, {
        data: selfieData,
    });
}

function handleCancel() {
    // Cancel the flow
    router.post(`/form-flow/${props.flow_id}/cancel`);
}
</script>

<template>
    <PublicLayout>
        <SelfieCapture
            :config="config"
            @submit="handleSubmit"
            @cancel="handleCancel"
        />
    </PublicLayout>
</template>
