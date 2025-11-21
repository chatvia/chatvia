<script setup lang="ts">
import { CalendarPrev, RangeCalendarPrev, type CalendarPrevProps } from 'reka-ui'
import { ChevronLeft } from 'lucide-vue-next'
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<CalendarPrevProps & { isRange?: boolean }>()

// Check if we're in a RangeCalendar context
const isRangeCalendar = inject('isRangeCalendar', false)

const Component = computed(() => isRangeCalendar || props.isRange ? RangeCalendarPrev : CalendarPrev)
</script>

<template>
  <component
    :is="Component"
    v-bind="props"
    :class="
      cn(
        'inline-flex h-7 w-7 items-center justify-center rounded-md border border-input bg-transparent p-0 text-sm font-normal text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring focus-visible:outline-hidden focus-visible:ring-1 active:bg-accent/90 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        props.class,
      )
    "
  >
    <slot>
      <ChevronLeft class="h-4 w-4" />
    </slot>
  </component>
</template>

