<script setup lang="ts">
import { TabsRoot, type TabsRootProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<TabsRootProps & { class?: string }>()
</script>

<template>
  <TabsRoot v-bind="props" :class="cn('', props.class)">
    <slot />
  </TabsRoot>
</template>
