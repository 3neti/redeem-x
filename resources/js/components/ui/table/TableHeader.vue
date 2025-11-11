<template>
    <thead class="[&_tr]:border-b" :class="$attrs.class">
        <slot />
    </thead>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>
