<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { DateRange, DateValue } from '@internationalized/date'
import { CalendarDate, DateFormatter, getLocalTimeZone, today } from '@internationalized/date'
import { CalendarIcon } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { RangeCalendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface Props {
    modelValue?: { start: string; end: string }
    firstDataDate?: string | null
    disabled?: boolean
}

interface Emits {
    (e: 'update:modelValue', value: { start: string; end: string }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Convert first data date to CalendarDate
const minDate = computed(() => {
    if (props.firstDataDate) {
        return stringToCalendarDate(props.firstDataDate)
    }
    return undefined
})

const df = new DateFormatter('en-US', {
    dateStyle: 'medium',
})

// Define preset options
const presets = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'Last 7 days', value: 'last_7_days' },
    { label: 'Last 30 days', value: 'last_30_days' },
    { label: 'This month', value: 'this_month' },
    { label: 'Last month', value: 'last_month' },
    { label: 'Last 3 months', value: 'last_3_months' },
    { label: 'Last 12 months', value: 'last_12_months' },
    { label: 'Custom range', value: 'custom' },
]

const selectedPreset = ref('last_7_days')
const isOpen = ref(false)

// Convert string date to CalendarDate
const stringToCalendarDate = (dateStr: string): CalendarDate => {
    const date = new Date(dateStr)
    return new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate())
}

// Convert CalendarDate to string
const calendarDateToString = (date: DateValue): string => {
    return `${date.year}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`
}

// Initialize date range
const value = ref<DateRange>({
    start: props.modelValue?.start ? stringToCalendarDate(props.modelValue.start) : today(getLocalTimeZone()).subtract({ days: 6 }),
    end: props.modelValue?.end ? stringToCalendarDate(props.modelValue.end) : today(getLocalTimeZone()),
})

// Calculate date range based on preset
const getPresetDateRange = (preset: string): DateRange => {
    const now = today(getLocalTimeZone())
    
    switch (preset) {
        case 'today':
            return { start: now, end: now }
        case 'yesterday':
            const yesterday = now.subtract({ days: 1 })
            return { start: yesterday, end: yesterday }
        case 'last_7_days':
            return { start: now.subtract({ days: 6 }), end: now }
        case 'last_30_days':
            return { start: now.subtract({ days: 29 }), end: now }
        case 'this_month':
            return { start: now.set({ day: 1 }), end: now }
        case 'last_month':
            const lastMonth = now.subtract({ months: 1 })
            const lastMonthStart = lastMonth.set({ day: 1 })
            const lastMonthEnd = now.set({ day: 1 }).subtract({ days: 1 })
            return { start: lastMonthStart, end: lastMonthEnd }
        case 'last_3_months':
            return { start: now.subtract({ months: 3 }), end: now }
        case 'last_12_months':
            return { start: now.subtract({ months: 12 }), end: now }
        default:
            return value.value
    }
}

// Watch preset changes
watch(selectedPreset, (newPreset) => {
    if (newPreset !== 'custom') {
        value.value = getPresetDateRange(newPreset)
        emitValue()
        // Close popover when preset is selected
        isOpen.value = false
    }
})

// Watch value changes from calendar
watch(value, () => {
    // Don't auto-switch to custom when programmatically setting preset values
    const currentPresetRange = selectedPreset.value !== 'custom' ? getPresetDateRange(selectedPreset.value) : null
    
    if (currentPresetRange) {
        const startMatches = value.value.start && currentPresetRange.start && 
            calendarDateToString(value.value.start) === calendarDateToString(currentPresetRange.start)
        const endMatches = value.value.end && currentPresetRange.end && 
            calendarDateToString(value.value.end) === calendarDateToString(currentPresetRange.end)
        
        if (!startMatches || !endMatches) {
            selectedPreset.value = 'custom'
        }
    }
    
    emitValue()
}, { deep: true })

const emitValue = () => {
    if (value.value.start && value.value.end) {
        emit('update:modelValue', {
            start: calendarDateToString(value.value.start),
            end: calendarDateToString(value.value.end),
        })
    }
}

// Format display text
const displayText = computed(() => {
    if (!value.value.start || !value.value.end) return 'Pick a date range'
    
    const presetLabel = presets.find(p => p.value === selectedPreset.value)?.label
    if (presetLabel && selectedPreset.value !== 'custom') {
        return presetLabel
    }
    
    return `${df.format(value.value.start.toDate(getLocalTimeZone()))} - ${df.format(value.value.end.toDate(getLocalTimeZone()))}`
})
</script>

<template>
    <Popover v-model:open="isOpen">
        <PopoverTrigger as-child :disabled="disabled">
            <Button
                variant="outline"
                :disabled="disabled"
                :class="cn('justify-start text-left font-normal', !value && 'text-muted-foreground')"
            >
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ displayText }}
            </Button>
        </PopoverTrigger>
        <PopoverContent v-if="!disabled" class="w-auto p-0" align="start">
            <div class="p-3 border-b">
                <Select v-model="selectedPreset">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select date range" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="preset in presets" :key="preset.value" :value="preset.value">
                            {{ preset.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            
            <!-- Calendar - Always visible -->
            <RangeCalendar
                v-model="value"
                :number-of-months="2"
                initial-focus
                :min-value="minDate"
                :max-value="today(getLocalTimeZone())"
                @update:model-value="(range) => { if (range.start && range.end) isOpen = false }"
            />
        </PopoverContent>
    </Popover>
</template>

