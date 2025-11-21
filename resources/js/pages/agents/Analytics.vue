<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import DateRangePicker from '@/components/ui/DateRangePicker.vue';
import WorldMap from '@/components/WorldMap.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Line, Bar } from 'vue-chartjs';
import { 
    Chart as ChartJS, 
    CategoryScale, 
    LinearScale, 
    PointElement, 
    LineElement, 
    BarElement,
    Title, 
    Tooltip as ChartTooltip, 
    Filler,
    type ChartOptions
} from 'chart.js';
import { 
    BarChart3, MessageSquare, Users, Clock, ThumbsUp, ThumbsDown, 
    TrendingUp, MessageCircle, Activity, Globe2,
    Timer, LoaderCircle
} from 'lucide-vue-next';
import { useTimeAgo } from '@/composables/useTimeAgo';
import axios from 'axios';

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, ChartTooltip, Filler);

// Country list dialog state
const isCountryDialogOpen = ref(false);

// Day of week dialog state
const isDayOfWeekDialogOpen = ref(false);

interface Props {
    agent: any
}

const props = defineProps<Props>();

const loading = ref(true);
const isRefreshing = ref(false);
const analytics = ref<any>(null);
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
    return !analytics.value?.firstDataDate;
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
        const response = await axios.get(route('agents.analytics.data', props.agent.id), {
            params: {
                start_date: dateRange.value.start || undefined,
                end_date: dateRange.value.end || undefined,
            }
        });
        analytics.value = response.data;
        
        // On initial load, set the date range based on available data
        if (isInitialLoad.value) {
            dateRange.value = getDefaultDateRange(response.data.firstDataDate);
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
watch(dateRange, () => {
    if (!isInitialLoad.value) {
        fetchAnalytics();
    }
}, { deep: true });

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

// Computed stats
const stats = computed(() => ({
    totalMessages: analytics.value?.totalMessages || 0,
    totalConversations: analytics.value?.totalConversations || 0,
    totalUserMessages: analytics.value?.totalUserMessages || 0,
    uniqueSessions: analytics.value?.uniqueSessions || 0,
    avgResponseTime: analytics.value?.avgResponseTime || 0,
    avgFirstResponseTime: analytics.value?.avgFirstResponseTime || null,
    avgMessagesPerConversation: analytics.value?.avgMessagesPerConversation || 0,
    avgConversationDuration: analytics.value?.avgConversationDuration || null,
    avgTokensPerMessage: analytics.value?.avgTokensPerMessage || 0,
    totalTokensUsed: analytics.value?.totalTokensUsed || 0,
    peakActivityHour: analytics.value?.peakActivityHour,
    conversationsWithFeedback: analytics.value?.conversationsWithFeedback || 0,
    feedback: analytics.value?.feedback || { positive: 0, negative: 0, total: 0 },
    countryUsage: analytics.value?.countryUsage || [],
    conversationsTrend: analytics.value?.conversationsTrend || [],
    activityByHour: analytics.value?.activityByHour || [],
    activityByDayOfWeek: analytics.value?.activityByDayOfWeek || [],
}));

// Peak activity
const peakActivity = computed(() => {
    if (stats.value.peakActivityHour === null) return 'N/A';
    return formatHour(stats.value.peakActivityHour);
});

// Busiest day
const busiestDay = computed(() => {
    if (!stats.value.activityByDayOfWeek.length) return 'N/A';
    const busiest = [...stats.value.activityByDayOfWeek].sort((a, b) => b.count - a.count)[0];
    return busiest?.day || 'N/A';
});

// Chart data for conversations trend - Dynamic based on date range
const conversationChartData = computed(() => {
    const trendData = new Map(
        stats.value.conversationsTrend.map((item: any) => [item.date, item.conversations || 0])
    );
    
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
    return Math.max(...conversationChartData.value.map((day: any) => day.conversations), 10);
});

// Activity by hour chart
const maxHourlyActivity = computed(() => {
    if (!stats.value.activityByHour.length) return 10;
    return Math.max(...stats.value.activityByHour.map((h: any) => h.count), 10);
});

// Chart.js data and options
const chartData = computed(() => ({
    labels: conversationChartData.value.map((d: any) => d.dayMonth),
    datasets: [
        {
            label: 'Conversations',
            data: conversationChartData.value.map((d: any) => d.conversations),
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
        }
    ]
}));

const chartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
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
                title: function(context: any) {
                    return context[0].label;
                },
                label: function(context: any) {
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
                            `Change: ${sign}${difference} (${sign}${percentage}%)`
                        ];
                    }
                    
                    return `${currentDate}: ${currentValue} conversations`;
                }
            }
        }
    },
    scales: {
        x: {
            grid: {
                display: false
            },
            ticks: {
                maxRotation: 0,
                autoSkipPadding: 20,
                color: 'rgb(156, 163, 175)'
            },
            border: {
                display: false
            }
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
                display: false
            }
        }
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
    }
};

