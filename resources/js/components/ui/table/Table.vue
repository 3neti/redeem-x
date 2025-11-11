<template>
    <div class="relative w-full overflow-auto">
        <table class="w-full caption-bottom text-sm" :class="$attrs.class">
            <slot />
        </table>
    </div>
</template>

<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>
