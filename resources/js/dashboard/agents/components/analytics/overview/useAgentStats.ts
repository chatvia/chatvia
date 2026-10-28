/**
 * State of the agent analytics overview: the stats, the selected date range
 * and the loading flags. The first load lets the server pick the range, then
 * picks a default range from how much data exists; every later range change
 * reloads the stats while the old numbers stay on screen.
 */
import { fetchAgentStats, type AgentStats } from '@/dashboard/agents/api/analytics';
import { errorMessage } from '@/shared/api/http';
import { computed, onMounted, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';

/** A date range as `YYYY-MM-DD` strings, the shape DateRangePicker binds. */
export interface StatsDateRange {
    start: string;
    end: string;
}

const MS_PER_DAY = 1000 * 60 * 60 * 24;

const isoDay = (date: Date): string => date.toISOString().split('T')[0];

const lastSevenDays = (): StatsDateRange => {
    const start = new Date();
    start.setDate(start.getDate() - 6);

    return { start: isoDay(start), end: isoDay(new Date()) };
};

/** The last 7 days, or every day since the first data when there is less than a week of it. */
function defaultDateRange(firstDataDate?: string | null): StatsDateRange {
    if (!firstDataDate) {
        return lastSevenDays();
    }

    const daysOfData = Math.floor((new Date().getTime() - new Date(firstDataDate).getTime()) / MS_PER_DAY);

    return daysOfData < 7 ? { start: firstDataDate, end: isoDay(new Date()) } : lastSevenDays();
}

export function useAgentStats(agentId: MaybeRefOrGetter<string>) {
    const { t } = useI18n();

    const loading = ref(true);
    const isRefreshing = ref(false);
    const analytics = ref<AgentStats | null>(null);
    const error = ref<string | null>(null);
    const dateRange = ref<StatsDateRange>({ start: '', end: '' });
    const isInitialLoad = ref(true);

    // Without any data there is nothing to pick a range for.
    const isDatePickerDisabled = computed(() => !analytics.value?.first_data_date);

    const fetchAnalytics = async () => {
        // Only show the full loading state on the first load.
        if (analytics.value === null) {
            loading.value = true;
        } else {
            isRefreshing.value = true;
        }
        error.value = null;

        try {
            const stats = await fetchAgentStats(toValue(agentId), {
                start_date: dateRange.value.start || undefined,
                end_date: dateRange.value.end || undefined,
            });
            analytics.value = stats;

            if (isInitialLoad.value) {
                dateRange.value = defaultDateRange(stats.first_data_date);
                isInitialLoad.value = false;
            }
        } catch (err) {
            error.value = errorMessage(err, t('agents.analytics.load_error'));
            console.error('Error fetching analytics:', err);
        } finally {
            loading.value = false;
            isRefreshing.value = false;
        }
    };

    watch(
        dateRange,
        () => {
            if (!isInitialLoad.value) {
                fetchAnalytics();
            }
        },
        { deep: true },
    );

    onMounted(() => {
        fetchAnalytics();
    });

    return { loading, isRefreshing, analytics, error, dateRange, isDatePickerDisabled, fetchAnalytics };
}