// Hourly activity chart data
const hourlyChartData = computed(() => ({
    labels: stats.value.activityByHour.map((h: any) => formatHour(h.hour)),
    datasets: [
        {
            label: 'Conversations',
            data: stats.value.activityByHour.map((h: any) => h.count),
            backgroundColor: 'rgba(99, 102, 241, 0.8)',
            borderColor: 'rgb(99, 102, 241)',
            borderWidth: 1,
            borderRadius: 4,
            barThickness: 'flex',
        }
    ]
}));

const hourlyChartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
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
                label: function(context: any) {
                    return `${context.parsed.y} conversations`;
                }
            }
        }
    },
    scales: {
        x: {
            grid: {
                display: false
            },
            ticks: {
                maxRotation: 0,
                autoSkipPadding: 10,
                color: 'rgb(156, 163, 175)',
                font: {
                    size: 11
                }
            },
            border: {
                display: false
            }
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
                display: false
            }
        }
    }
};

// Day of week chart data
const dayOfWeekChartData = computed(() => ({
    labels: stats.value.activityByDayOfWeek.map((d: any) => d.day),
    datasets: [
        {
            label: 'Conversations',
            data: stats.value.activityByDayOfWeek.map((d: any) => d.count),
            backgroundColor: 'rgba(99, 102, 241, 0.8)',
            borderColor: 'rgb(99, 102, 241)',
            borderWidth: 1,
            borderRadius: 4,
        }
    ]
}));

