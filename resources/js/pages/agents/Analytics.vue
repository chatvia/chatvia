<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DateRangePicker from '@/components/ui/DateRangePicker.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import WorldMap from '@/components/WorldMap.vue';
import { useTimeAgo } from '@/composables/useTimeAgo';
import AgentLayout from '@/layouts/AgentLayout.vue';
import axios from 'axios';
import {
    BarElement,
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
import { BarChart3, Globe2, LoaderCircle, MessageCircle, MessageSquare, ThumbsDown, ThumbsUp, TrendingUp } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Bar, Line } from 'vue-chartjs';

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, ChartTooltip, Filler);

// Country list dialog state
const isCountryDialogOpen = ref(false);

// Day of week dialog state
const isDayOfWeekDialogOpen = ref(false);

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

const props = defineProps<Props>();

const loading = ref(true);
const isRefreshing = ref(false);
const analytics = ref<App.Data.Agent.Stats.StatsData | null>(null);
const error = ref<string | null>(null);

const { formatTimeAgo } = useTimeAgo();

// Date range state
const dateRange = ref({
    start: '',
    end: '',
});
const isInitialLoad = ref(true);

// Active tab for conversations chart
const activeChartTab = ref('daily');

// Check if date picker should be disabled (no data available)
const isDatePickerDisabled = computed(() => {
    return !analytics.value?.first_data_date;
});

// Helper to get default date range based on available data
const getDefaultDateRange = (firstDataDate?: string | null) => {
    const end = new Date();
    const endStr = end.toISOString().split('T')[0];

    if (!firstDataDate) {
        // No data, default to last 7 days
        const start = new Date();
        start.setDate(start.getDate() - 6);
        return {
            start: start.toISOString().split('T')[0],
            end: endStr,
        };
    }

    const firstDate = new Date(firstDataDate);
    const today = new Date();
    const daysDiff = Math.floor((today.getTime() - firstDate.getTime()) / (1000 * 60 * 60 * 24));

    if (daysDiff < 7) {
        // Less than 7 days of data, use all available data
        return {
            start: firstDataDate,
            end: endStr,
        };
    } else {
        // More than 7 days, default to last 7 days
        const start = new Date();
        start.setDate(start.getDate() - 6);
        return {
            start: start.toISOString().split('T')[0],
            end: endStr,
        };
    }
};

// Fetch analytics data
const fetchAnalytics = async () => {
    // Only show full loading state on initial load
    if (analytics.value === null) {
        loading.value = true;
    } else {
        isRefreshing.value = true;
    }
    error.value = null;

    try {
        const response = await axios.get(route('agents.analytics.data', props.agent.external_id), {
            params: {
                start_date: dateRange.value.start || undefined,
                end_date: dateRange.value.end || undefined,
            },
        });
        analytics.value = response.data;

        // On initial load, set the date range based on available data
        if (isInitialLoad.value) {
            dateRange.value = getDefaultDateRange(response.data.first_data_date);
            isInitialLoad.value = false;
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load analytics';
        console.error('Error fetching analytics:', err);
    } finally {
        loading.value = false;
        isRefreshing.value = false;
    }
};

// Watch for date range changes and reload data (skip initial load)
watch(
    dateRange,
    () => {
        if (!isInitialLoad.value) {
            fetchAnalytics();
        }
    },
    { deep: true },
);

// Fetch analytics on mount
onMounted(() => {
    fetchAnalytics();
});

// Helper functions
const formatDuration = (seconds: number | null): string => {
    if (!seconds) return 'N/A';
    const minutes = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    }
    return `${secs}s`;
};

const formatHour = (hour: number): string => {
    return `${hour.toString().padStart(2, '0')}:00`;
};

const getFlagPath = (countryCode: string): string => {
    return `/images/flags/${countryCode.toLowerCase()}.svg`;
};

// Helper to get peak activity hour formatted
const getPeakActivity = () => {
    const hour = analytics.value?.peak_activity_hour;
    return hour != null ? formatHour(hour) : 'N/A';
};

// Helper to get busiest day
const getBusiestDay = () => {
    const days = analytics.value?.activity_by_day_of_week || [];
    if (!days.length) return 'N/A';
    const busiest = [...days].sort((a, b) => b.count - a.count)[0];
    return busiest?.day || 'N/A';
};

// Chart data for conversations trend - Dynamic based on date range
const conversationChartData = computed(() => {
    const trend = analytics.value?.conversations_trend || [];
    const trendData = new Map(trend.map((item) => [item.date, item.conversations || 0]));

    const start = new Date(dateRange.value.start);
    const end = new Date(dateRange.value.end);
    const daysDiff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));

    const result = [];
    for (let i = 0; i <= daysDiff; i++) {
        const date = new Date(start);
        date.setDate(date.getDate() + i);
        const dateStr = date.toISOString().split('T')[0];

        result.push({
            date: dateStr,
            dayMonth: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
            conversations: trendData.get(dateStr) || 0,
        });
    }

    return result;
});

const maxConversations = computed(() => {
    if (!conversationChartData.value.length) return 10;
    return Math.max(...conversationChartData.value.map((day) => day.conversations), 10);
});

