<script setup lang="ts">
import { CalendarCellTrigger, RangeCalendarCellTrigger, type CalendarCellTriggerProps } from 'reka-ui'
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<CalendarCellTriggerProps & { isRange?: boolean }>()

// Check if we're in a RangeCalendar context
const isRangeCalendar = inject('isRangeCalendar', false)

const Component = computed(() => isRangeCalendar || props.isRange ? RangeCalendarCellTrigger : CalendarCellTrigger)
</script>

<template>
  <component
    :is="Component"
    v-bind="props"
    :class="
      cn(
        'inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-normal ring-offset-background transition-colors whitespace-nowrap',
        'hover:bg-accent hover:text-accent-foreground',
        'focus-visible:ring-ring focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-offset-2',
        'disabled:pointer-events-none disabled:opacity-50',
        
        // Default selected state (middle of range) - transparent to show cell background
        'data-[selected]:bg-transparent data-[selected]:text-foreground data-[selected]:opacity-100',
        
        // Start and End of range (solid black circles) - using !important to override
        'data-[selection-start]:!bg-primary data-[selection-start]:!text-primary-foreground data-[selection-start]:hover:!bg-primary data-[selection-start]:hover:!text-primary-foreground',
        'data-[selection-end]:!bg-primary data-[selection-end]:!text-primary-foreground data-[selection-end]:hover:!bg-primary data-[selection-end]:hover:!text-primary-foreground',

        'data-[today]:bg-accent data-[today]:text-accent-foreground',
        'data-[outside-month]:text-muted-foreground data-[outside-month]:opacity-50 data-[outside-month]:pointer-events-none',
        
        // Disabled/future dates (strikethrough and faded)
        'data-[disabled]:text-muted-foreground data-[disabled]:opacity-40 data-[disabled]:line-through',
        'data-[unavailable]:text-muted-foreground data-[unavailable]:opacity-40 data-[unavailable]:line-through',
        props.class,
      )
    "
  >
    <slot />
  </component>
</template>