const dayOfWeekChartOptions: ChartOptions<'bar'> = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
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
                label: function(context: any) {
                    return `${context.parsed.x} conversations`;
                }
            }
        }
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
                display: false
            }
        },
        y: {
            grid: {
                display: false
            },
            ticks: {
                color: 'rgb(156, 163, 175)',
                font: {
                    size: 12
                }
            },
            border: {
                display: false
            }
        }
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Agent Statistics">
        
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center py-12">
            <Card class="max-w-md">
                <CardContent class="pt-6">
                    <div class="text-center space-y-2">
                        <p class="text-destructive font-medium">{{ error }}</p>
                        <button
                            @click="fetchAnalytics"
                            class="text-sm text-muted-foreground hover:text-foreground underline"
                        >
                            Try again
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Analytics Content -->
        <div v-else-if="analytics">
            <!-- Date Range Picker -->
            <div class="mb-6 relative">
                <div class="flex items-center gap-3">
                    <DateRangePicker 
                        v-model="dateRange" 
                        :first-data-date="analytics.firstDataDate" 
                        :disabled="isDatePickerDisabled"
                    />
                    <!-- Refreshing Indicator -->
                    <div v-if="isRefreshing" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                        <span>Updating...</span>
                    </div>
                    <!-- No Data Message -->
                    <div v-if="isDatePickerDisabled" class="text-sm text-muted-foreground">
                        No conversation data available yet
                    </div>
                </div>
            </div>

        <!-- Key Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Conversations -->
            <Card>
                <CardContent class="!p-3 !pb-3">
                    <div class="flex items-center gap-2">
                        <MessageCircle class="h-4 w-4 text-muted-foreground" />
                        <p class="text-sm font-medium text-muted-foreground">Total conversations</p>
                    </div>
                    <p class="text-xl font-bold mt-1">{{ stats.totalConversations.toLocaleString() }}</p>
                </CardContent>
            </Card>

            <!-- Total Messages -->
            <Card>
                <CardContent class="!p-3 !pb-3">
                    <div class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                        <p class="text-sm font-medium text-muted-foreground">Total messages</p>
                    </div>
                    <p class="text-xl font-bold mt-1">{{ stats.totalMessages.toLocaleString() }}</p>
                </CardContent>
            </Card>

            <!-- Thumbs Up -->
            <Card>
                <CardContent class="!p-3 !pb-3">
                    <div class="flex items-center gap-2">
                        <ThumbsUp class="h-4 w-4 text-muted-foreground" />
                        <p class="text-sm font-medium text-muted-foreground">Thumbs up messages</p>
                    </div>
                    <p class="text-xl font-bold mt-1">{{ stats.feedback.positive }}</p>
                </CardContent>
            </Card>

            <!-- Thumbs Down -->
            <Card>
                <CardContent class="!p-3 !pb-3">
                    <div class="flex items-center gap-2">
                        <ThumbsDown class="h-4 w-4 text-muted-foreground" />
                        <p class="text-sm font-medium text-muted-foreground">Thumbs down messages</p>
                    </div>
                    <p class="text-xl font-bold mt-1">{{ stats.feedback.negative }}</p>
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
                    <div v-if="!conversationChartData || conversationChartData.length === 0" class="flex h-[300px] items-center justify-center text-muted-foreground">
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
                            <div class="flex items-center justify-between text-sm px-2">
                                <div class="flex items-center gap-4">
                                    <span class="text-muted-foreground">Peak hour: <span class="font-medium text-foreground">{{ peakActivity }}</span></span>
                                    <span class="text-muted-foreground">Busiest day: <span class="font-medium text-foreground">{{ busiestDay }}</span></span>
                                </div>
                                <Dialog v-model:open="isDayOfWeekDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button variant="outline" size="sm">
                                            View by Day of Week
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>Activity by Day of Week</DialogTitle>
                                        </DialogHeader>
                                        <div class="h-[400px] w-full mt-4">
                                            <Bar :data="dayOfWeekChartData" :options="dayOfWeekChartOptions" />
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            </div>
                            
                            <!-- Hourly Activity Chart -->
                            <div>
                                <h4 class="text-sm font-medium mb-3 px-2">Activity by Hour</h4>
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
                    <Dialog v-model:open="isCountryDialogOpen" v-if="stats.countryUsage.length">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm">
                                View All
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
                            <DialogHeader>
                                <DialogTitle>All Countries</DialogTitle>
                            </DialogHeader>
                            <div class="flex-1 overflow-y-auto pr-2">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between pb-3 border-b sticky top-0 bg-background z-10">
                                        <div class="text-sm font-medium text-muted-foreground flex-1">
                                            Country
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground text-right w-32">
                                            Progress
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground text-right w-16">
                                            Chats
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground text-right w-16">
                                            %
                                        </div>
                                    </div>
                                    
                                    <div v-for="country in stats.countryUsage" :key="country.country_code" class="flex items-center justify-between gap-4 py-2">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <img 
                                                :src="getFlagPath(country.country_code)" 
                                                :alt="country.country_name"
                                                class="w-6 h-4 object-cover rounded flex-shrink-0"
                                                onerror="this.style.display='none'"
                                            />
                                            <span class="text-sm font-medium truncate">{{ country.country_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <div class="w-32 h-2 bg-muted rounded-full overflow-hidden">
                                                <div class="h-full bg-blue-600 transition-all" :style="{ width: `${country.percentage}%` }"></div>
                                            </div>
                                            <span class="text-sm font-bold w-16 text-right">{{ country.count }}</span>
                                            <span class="text-sm text-muted-foreground w-16 text-right">{{ Number(country.percentage).toFixed(1) }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardHeader>
            <CardContent class="overflow-hidden">
                <div v-if="!stats.countryUsage.length" class="text-center py-8 text-muted-foreground">
                    No geographic data available
                </div>
                <div v-else class="overflow-hidden">
                    <WorldMap :country-data="stats.countryUsage" />
                </div>
            </CardContent>
        </Card>

        </div>
    </AgentLayout>
</template>
