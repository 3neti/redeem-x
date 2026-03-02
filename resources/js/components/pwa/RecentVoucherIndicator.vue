<script setup lang="ts">
import { Clock } from 'lucide-vue-next';

interface Props {
  count: number;
}

defineProps<Props>();

const emit = defineEmits<{
  open: [];
}>();
</script>

<template>
  <Transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0 translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition-all duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-4"
  >
    <button
      v-if="count > 0"
      class="
        fixed bottom-6 left-1/2 -translate-x-1/2 z-40
        flex items-center gap-2
        px-4 py-2.5
        bg-primary/95 text-primary-foreground
        backdrop-blur-sm
        rounded-full
        shadow-lg
        hover:bg-primary
        hover:shadow-xl
        active:scale-95
        transition-all duration-200
        print:hidden
        font-medium text-sm
      "
      @click="emit('open')"
    >
      <Clock class="h-4 w-4" />
      <span>Recent</span>
      <span class="
        flex items-center justify-center
        min-w-[20px] h-5
        px-1.5
        bg-primary-foreground/20
        rounded-full
        text-xs font-semibold
      ">
        {{ count }}
      </span>
    </button>
  </Transition>
</template>
