<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Line, Pie } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip as ChartTooltip,
    Legend,
    Filler,
    type ChartOptions
} from 'chart.js';
import {
    SmilePlus, LoaderCircle, Info
} from 'lucide-vue-next';
import axios from 'axios';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    ChartTooltip,
    Legend,
    Filler
);

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData
}

const props = defineProps<Props>();

const loading = ref(true);
const sentimentData = ref<any>(null);
const error = ref<string | null>(null);
const activeView = ref<'graph' | 'pie'>('graph');

const fetchSentimentData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('agents.sentiment.data', props.agent.id));
        sentimentData.value = response.data;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load sentiment data';
        console.error('Error fetching sentiment data:', err);
    } finally {
        loading.value = false;
    }
};

// Format date for display (e.g., "Dec 3")
const formatDate = (dateStr: string): string => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Line chart data
const lineChartData = computed(() => {
    if (!sentimentData.value) return null;

    return {
        labels: sentimentData.value.sentimentByDate.map((d: any) => formatDate(d.date)),
        datasets: [
            {
                label: 'Positive',
                data: sentimentData.value.sentimentByDate.map((d: any) => d.positive),
                borderColor: 'rgb(96, 165, 250)',
                backgroundColor: 'rgba(96, 165, 250, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(96, 165, 250)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            },
            {
                label: 'Neutral',
                data: sentimentData.value.sentimentByDate.map((d: any) => d.neutral),
                borderColor: 'rgb(71, 85, 105)',
                backgroundColor: 'rgba(71, 85, 105, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(71, 85, 105)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            },
            {
                label: 'Negative',
                data: sentimentData.value.sentimentByDate.map((d: any) => d.negative),
                borderColor: 'rgb(248, 113, 113)',
                backgroundColor: 'rgba(248, 113, 113, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(248, 113, 113)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            },
            {
                label: 'Unspecified',
                data: sentimentData.value.sentimentByDate.map((d: any) => d.unspecified),
                borderColor: 'rgb(203, 213, 225)',
                backgroundColor: 'rgba(203, 213, 225, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(203, 213, 225)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            },
        ]
    };
});

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
                }
            }
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
                label: function(context) {
                    return `${context.dataset.label}: ${context.parsed.y}`;
                }
            }
        }
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
                }
            }
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
            }
        }
    }
};

// Pie chart data
const pieChartData = computed(() => {
    if (!sentimentData.value) return null;

    const totals = sentimentData.value.totalSentiments;

    return {
        labels: ['Neutral', 'Positive', 'Negative', 'Unspecified'],
        datasets: [{
            data: [totals.neutral, totals.positive, totals.negative, totals.unspecified],
            backgroundColor: [
                'rgb(71, 85, 105)',
                'rgb(96, 165, 250)',
                'rgb(248, 113, 113)',
                'rgb(203, 213, 225)',
            ],
            borderColor: '#fff',
            borderWidth: 2,
        }]
    };
});

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
                }
            }
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
                label: function(context) {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = sentimentData.value?.totalSentiments?.total || 0;
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                }
            }
        }
    }
};

// Summary stats
const summaryStats = computed(() => {
    if (!sentimentData.value) return null;

    const totals = sentimentData.value.totalSentiments;
    const total = totals.total;

    return {
        positive: {
            count: totals.positive,
            percentage: total > 0 ? ((totals.positive / total) * 100).toFixed(1) : '0',
        },
        neutral: {
            count: totals.neutral,
            percentage: total > 0 ? ((totals.neutral / total) * 100).toFixed(1) : '0',
        },
        negative: {
            count: totals.negative,
            percentage: total > 0 ? ((totals.negative / total) * 100).toFixed(1) : '0',
        },
        unspecified: {
            count: totals.unspecified,
            percentage: total > 0 ? ((totals.unspecified / total) * 100).toFixed(1) : '0',
        },
        total: total,
    };
});

onMounted(() => {
    fetchSentimentData();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Sentiment Analysis" description="Analyze user sentiment patterns and trends">
        <template #header-icon>
            <SmilePlus class="h-5 w-5" />
        </template>

        <!-- Delayed Data Notice -->
        <div class="mb-6 flex justify-end">
            <p class="text-sm text-muted-foreground">
                Analytics data for today might be delayed
            </p>
        </div>

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
                            @click="fetchSentimentData"
                            class="text-sm text-muted-foreground hover:text-foreground underline"
                        >
                            Try again
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Content -->
        <div v-else-if="sentimentData" class="space-y-6">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Positive</p>
                                <Badge class="bg-blue-100 text-blue-700 hover:bg-blue-100">
                                    {{ summaryStats?.positive.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.positive.count }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Neutral</p>
                                <Badge variant="secondary">
                                    {{ summaryStats?.neutral.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.neutral.count }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Negative</p>
                                <Badge class="bg-red-100 text-red-700 hover:bg-red-100">
                                    {{ summaryStats?.negative.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.negative.count }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="!p-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">Unspecified</p>
                                <Badge variant="outline">
                                    {{ summaryStats?.unspecified.percentage }}%
                                </Badge>
                            </div>
                            <p class="text-2xl font-bold">{{ summaryStats?.unspecified.count }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sentiment Chart -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CardTitle>Sentiment</CardTitle>
                            <Info class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <Tabs v-model="activeView" class="w-auto">
                            <TabsList>
                                <TabsTrigger value="graph" @click="activeView = 'graph'">
                                    Graph
                                </TabsTrigger>
                                <TabsTrigger value="pie" @click="activeView = 'pie'">
                                    Pie Chart
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Line Chart -->
                    <div v-if="activeView === 'graph'" class="h-96">
                        <Line
                            v-if="lineChartData"
                            :data="lineChartData"
                            :options="lineChartOptions"
                        />
                    </div>

                    <!-- Pie Chart -->
                    <div v-else class="h-96 flex items-center justify-center">
                        <div class="w-full max-w-md">
                            <Pie
                                v-if="pieChartData"
                                :data="pieChartData"
                                :options="pieChartOptions"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

        </div>
    </AgentLayout>
</template>
