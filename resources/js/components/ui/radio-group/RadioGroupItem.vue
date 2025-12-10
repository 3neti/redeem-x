<script setup lang="ts">
import type { RadioGroupItemProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { RadioGroupIndicator, RadioGroupItem } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'
import { Circle } from 'lucide-vue-next'

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props
  return delegated
})
</script>

<template>
  <RadioGroupItem
    v-bind="delegatedProps"
    :class="
      cn(
        'aspect-square size-4 rounded-full border border-input text-primary shadow-xs focus:outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
        props.class,
      )
    "
  >
    <RadioGroupIndicator class="flex items-center justify-center">
      <Circle class="size-2 fill-current text-current" />
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
