<script setup lang="ts">
import { computed, provide } from 'vue'
import type { ChartConfig } from './chart'

interface Props {
  config: ChartConfig
  class?: string
}

const props = defineProps<Props>()

provide('chartConfig', props.config)

const chartClass = computed(() => props.class || '')
</script>

<template>
  <div :class="chartClass">
    <slot />
  </div>
</template>



