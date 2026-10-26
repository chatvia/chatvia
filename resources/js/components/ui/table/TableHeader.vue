<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <thead
    data-slot="table-header"
    :class="cn('bg-muted/50 [&_tr]:border-b [&_tr]:hover:bg-transparent', props.class)"
  >
    <slot />
  </thead>
</template>
