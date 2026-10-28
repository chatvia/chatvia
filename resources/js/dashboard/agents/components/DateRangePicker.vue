<script setup lang="ts">
/**
 * Date range picker with presets (today, last 7 days, this month, …) and a
 * two-month calendar for a custom range. Binds `{ start, end }` as
 * `YYYY-MM-DD` strings; picking days by hand switches the preset to "custom".
 * A custom range is shown as dates in the UI language.
 */
import { Button } from '@/shared/ui/button';
import { RangeCalendar } from '@/shared/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/shared/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import { useLocaleDate } from '@/shared/composables/useLocaleDate';
import { cn } from '@/shared/lib/utils';
import { CalendarDate, DateFormatter, getLocalTimeZone, today, type DateValue } from '@internationalized/date';
import { CalendarIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

// Local alias matching reka-ui's `DateRange` shape. We avoid importing it
// directly to keep `DateValue` resolving to the @internationalized/date
// identity — reka-ui re-exports it under an alias which vue-tsc treats as a
// distinct nominal type even though it's structurally identical.
type DateRange = { start: DateValue | undefined; end: DateValue | undefined };

interface Props {
    modelValue?: { start: string; end: string };
    firstDataDate?: string | null;
    disabled?: boolean;
    class?: string;
}

interface Emits {
    (e: 'update:modelValue', value: { start: string; end: string }): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { t } = useI18n();
const { intlLocale } = useLocaleDate();

// Convert string date to CalendarDate
const stringToCalendarDate = (dateStr: string): CalendarDate => {
    const date = new Date(dateStr);
    return new CalendarDate(date.getFullYear(), date.getMonth() + 1, date.getDate());
};

// Convert CalendarDate to string
const calendarDateToString = (date: DateValue): string => {
    return `${date.year}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`;
};

// Convert first data date to CalendarDate
const minDate = computed(() => {
    if (props.firstDataDate) {
        return stringToCalendarDate(props.firstDataDate);
    }
    return undefined;
});

const df = computed(
    () =>
        new DateFormatter(intlLocale.value, {
            dateStyle: 'medium',
        }),
);

const presets = computed(() => [
    { label: t('shared.date_range.preset_today'), value: 'today' },
    { label: t('shared.date_range.preset_yesterday'), value: 'yesterday' },
    { label: t('shared.date_range.preset_last_7_days'), value: 'last_7_days' },
    { label: t('shared.date_range.preset_last_30_days'), value: 'last_30_days' },
    { label: t('shared.date_range.preset_this_month'), value: 'this_month' },
    { label: t('shared.date_range.preset_last_month'), value: 'last_month' },
    { label: t('shared.date_range.preset_last_3_months'), value: 'last_3_months' },
    { label: t('shared.date_range.preset_last_12_months'), value: 'last_12_months' },
    { label: t('shared.date_range.preset_custom'), value: 'custom' },
]);

const selectedPreset = ref('last_7_days');
const isOpen = ref(false);

// Initialize date range. Cast the inner values to `DateValue` so vue-tsc
// keeps the canonical union identity instead of widening to a structural
// shape (which then fails to match the same `DateValue` union elsewhere).
const value = ref<DateRange>({
    start: (props.modelValue?.start ? stringToCalendarDate(props.modelValue.start) : today(getLocalTimeZone()).subtract({ days: 6 })) as DateValue,
    end: (props.modelValue?.end ? stringToCalendarDate(props.modelValue.end) : today(getLocalTimeZone())) as DateValue,
});

// Calculate date range based on preset
const getPresetDateRange = (preset: string): DateRange => {
    const now = today(getLocalTimeZone()) as DateValue;

    switch (preset) {
        case 'today':
            return { start: now, end: now };
        case 'yesterday': {
            const yesterday = (now as CalendarDate).subtract({ days: 1 }) as DateValue;
            return { start: yesterday, end: yesterday };
        }
        case 'last_7_days':
            return { start: (now as CalendarDate).subtract({ days: 6 }) as DateValue, end: now };
        case 'last_30_days':
            return { start: (now as CalendarDate).subtract({ days: 29 }) as DateValue, end: now };
        case 'this_month':
            return { start: (now as CalendarDate).set({ day: 1 }) as DateValue, end: now };
        case 'last_month': {
            const lastMonth = (now as CalendarDate).subtract({ months: 1 });
            const lastMonthStart = lastMonth.set({ day: 1 }) as DateValue;
            const lastMonthEnd = (now as CalendarDate).set({ day: 1 }).subtract({ days: 1 }) as DateValue;
            return { start: lastMonthStart, end: lastMonthEnd };
        }
        case 'last_3_months':
            return { start: (now as CalendarDate).subtract({ months: 3 }) as DateValue, end: now };
        case 'last_12_months':
            return { start: (now as CalendarDate).subtract({ months: 12 }) as DateValue, end: now };
        default:
            return value.value as DateRange;
    }
};

const emitValue = () => {
    if (value.value.start && value.value.end) {
        emit('update:modelValue', {
            start: calendarDateToString(value.value.start as DateValue),
            end: calendarDateToString(value.value.end as DateValue),
        });
    }
};

// Watch preset changes
watch(selectedPreset, (newPreset) => {
    if (newPreset !== 'custom') {
        value.value = getPresetDateRange(newPreset);
        emitValue();
        // Close popover when preset is selected
        isOpen.value = false;
    }
});

// Watch value changes from calendar
watch(
    value,
    () => {
        // Don't auto-switch to custom when programmatically setting preset values
        const currentPresetRange = selectedPreset.value !== 'custom' ? getPresetDateRange(selectedPreset.value) : null;

        if (currentPresetRange) {
            const startMatches =
                value.value.start &&
                currentPresetRange.start &&
                calendarDateToString(value.value.start as DateValue) === calendarDateToString(currentPresetRange.start as DateValue);
            const endMatches =
                value.value.end &&
                currentPresetRange.end &&
                calendarDateToString(value.value.end as DateValue) === calendarDateToString(currentPresetRange.end as DateValue);

            if (!startMatches || !endMatches) {
                selectedPreset.value = 'custom';
            }
        }

        emitValue();
    },
    { deep: true },
);

// Format display text
const displayText = computed(() => {
    if (!value.value.start || !value.value.end) return t('shared.date_range.pick_range');

    const presetLabel = presets.value.find((p) => p.value === selectedPreset.value)?.label;
    if (presetLabel && selectedPreset.value !== 'custom') {
        return presetLabel;
    }

    return `${df.value.format(value.value.start.toDate(getLocalTimeZone()))} - ${df.value.format(value.value.end.toDate(getLocalTimeZone()))}`;
});

// The calendar closes the popover once both ends of a range are picked.
const onCalendarUpdate = (range: DateRange) => {
    if (range.start && range.end) isOpen.value = false;
};
</script>

<template>
    <Popover v-model:open="isOpen">
        <PopoverTrigger as-child :disabled="disabled">
            <Button
                variant="outline"
                :disabled="disabled"
                :class="cn('justify-start text-start font-normal', !value && 'text-muted-foreground', props.class)"
            >
                <CalendarIcon class="me-2 h-4 w-4" />
                {{ displayText }}
            </Button>
        </PopoverTrigger>
        <PopoverContent v-if="!disabled" class="w-auto p-0" align="start">
            <div class="border-b p-3">
                <Select v-model="selectedPreset">
                    <SelectTrigger class="w-full">
                        <SelectValue :placeholder="t('shared.date_range.select_placeholder')" />
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
                v-model="value as never"
                :number-of-months="2"
                initial-focus
                :min-value="minDate"
                :max-value="today(getLocalTimeZone())"
                @update:model-value="onCalendarUpdate"
            />
        </PopoverContent>
    </Popover>
</template>
