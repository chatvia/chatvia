<script setup lang="ts">
/**
 * The hourly view of the conversations card: peak hour and busiest day, a bar
 * per hour of the day, and a dialog with the activity per day of the week.
 */
import type { AgentStats } from '@/dashboard/agents/api/analytics';
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/shared/ui/dialog';
import { useLocaleDate } from '@/shared/composables/useLocaleDate';
import { BarElement, CategoryScale, Chart as ChartJS, Tooltip as ChartTooltip, LinearScale, Title, type ChartOptions } from 'chart.js';
import { computed, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, ChartTooltip);

const props = defineProps<{
    stats: AgentStats;
}>();

const { t } = useI18n();
const { formatDayOfWeek, intlLocale } = useLocaleDate();

const isDayOfWeekDialogOpen = ref(false);

const formatHour = (hour: number): string => {
    return `${hour.toString().padStart(2, '0')}:00`;
};

const peakActivity = computed(() => {
    const hour = props.stats.peak_activity_hour;
    return hour != null ? formatHour(hour) : t('agents.analytics.na');
});

const busiestDay = computed(() => {
    const days = props.stats.activity_by_day_of_week || [];
    if (!days.length) return t('agents.analytics.na');
    const busiest = [...days].sort((a, b) => b.count - a.count)[0];
    return busiest != null ? formatDayOfWeek(busiest.day_number) : t('agents.analytics.na');
});

// Shared look of both bar charts' tooltips.
const tooltipStyle = {
    backgroundColor: 'rgb(17, 24, 39)',
    titleColor: '#fff',
    bodyColor: '#fff',
    borderColor: 'rgb(55, 65, 81)',
    borderWidth: 1,
    padding: 12,
    displayColors: false,
};

const hourlyChartData = computed(() => {
    const hours = props.stats.activity_by_hour || [];
    return {
        labels: hours.map((h) => formatHour(h.hour)),
        datasets: [
            {
                label: t('agents.analytics.chart_conversations'),
                data: hours.map((h) => h.count),
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1,
                borderRadius: 4,
                barThickness: 'flex' as const,
            },
        ],
    };
});

const hourlyChartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            ...tooltipStyle,
            callbacks: {
                label: function (context) {
                    return t('agents.analytics.chart_tooltip_count', { count: context.parsed.y });
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxRotation: 0,
                autoSkipPadding: 10,
                color: 'rgb(156, 163, 175)',
                font: {
                    size: 11,
                },
            },
            border: {
                display: false,
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgb(243, 244, 246)',
                lineWidth: 1,
                drawTicks: false,
            },
            ticks: {
                display: false,
                maxTicksLimit: 5,
            },
            border: {
                display: false,
            },
        },
    },
}));

const dayOfWeekChartData = computed(() => {
    void intlLocale.value;

    const days = props.stats.activity_by_day_of_week || [];
    return {
        labels: days.map((d) => formatDayOfWeek(d.day_number)),
        datasets: [
            {
                label: t('agents.analytics.chart_conversations'),
                data: days.map((d) => d.count),
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1,
                borderRadius: 4,
            },
        ],
    };
});

const dayOfWeekChartOptions = computed<ChartOptions<'bar'>>(() => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            ...tooltipStyle,
            callbacks: {
                label: function (context) {
                    return t('agents.analytics.chart_tooltip_count', { count: context.parsed.x });
                },
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: {
                color: 'rgb(243, 244, 246)',
                lineWidth: 1,
                drawTicks: false,
            },
            ticks: {
                display: false,
            },
            border: {
                display: false,
            },
        },
        y: {
            grid: {
                display: false,
            },
            ticks: {
                color: 'rgb(156, 163, 175)',
                font: {
                    size: 12,
                },
            },
            border: {
                display: false,
            },
        },
    },
}));
</script>

<template>
    <div class="space-y-4">
        <!-- Peak Info and Day of Week Button -->
        <div class="flex items-center justify-between px-2 text-sm">
            <div class="flex items-center gap-4">
                <span class="text-muted-foreground"
                    >{{ t('agents.analytics.peak_hour') }}: <span class="text-foreground font-medium">{{ peakActivity }}</span></span
                >
                <span class="text-muted-foreground"
                    >{{ t('agents.analytics.busiest_day') }}: <span class="text-foreground font-medium">{{ busiestDay }}</span></span
                >
            </div>
            <Dialog v-model:open="isDayOfWeekDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" size="sm">{{ t('agents.analytics.view_by_day') }}</Button>
                </DialogTrigger>
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ t('agents.analytics.activity_by_day') }}</DialogTitle>
                    </DialogHeader>
                    <div class="mt-4 h-[400px] w-full">
                        <Bar :data="dayOfWeekChartData" :options="dayOfWeekChartOptions" />
                    </div>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Hourly Activity Chart -->
        <div>
            <h4 class="mb-3 px-2 text-sm font-medium">{{ t('agents.analytics.activity_by_hour') }}</h4>
            <div class="h-[280px] w-full">
                <Bar :data="hourlyChartData" :options="hourlyChartOptions" />
            </div>
        </div>
    </div>
</template>
