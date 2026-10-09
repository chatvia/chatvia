<script setup lang="ts" generic="Bucket extends string">
/**
 * The trend card of the Tone and Sentiment pages: one line per bucket over
 * the days of the range, or a pie of the range totals. Series, slices and
 * colours come from the page; the chart styling is the same for both.
 */
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useLocaleDate } from '@/composables/useLocaleDate';
import {
    ArcElement,
    CategoryScale,
    Chart as ChartJS,
    Tooltip as ChartTooltip,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    type ChartOptions,
} from 'chart.js';
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';
import { Line, Pie } from 'vue-chartjs';
import { type DimensionBreakdown, type DimensionLineSeries, type DimensionPieSlice, translucent } from './messageDimension';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, ChartTooltip, Legend, Filler);

const props = defineProps<{
    data: DimensionBreakdown<Bucket>;
    lineSeries: DimensionLineSeries<Bucket>[];
    pieSlices: DimensionPieSlice<Bucket>[];
    title: string;
    graphLabel: string;
    pieLabel: string;
}>();

const activeView = defineModel<'graph' | 'pie'>('view', { required: true });

const { formatMonthDay } = useLocaleDate();

const lineChartData = computed(() => ({
    labels: props.data.byDate.map((day) => formatMonthDay(day.date)),
    datasets: props.lineSeries.map((series) => ({
        label: series.label,
        data: props.data.byDate.map((day) => day[series.key]),
        borderColor: series.color,
        backgroundColor: translucent(series.color),
        fill: true,
        tension: 0.4,
        pointBackgroundColor: series.color,
        ...series.point,
    })),
}));

const lineChartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                    size: 12,
                },
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14,
            },
            bodyFont: {
                size: 13,
            },
            displayColors: true,
            callbacks: {
                label: function (context) {
                    return `${context.dataset.label}: ${context.parsed.y}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
                precision: 0,
            },
        },
    },
};

const pieChartData = computed(() => ({
    labels: props.pieSlices.map((slice) => slice.label),
    datasets: [
        {
            data: props.pieSlices.map((slice) => props.data.totals[slice.key]),
            backgroundColor: props.pieSlices.map((slice) => slice.color),
            borderColor: '#fff',
            borderWidth: 2,
        },
    ],
}));

const pieChartOptions: ChartOptions<'pie'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                    size: 12,
                },
            },
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14,
            },
            bodyFont: {
                size: 13,
            },
            callbacks: {
                label: function (context) {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = props.data.totals.total || 0;
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                },
            },
        },
    },
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <CardTitle>{{ title }}</CardTitle>
                    <Info class="text-muted-foreground h-4 w-4" />
                </div>
                <Tabs v-model="activeView" class="w-auto">
                    <TabsList>
                        <TabsTrigger value="graph" @click="activeView = 'graph'"> {{ graphLabel }} </TabsTrigger>
                        <TabsTrigger value="pie" @click="activeView = 'pie'"> {{ pieLabel }} </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Line Chart -->
            <div v-if="activeView === 'graph'" class="h-96">
                <Line :data="lineChartData" :options="lineChartOptions" />
            </div>

            <!-- Pie Chart -->
            <div v-else class="flex h-96 items-center justify-center">
                <div class="w-full max-w-md">
                    <Pie :data="pieChartData" :options="pieChartOptions" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
