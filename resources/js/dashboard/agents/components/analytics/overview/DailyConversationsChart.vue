<script setup lang="ts">
/**
 * Conversations started per day over the selected range. Days without
 * conversations are filled in with 0 so the line covers the whole range; the
 * tooltip compares each day with the day before.
 */
import type { AgentStats } from '@/dashboard/agents/api/analytics';
import { useLocaleDate } from '@/shared/composables/useLocaleDate';
import {
    CategoryScale,
    Chart as ChartJS,
    Tooltip as ChartTooltip,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    type ChartOptions,
} from 'chart.js';
import { BarChart3 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';
import type { StatsDateRange } from './useAgentStats';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, ChartTooltip, Filler);

const props = defineProps<{
    stats: AgentStats;
    dateRange: StatsDateRange;
}>();

const { t } = useI18n();
const { formatMonthDay, intlLocale } = useLocaleDate();

const conversationChartData = computed(() => {
    void intlLocale.value;

    const trend = props.stats.conversations_trend || [];
    const trendData = new Map(trend.map((item) => [item.date, item.conversations || 0]));

    const start = new Date(props.dateRange.start);
    const end = new Date(props.dateRange.end);
    const daysDiff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));

    const result = [];
    for (let i = 0; i <= daysDiff; i++) {
        const date = new Date(start);
        date.setDate(date.getDate() + i);
        const dateStr = date.toISOString().split('T')[0];

        result.push({
            date: dateStr,
            dayMonth: formatMonthDay(dateStr),
            conversations: trendData.get(dateStr) || 0,
        });
    }

    return result;
});

const chartData = computed(() => ({
    labels: conversationChartData.value.map((d) => d.dayMonth),
    datasets: [
        {
            label: t('agents.analytics.chart_conversations'),
            data: conversationChartData.value.map((d) => d.conversations),
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: 'rgb(99, 102, 241)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverRadius: 8,
            pointHoverBackgroundColor: 'rgb(99, 102, 241)',
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 3,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgb(17, 24, 39)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgb(55, 65, 81)',
            borderWidth: 1,
            padding: 12,
            displayColors: false,
            callbacks: {
                title: function (context) {
                    return context[0].label;
                },
                label: function (context) {
                    const currentValue = context.parsed.y ?? 0;
                    const currentIndex = context.dataIndex;
                    const currentDate = context.label;

                    if (currentIndex > 0) {
                        const previousValue = context.dataset.data[currentIndex - 1] as number;
                        const previousDate = context.chart.data.labels?.[currentIndex - 1] as string;
                        const difference = currentValue - previousValue;
                        const sign = difference >= 0 ? '+' : '';
                        const percentage = previousValue > 0 ? ((difference / previousValue) * 100).toFixed(1) : '0.0';

                        return [
                            t('agents.analytics.chart_tooltip_conversations', { date: currentDate, count: currentValue }),
                            t('agents.analytics.chart_tooltip_conversations', { date: previousDate, count: previousValue }),
                            t('agents.analytics.chart_tooltip_change', { sign, diff: difference, percent: percentage }),
                        ];
                    }

                    return t('agents.analytics.chart_tooltip_conversations', { date: currentDate, count: currentValue });
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
                autoSkipPadding: 20,
                color: 'rgb(156, 163, 175)',
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
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false,
    },
}));
</script>

<template>
    <div v-if="!conversationChartData || conversationChartData.length === 0" class="text-muted-foreground flex h-[300px] items-center justify-center">
        <div class="text-center">
            <BarChart3 class="mx-auto h-8 w-8 opacity-50" />
            <p class="mt-2">{{ t('agents.analytics.no_conversation_data') }}</p>
        </div>
    </div>
    <div v-else class="h-[300px] w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
