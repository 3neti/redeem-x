<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { useVModel } from '@vueuse/core'
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: number | null
  defaultValue?: number
  min?: number
  max?: number
  step?: number | string
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  hideSpinner?: boolean
  selectOnFocus?: boolean
  class?: HTMLAttributes['class']
}>(), {
  hideSpinner: true,
  selectOnFocus: true,
  step: 1,
})

const emits = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const handleFocus = (event: FocusEvent) => {
  if (props.selectOnFocus && !props.readonly) {
    (event.target as HTMLInputElement).select()
  }
}

const inputClasses = computed(() => cn(
  'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
  'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
  'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
  props.hideSpinner && '[&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
  props.class,
))
</script>

<template>
  <input
    v-model.number="modelValue"
    type="number"
    :min="min"
    :max="max"
    :step="step"
    :placeholder="placeholder"
    :disabled="disabled"
    :readonly="readonly"
    :class="inputClasses"
    @focus="handleFocus"
    data-slot="number-input"
  >
</template>
