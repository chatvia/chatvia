<script setup lang="ts">
import { CalendarHeading, RangeCalendarHeading, type CalendarHeadingProps } from 'reka-ui'
import { inject, computed } from 'vue'

const props = defineProps<CalendarHeadingProps & { isRange?: boolean }>()

// Check if we're in a RangeCalendar context
const isRangeCalendar = inject('isRangeCalendar', false)

const Component = computed(() => isRangeCalendar || props.isRange ? RangeCalendarHeading : CalendarHeading)
</script>

<template>
  <component :is="Component" v-slot="{ headingValue }" v-bind="props" class="text-sm font-medium">
    <slot>{{ headingValue }}</slot>
  </component>
</template>

