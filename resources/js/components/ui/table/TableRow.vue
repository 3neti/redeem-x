<template>
    <tr class="border-b transition-colors hover:bg-muted/50" :class="$attrs.class">
        <slot />
    </tr>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>
