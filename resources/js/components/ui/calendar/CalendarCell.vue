<script setup lang="ts">
import { CalendarCell, RangeCalendarCell, type CalendarCellProps } from 'reka-ui'
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<CalendarCellProps & { isRange?: boolean; class?: string }>()

// Check if we're in a RangeCalendar context
const isRangeCalendar = inject('isRangeCalendar', false)

const Component = computed(() => isRangeCalendar || props.isRange ? RangeCalendarCell : CalendarCell)
</script>

<template>
  <component
    :is="Component"
    v-bind="props"
    :class="
      cn(
        'relative h-9 w-9 p-0 text-center text-sm focus-within:relative focus-within:z-20',
        '[&:has([data-selected])]:bg-accent [&:has([data-selected][data-outside-month])]:bg-accent/50',
        '[&:has([data-selection-start])]:rounded-s-md [&:has([data-selection-end])]:rounded-e-md',
        props.class,
      )
    "
  >
    <slot />
  </component>
</template>

