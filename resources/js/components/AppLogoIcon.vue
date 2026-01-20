<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const page = usePage();
const isDark = ref(false);

// Watch for dark mode changes
const updateDarkMode = () => {
    isDark.value = document.documentElement.classList.contains('dark');
};

onMounted(() => {
    updateDarkMode();
    
    // Watch for class changes on html element
    const observer = new MutationObserver(updateDarkMode);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    
    // Store observer for cleanup
    (window as any).__logoObserver = observer;
});

onUnmounted(() => {
    (window as any).__logoObserver?.disconnect();
});

// Get logo config from Inertia props
const logoConfig = computed(() => {
    return (page.props.branding as any)?.logo || {
        light: '/images/logo.png',
        dark: '/images/logo.png',
        fallback: '/images/logo.png',
    };
});

// Select logo based on dark mode
const logoSrc = computed(() => {
    return isDark.value ? logoConfig.value.dark : logoConfig.value.light;
});
</script>

<template>
    <img
        :src="logoSrc"
        alt="Logo"
        :class="className"
        v-bind="$attrs"
    />
</template>
