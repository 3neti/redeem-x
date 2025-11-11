<template>
    <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground" :class="$attrs.class">
        <slot />
    </th>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>