// Activity by hour chart - max value for scaling
const maxHourlyActivity = computed(() => {
    const hours = analytics.value?.activity_by_hour || [];
    if (!hours.length) return 10;
    return Math.max(...hours.map((h) => h.count), 10);
});

// Chart.js data and options
const chartData = computed(() => ({
    labels: conversationChartData.value.map((d) => d.dayMonth),
    datasets: [
        {
            label: 'Conversations',
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

const chartOptions: ChartOptions<'line'> = {
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
                title: function (context: any) {
                    return context[0].label;
                },
                label: function (context: any) {
                    const currentValue = context.parsed.y;
                    const currentIndex = context.dataIndex;
                    const currentDate = context.label;

                    // Get previous day value if it exists
                    if (currentIndex > 0) {
                        const previousValue = context.dataset.data[currentIndex - 1];
                        const previousDate = context.chart.data.labels[currentIndex - 1];
                        const difference = currentValue - previousValue;
                        const sign = difference >= 0 ? '+' : '';
                        const percentage = previousValue > 0 ? ((difference / previousValue) * 100).toFixed(1) : '0.0';

                        return [
                            `${currentDate}: ${currentValue} conversations`,
                            `${previousDate}: ${previousValue} conversations`,
                            `Change: ${sign}${difference} (${sign}${percentage}%)`,
                        ];
                    }

                    return `${currentDate}: ${currentValue} conversations`;
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
};

// Hourly activity chart data
const hourlyChartData = computed(() => {
    const hours = analytics.value?.activity_by_hour || [];
    return {
        labels: hours.map((h) => formatHour(h.hour)),
        datasets: [
            {
                label: 'Conversations',
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

const hourlyChartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: 'rgb(17, 24, 39)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgb(55, 65, 81)',
            borderWidth: 1,
            padding: 12,
            displayColors: false,
            callbacks: {
                label: function (context: any) {
                    return `${context.parsed.y} conversations`;
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
};

// Day of week chart data
const dayOfWeekChartData = computed(() => {
    const days = analytics.value?.activity_by_day_of_week || [];
    return {
        labels: days.map((d) => d.day),
        datasets: [
            {
                label: 'Conversations',
                data: days.map((d) => d.count),
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1,
                borderRadius: 4,
            },
        ],
    };
});

const dayOfWeekChartOptions: ChartOptions<'bar'> = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: 'rgb(17, 24, 39)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgb(55, 65, 81)',
            borderWidth: 1,
            padding: 12,
            displayColors: false,
            callbacks: {
                label: function (context: any) {
                    return `${context.parsed.x} conversations`;
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
};
</script>

<template>
    <AgentLayout :agent="agent" title="Agent Statistics">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-12">
            <Card class="max-w-md">
                <CardContent class="pt-6">
                    <div class="space-y-2 text-center">
                        <p class="text-destructive font-medium">{{ error }}</p>
                        <button @click="fetchAnalytics" class="text-muted-foreground hover:text-foreground text-sm underline">Try again</button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Analytics Content -->
        <div v-else-if="analytics">
            <!-- Date Range Picker -->
            <div class="relative mb-6">
                <div class="flex items-center gap-3">
                    <DateRangePicker v-model="dateRange" :first-data-date="analytics?.first_data_date" :disabled="isDatePickerDisabled" />
                    <!-- Refreshing Indicator -->
                    <div v-if="isRefreshing" class="text-muted-foreground flex items-center gap-2 text-sm">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        <span>Updating...</span>
                    </div>
                    <!-- No Data Message -->
                    <div v-if="isDatePickerDisabled" class="text-muted-foreground text-sm">No conversation data available yet</div>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Conversations -->
                <Card>
                    <CardContent class="!p-3 !pb-3">
                        <div class="flex items-center gap-2">
                            <MessageCircle class="text-muted-foreground h-4 w-4" />
                            <p class="text-muted-foreground text-sm font-medium">Total conversations</p>
                        </div>
                        <p class="mt-1 text-xl font-bold">{{ (analytics?.total_conversations ?? 0).toLocaleString() }}</p>
                    </CardContent>
                </Card>

                <!-- Total Messages -->
                <Card>
                    <CardContent class="!p-3 !pb-3">
                        <div class="flex items-center gap-2">
                            <MessageSquare class="text-muted-foreground h-4 w-4" />
                            <p class="text-muted-foreground text-sm font-medium">Total messages</p>
                        </div>
                        <p class="mt-1 text-xl font-bold">{{ (analytics?.total_messages ?? 0).toLocaleString() }}</p>
                    </CardContent>
                </Card>

                <!-- Thumbs Up -->
                <Card>
                    <CardContent class="!p-3 !pb-3">
                        <div class="flex items-center gap-2">
                            <ThumbsUp class="text-muted-foreground h-4 w-4" />
                            <p class="text-muted-foreground text-sm font-medium">Thumbs up messages</p>
                        </div>
                        <p class="mt-1 text-xl font-bold">{{ analytics?.feedback?.positive ?? 0 }}</p>
                    </CardContent>
                </Card>

                <!-- Thumbs Down -->
                <Card>
                    <CardContent class="!p-3 !pb-3">
                        <div class="flex items-center gap-2">
                            <ThumbsDown class="text-muted-foreground h-4 w-4" />
                            <p class="text-muted-foreground text-sm font-medium">Thumbs down messages</p>
                        </div>
                        <p class="mt-1 text-xl font-bold">{{ analytics?.feedback?.negative ?? 0 }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Conversations Trend Chart - Full Width with Tabs -->
            <Card class="mb-6">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <TrendingUp class="h-5 w-5" />
                            Conversations Started
                        </CardTitle>
                        <Tabs v-model="activeChartTab" default-value="daily">
                            <TabsList class="grid grid-cols-2">
                                <TabsTrigger value="daily">Daily View</TabsTrigger>
                                <TabsTrigger value="hourly">Hourly View</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="activeChartTab === 'daily'">
                        <div
                            v-if="!conversationChartData || conversationChartData.length === 0"
                            class="text-muted-foreground flex h-[300px] items-center justify-center"
                        >
                            <div class="text-center">
                                <BarChart3 class="mx-auto h-8 w-8 opacity-50" />
                                <p class="mt-2">No conversation data available</p>
                            </div>
                        </div>
                        <div v-else class="h-[300px] w-full">
                            <Line :data="chartData" :options="chartOptions" />
                        </div>
                    </div>

                    <div v-else-if="activeChartTab === 'hourly'">
                        <div class="space-y-4">
                            <!-- Peak Info and Day of Week Button -->
                            <div class="flex items-center justify-between px-2 text-sm">
                                <div class="flex items-center gap-4">
                                    <span class="text-muted-foreground"
                                        >Peak hour: <span class="text-foreground font-medium">{{ getPeakActivity() }}</span></span
                                    >
                                    <span class="text-muted-foreground"
                                        >Busiest day: <span class="text-foreground font-medium">{{ getBusiestDay() }}</span></span
                                    >
                                </div>
                                <Dialog v-model:open="isDayOfWeekDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button variant="outline" size="sm"> View by Day of Week </Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>Activity by Day of Week</DialogTitle>
                                        </DialogHeader>
                                        <div class="mt-4 h-[400px] w-full">
                                            <Bar :data="dayOfWeekChartData" :options="dayOfWeekChartOptions" />
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            </div>

                            <!-- Hourly Activity Chart -->
                            <div>
                                <h4 class="mb-3 px-2 text-sm font-medium">Activity by Hour</h4>
                                <div class="h-[280px] w-full">
                                    <Bar :data="hourlyChartData" :options="hourlyChartOptions" />
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Conversations by Country with Map (Full Width) -->
            <Card class="mb-6 overflow-hidden">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Globe2 class="h-5 w-5" />
                            Chats by Country
                        </CardTitle>
                        <Dialog v-model:open="isCountryDialogOpen" v-if="analytics?.country_usage?.length">
                            <DialogTrigger as-child>
                                <Button variant="outline" size="sm"> View All </Button>
                            </DialogTrigger>
                            <DialogContent class="flex max-h-[80vh] max-w-2xl flex-col overflow-hidden">
                                <DialogHeader>
                                    <DialogTitle>All Countries</DialogTitle>
                                </DialogHeader>
                                <div class="flex-1 overflow-y-auto pr-2">
                                    <div class="space-y-3">
                                        <div class="bg-background sticky top-0 z-10 flex items-center justify-between border-b pb-3">
                                            <div class="text-muted-foreground flex-1 text-sm font-medium">Country</div>
                                            <div class="text-muted-foreground w-32 text-right text-sm font-medium">Progress</div>
                                            <div class="text-muted-foreground w-16 text-right text-sm font-medium">Chats</div>
                                            <div class="text-muted-foreground w-16 text-right text-sm font-medium">%</div>
                                        </div>

                                        <div
                                            v-for="country in analytics?.country_usage"
                                            :key="country.country_code"
                                            class="flex items-center justify-between gap-4 py-2"
                                        >
                                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                                <img
                                                    :src="getFlagPath(country.country_code)"
                                                    :alt="country.country_name"
                                                    class="h-4 w-6 flex-shrink-0 rounded object-cover"
                                                    onerror="this.style.display='none'"
                                                />
                                                <span class="truncate text-sm font-medium">{{ country.country_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <div class="bg-muted h-2 w-32 overflow-hidden rounded-full">
                                                    <div class="h-full bg-blue-600 transition-all" :style="{ width: `${country.percentage}%` }"></div>
                                                </div>
                                                <span class="w-16 text-right text-sm font-bold">{{ country.count }}</span>
                                                <span class="text-muted-foreground w-16 text-right text-sm"
                                                    >{{ Number(country.percentage).toFixed(1) }}%</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent class="overflow-hidden">
                    <div v-if="!analytics?.country_usage?.length" class="text-muted-foreground py-8 text-center">No geographic data available</div>
                    <div v-else class="overflow-hidden">
                        <WorldMap :country-data="analytics?.country_usage ?? []" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>
