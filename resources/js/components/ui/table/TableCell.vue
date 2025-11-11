<template>
    <td class="p-4 align-middle" :class="$attrs.class">
        <slot />
    </td>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>
