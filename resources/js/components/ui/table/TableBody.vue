<template>
    <tbody class="[&_tr:last-child]:border-0" :class="$attrs.class">
        <slot />
    </tbody>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>
