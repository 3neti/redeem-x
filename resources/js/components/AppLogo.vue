<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const appName = (page.props.name as string) || 'Redeem-X';
</script>

<template>
    <AppLogoIcon class="h-20 w-auto" />
    <div class="ml-3 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            appName
        }}</span>
    </div>
</template>
