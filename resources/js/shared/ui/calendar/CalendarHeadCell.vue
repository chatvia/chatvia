<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/shared/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <th
    :class="cn('w-9 rounded-md text-[0.8rem] font-normal text-muted-foreground', props.class)"
  >
    <slot />
  </th>
</template>

